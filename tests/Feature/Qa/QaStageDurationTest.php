<?php

namespace Tests\Feature\Qa;

use App\Models\Gudang;
use App\Models\PoLeadTime;
use App\Models\PoStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierLtSummary;
use App\Models\User;
use App\Services\Purchasing\PurchasingStageService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QaStageDurationTest extends TestCase
{
    use RefreshDatabase;

    private User $purchasing;

    private User $manager;

    private User $gudangUser;

    private Supplier $supplier;

    private Gudang $gudang;

    private Produk $produk1;

    private Produk $produk2;

    private PurchasingStageService $stageService;

    protected function setUp(): void
    {
        parent::setUp();

        // Env 'local' mengaktifkan QA tools; CSRF hanya di-skip otomatis saat env 'testing'.
        $this->app['env'] = 'local';
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->seed();

        config([
            'qa.lead_time_test' => true,
            'qa.clock_path' => storage_path('framework/testing/qa-clock-'.uniqid().'.json'),
        ]);

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $this->supplier = Supplier::active()->firstOrFail();
        $this->gudang = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();

        $produks = Produk::bahan()->active()->take(2)->get();
        $this->produk1 = $produks[0];
        $this->produk2 = $produks[1];

        $this->stageService = app(PurchasingStageService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        @unlink(config('qa.clock_path'));

        parent::tearDown();
    }

    private function createSamplePo(string $skemaBayar = 'tempo'): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-'.uniqid(),
            'no_invoice' => 'INV-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudang->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => $skemaBayar,
            'status' => 'draft',
            'subtotal_produk' => 1000000,
            'grand_total' => 1000000,
            'created_by' => $this->purchasing->id,
        ]);

        $po->items()->create([
            'produk_id' => $this->produk1->id,
            'qty' => 100,
            'harga_total' => 600000,
            'hpp_per_satuan' => 6000,
        ]);

        $po->items()->create([
            'produk_id' => $this->produk2->id,
            'qty' => 50,
            'harga_total' => 400000,
            'hpp_per_satuan' => 8000,
        ]);

        $this->stageService->initializePoStages($po, now()->subDays(10), $this->purchasing);

        return $po;
    }

    private function stageRow(PurchaseOrder $po, string $stage, ?int $putaran = null): PoStage
    {
        $query = $po->stages()->where('stage', $stage);

        if ($putaran !== null) {
            $query->where('putaran', $putaran);
        }

        return $query->orderBy('putaran')->firstOrFail();
    }

    private function completeUrl(PurchaseOrder $po, string $stage): string
    {
        return route('purchasing.stages.complete', [$po, $stage]);
    }

    public function test_duration_sets_waktu_selesai_from_stage_start(): void
    {
        $po = $this->createSamplePo();

        $this->actingAs($this->purchasing)
            ->post($this->completeUrl($po, PoStage::STAGE_1), ['qa_durasi_hari' => 1, 'qa_durasi_jam' => 6])
            ->assertSessionHasNoErrors();

        $stage = $this->stageRow($po, PoStage::STAGE_1);

        $this->assertSame(1800, $stage->durasi_menit);
        $this->assertTrue($stage->waktu_selesai->equalTo($stage->waktu_mulai->copy()->addMinutes(1800)));
        $this->assertSame(PoStage::STAGE_3, $po->fresh()->stage_aktif);
    }

    public function test_zero_duration_is_allowed(): void
    {
        $po = $this->createSamplePo();

        $this->actingAs($this->purchasing)
            ->post($this->completeUrl($po, PoStage::STAGE_1), ['qa_durasi_hari' => 0])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->stageRow($po, PoStage::STAGE_1)->durasi_menit);
    }

    public function test_negative_duration_rejected(): void
    {
        $po = $this->createSamplePo();

        $this->actingAs($this->purchasing)
            ->post($this->completeUrl($po, PoStage::STAGE_1), ['qa_durasi_jam' => -2])
            ->assertSessionHasErrors('qa_durasi_jam');

        $this->assertSame(PoStage::STAGE_1, $po->fresh()->stage_aktif);
    }

    public function test_duration_beyond_now_rejected_with_hint(): void
    {
        $po = $this->createSamplePo();

        $response = $this->actingAs($this->purchasing)
            ->post($this->completeUrl($po, PoStage::STAGE_1), ['qa_durasi_hari' => 11]);

        $response->assertSessionHasErrors('qa_durasi');
        $this->assertStringContainsString('Mundurkan PO', session('errors')->first('qa_durasi'));
        $this->assertSame(PoStage::STAGE_1, $po->fresh()->stage_aktif);
    }

    public function test_duration_ignored_when_disabled(): void
    {
        $this->app['env'] = 'testing';
        $po = $this->createSamplePo();

        $this->actingAs($this->purchasing)
            ->post($this->completeUrl($po, PoStage::STAGE_1), ['qa_durasi_hari' => 1])
            ->assertSessionHasNoErrors();

        $this->assertGreaterThanOrEqual(14399, $this->stageRow($po, PoStage::STAGE_1)->durasi_menit);
    }

    public function test_enabled_with_empty_duration_fields_uses_old_behavior(): void
    {
        $po = $this->createSamplePo();
        $mulai = $this->stageRow($po, PoStage::STAGE_1)->waktu_mulai;

        $this->actingAs($this->purchasing)
            ->post($this->completeUrl($po, PoStage::STAGE_1), [
                'qa_durasi_hari' => '',
                'qa_durasi_jam' => '',
                'waktu_selesai' => $mulai->copy()->addHours(2)->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(120, $this->stageRow($po, PoStage::STAGE_1)->durasi_menit);
    }

    public function test_reject_and_payment_routes_use_duration(): void
    {
        $po = $this->createSamplePo('tempo');

        $this->actingAs($this->purchasing)
            ->post($this->completeUrl($po, PoStage::STAGE_1), ['qa_durasi_jam' => 1])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->purchasing)
            ->post($this->completeUrl($po, PoStage::STAGE_3), ['qa_durasi_jam' => 1])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->manager)
            ->post(route('purchasing.stages.reject-stage2', $po), ['alasan' => 'Harga perlu dinegosiasi ulang', 'qa_durasi_jam' => 5])
            ->assertSessionHasNoErrors();

        $this->assertSame(300, $this->stageRow($po, PoStage::STAGE_2, 1)->durasi_menit);
        $this->assertSame(PoStage::STAGE_3, $po->fresh()->stage_aktif);

        $this->actingAs($this->purchasing)
            ->post($this->completeUrl($po, PoStage::STAGE_3), ['qa_durasi_jam' => 1])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->manager)
            ->post($this->completeUrl($po, PoStage::STAGE_2), ['qa_durasi_jam' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(PoStage::STAGE_4, $po->fresh()->stage_aktif);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.stages.lanjut-tempo', $po), ['qa_durasi_jam' => 2])
            ->assertSessionHasNoErrors();

        $this->assertSame(120, $this->stageRow($po, PoStage::STAGE_4)->durasi_menit);
        $this->assertSame(PoStage::STAGE_5, $po->fresh()->stage_aktif);
    }

    public function test_full_lifecycle_with_durations_records_lead_time(): void
    {
        $po = $this->createSamplePo('tempo');
        $durasi = ['qa_durasi_jam' => 12];

        $this->actingAs($this->purchasing);
        $this->post($this->completeUrl($po, PoStage::STAGE_1), $durasi)->assertSessionHasNoErrors();
        $this->post($this->completeUrl($po, PoStage::STAGE_3), $durasi)->assertSessionHasNoErrors();

        $this->actingAs($this->manager);
        $this->post($this->completeUrl($po, PoStage::STAGE_2), $durasi)->assertSessionHasNoErrors();

        $this->actingAs($this->purchasing);
        $this->post(route('purchasing.stages.lanjut-tempo', $po), $durasi)->assertSessionHasNoErrors();
        $this->post($this->completeUrl($po, PoStage::STAGE_5), $durasi)->assertSessionHasNoErrors();
        $this->post($this->completeUrl($po, PoStage::STAGE_6), $durasi + [
            'shipping' => ['ekspedisi' => 'JNE Cargo', 'no_resi' => 'JNE123456'],
        ])->assertSessionHasNoErrors();

        $po->load('items');
        $this->actingAs($this->gudangUser);
        $this->post($this->completeUrl($po, PoStage::STAGE_7), $durasi + [
            'physical_check' => [
                'kondisi' => 'baik',
                'items' => [
                    ['po_item_id' => $po->items[0]->id, 'qty_fisik' => 100],
                    ['po_item_id' => $po->items[1]->id, 'qty_fisik' => 50],
                ],
            ],
        ])->assertSessionHasNoErrors();
        $this->post($this->completeUrl($po, PoStage::STAGE_8), $durasi)->assertSessionHasNoErrors();

        $this->assertSame('selesai', $po->fresh()->status);

        $leadTime = PoLeadTime::where('po_id', $po->id)->firstOrFail();
        $this->assertSame(8 * 720, (int) $leadTime->total_lt_menit);

        $summary = SupplierLtSummary::where('supplier_id', $this->supplier->id)->firstOrFail();
        $this->assertSame(1, (int) $summary->jumlah_transaksi);
    }

    public function test_form_renders_duration_inputs_only_when_enabled(): void
    {
        $po = $this->createSamplePo();

        $this->actingAs($this->purchasing)
            ->get(route('purchasing.show', $po))
            ->assertOk()
            ->assertSee('qa_durasi_hari', false);

        $this->app['env'] = 'testing';

        $this->actingAs($this->purchasing)
            ->get(route('purchasing.show', $po))
            ->assertOk()
            ->assertDontSee('qa_durasi_hari', false);
    }
}
