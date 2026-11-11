<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderTermin;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchasingPaymentService;
use App\Services\Purchasing\PurchasingStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchasingCancelledPaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $purchasing;
    private User $manager;
    private Supplier $supplier;
    private Gudang $gudang;
    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->supplier = Supplier::active()->firstOrFail();
        $this->gudang = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $this->produk = Produk::bahan()->active()->firstOrFail();
    }

    private function makePo(array $overrides = []): PurchaseOrder
    {
        $total = $overrides['grand_total'] ?? 1500000;

        $po = PurchaseOrder::create(array_merge([
            'no_po' => 'PO-BATAL-TEST-' . uniqid(),
            'no_invoice' => 'INV-BATAL-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudang->id,
            'tanggal' => now()->toDateString(),
            'eta' => now()->addDays(5)->toDateString(),
            'skema_bayar' => 'tempo',
            'subtotal_produk' => $total,
            'grand_total' => $total,
            'status' => 'diajukan',
            'status_pembayaran' => 'belum_lunas',
            'created_by' => $this->purchasing->id,
        ], $overrides));

        $po->items()->create([
            'produk_id' => $this->produk->id,
            'qty' => 10,
            'harga_total' => $total,
            'hpp_per_satuan' => round($total / 10, 4),
        ]);

        return $po->fresh(['items', 'supplier', 'gudang']);
    }

    public function test_cancelling_po_via_controller_sets_status_pembayaran_to_batal_and_zeroes_sisa_tagihan(): void
    {
        $po = $this->makePo(['status' => 'diajukan', 'status_pembayaran' => 'belum_lunas']);

        $this->actingAs($this->manager)
            ->post(route('purchasing.cancel', $po), ['alasan' => 'Supplier tidak sanggup kirim'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals('dibatalkan', $po->status);
        $this->assertEquals('batal', $po->status_pembayaran);
        $this->assertEquals(0.0, $po->sisaTagihan());
        $this->assertFalse($po->isLunas());
    }

    public function test_cancelling_po_via_stage_service_sets_status_pembayaran_to_batal(): void
    {
        $po = $this->makePo(['status' => 'berjalan', 'status_pembayaran' => 'belum_lunas']);
        app(PurchasingStageService::class)->initializePoStages($po, now()->subHour(), $this->purchasing);

        app(PurchasingStageService::class)->cancelPo($po, $this->manager, 'Dibatalkan melalui stage service');

        $po->refresh();
        $this->assertEquals('dibatalkan', $po->status);
        $this->assertEquals('batal', $po->status_pembayaran);
    }

    public function test_data_ap_and_data_endpoints_show_batal_and_filter_properly_including_legacy_rows(): void
    {
        // PO batal dengan status_pembayaran lama yang masih 'belum_lunas' dan pernah bayar DP 500rb
        $poBatal = $this->makePo([
            'no_po' => 'PO-AP-BATAL-01',
            'grand_total' => 2000000,
            'status' => 'dibatalkan',
            'status_pembayaran' => 'belum_lunas',
        ]);
        $poBatal->payments()->create([
            'skema' => 'termin',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 500000,
        ]);

        // PO aktif belum lunas
        $poAktif = $this->makePo([
            'no_po' => 'PO-AP-AKTIF-01',
            'grand_total' => 1000000,
            'status' => 'diajukan',
            'status_pembayaran' => 'belum_lunas',
        ]);

        $this->actingAs($this->purchasing);

        // 1. Tanpa filter: PO batal tampil dengan status_pembayaran = 'batal', sisa = '0', total_dibayar tetap 500.000
        $resAll = $this->getJson(route('purchasing.data-ap'))->assertOk();
        $rowsAll = collect($resAll->json('data'));

        $rowBatal = $rowsAll->firstWhere('no_po', 'PO-AP-BATAL-01');
        $this->assertNotNull($rowBatal);
        $this->assertEquals('batal', $rowBatal['status_pembayaran']);
        $this->assertEquals('500.000', $rowBatal['total_dibayar']);
        $this->assertEquals('0', $rowBatal['sisa']);
        $this->assertEquals('—', $rowBatal['jatuh_tempo_terdekat']);
        $this->assertStringNotContainsString('Bayar', $rowBatal['action']);

        // 2. Filter status_bayar=belum_lunas tidak boleh menyertakan PO batal
        $resBelum = $this->getJson(route('purchasing.data-ap', ['status_bayar' => 'belum_lunas']))->assertOk();
        $rowsBelum = collect($resBelum->json('data'));
        $this->assertNull($rowsBelum->firstWhere('no_po', 'PO-AP-BATAL-01'));
        $this->assertNotNull($rowsBelum->firstWhere('no_po', 'PO-AP-AKTIF-01'));

        // 3. Filter status_bayar=batal hanya menyertakan PO batal
        $resBatal = $this->getJson(route('purchasing.data-ap', ['status_bayar' => 'batal']))->assertOk();
        $rowsBatal = collect($resBatal->json('data'));
        $this->assertNotNull($rowsBatal->firstWhere('no_po', 'PO-AP-BATAL-01'));
        $this->assertNull($rowsBatal->firstWhere('no_po', 'PO-AP-AKTIF-01'));

        // 4. Endpoint daftar PO utama (/purchasing/data) juga konsisten
        $resDataBelum = $this->getJson(route('purchasing.data', ['status_bayar' => 'belum_lunas']))->assertOk();
        $this->assertNull(collect($resDataBelum->json('data'))->firstWhere('no_po', 'PO-AP-BATAL-01'));

        $resDataBatal = $this->getJson(route('purchasing.data', ['status_bayar' => 'batal']))->assertOk();
        $rowDataBatal = collect($resDataBatal->json('data'))->firstWhere('no_po', 'PO-AP-BATAL-01');
        $this->assertNotNull($rowDataBatal);
        $this->assertEquals('Batal', $rowDataBatal['status_pembayaran']);
        $this->assertEquals('0', $rowDataBatal['sisa']);
    }

    public function test_purchasing_index_renders_batal_option_in_ap_filter_and_badge_logic(): void
    {
        $res = $this->actingAs($this->purchasing)->get(route('purchasing.index'));

        $res->assertOk();
        $res->assertSee('<option value="batal">', false);
        $res->assertSee("norm.includes('batal')", false);
    }

    public function test_purchasing_show_displays_batal_ap_status_and_does_not_mark_cancelled_po_as_overdue(): void
    {
        $po = $this->makePo([
            'status' => 'dibatalkan',
            'status_pembayaran' => 'belum_lunas',
            'skema_bayar' => 'tempo',
            'tanggal_tempo' => now()->subDays(5)->toDateString(),
            'eta' => now()->subDays(5)->toDateString(),
        ]);

        $res = $this->actingAs($this->purchasing)->get(route('purchasing.show', $po));

        $res->assertOk();
        $res->assertSee('AP: Batal');
        $res->assertDontSee('AP: Belum Lunas');
        $res->assertDontSee('AP: Overdue');
        $res->assertDontSee('(Overdue)');

        $po->refresh();
        $this->assertEquals('batal', $po->status_pembayaran);
    }

    public function test_refresh_all_overdue_statuses_does_not_mark_cancelled_po_or_its_termins_overdue(): void
    {
        $poBatal = $this->makePo([
            'status' => 'dibatalkan',
            'status_pembayaran' => 'batal',
            'skema_bayar' => 'termin',
            'eta' => now()->subDays(10)->toDateString(),
        ]);
        $terminBatal = PurchaseOrderTermin::create([
            'po_id' => $poBatal->id,
            'termin_ke' => 1,
            'tanggal_tempo' => now()->subDays(3)->toDateString(),
            'nominal_tagihan' => 1500000,
            'nominal_dibayar' => 0,
            'status' => 'belum_lunas',
        ]);

        $poAktif = $this->makePo([
            'status' => 'disetujui',
            'status_pembayaran' => 'belum_lunas',
            'skema_bayar' => 'tempo',
            'eta' => now()->subDays(4)->toDateString(),
            'tanggal_tempo' => now()->subDays(4)->toDateString(),
        ]);

        app(PurchasingPaymentService::class)->refreshAllOverdueStatuses();

        $poBatal->refresh();
        $terminBatal->refresh();
        $poAktif->refresh();

        $this->assertEquals('batal', $poBatal->status_pembayaran);
        $this->assertEquals('belum_lunas', $terminBatal->status);
        $this->assertEquals('overdue', $poAktif->status_pembayaran);
    }

    public function test_payment_is_rejected_when_po_is_cancelled(): void
    {
        $po = $this->makePo([
            'status' => 'dibatalkan',
            'status_pembayaran' => 'batal',
        ]);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), [
                'skema' => 'tempo',
                'tanggal_bayar' => now()->toDateString(),
                'nominal' => 500000,
            ])
            ->assertSessionHasErrors('status');

        $this->assertEquals(0, $po->payments()->count());

        $this->expectException(ValidationException::class);
        app(PurchasingPaymentService::class)->recordPayment($po, [
            'skema' => 'tempo',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 500000,
        ]);
    }

    public function test_migration_backfills_existing_cancelled_pos_to_batal_without_touching_active_pos(): void
    {
        $poBatalLegacy = $this->makePo([
            'status' => 'dibatalkan',
            'status_pembayaran' => 'belum_lunas',
        ]);
        $poAktif = $this->makePo([
            'status' => 'disetujui',
            'status_pembayaran' => 'belum_lunas',
        ]);

        $migration = require database_path('migrations/2026_11_11_000003_sync_cancelled_po_payment_status.php');
        $migration->up();

        $this->assertEquals('batal', $poBatalLegacy->fresh()->status_pembayaran);
        $this->assertEquals('belum_lunas', $poAktif->fresh()->status_pembayaran);
    }
}
