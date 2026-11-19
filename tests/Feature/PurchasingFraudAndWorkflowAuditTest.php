<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Payment;
use App\Models\PoRevisi;
use App\Models\PoStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderTermin;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderRevisionService;
use App\Services\Purchasing\PurchasingPaymentService;
use App\Services\Purchasing\PurchasingStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchasingFraudAndWorkflowAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchasing;
    protected User $manager;
    protected User $gudangUser;
    protected Supplier $supplier;
    protected Gudang $gudang;
    protected Produk $produk1;
    protected Produk $produk2;
    protected PurchasingStageService $stageService;
    protected PurchaseOrderRevisionService $revisionService;
    protected PurchasingPaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $this->supplier = Supplier::active()->firstOrFail();
        $this->gudang = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();

        $bahans = Produk::bahan()->active()->take(2)->get();
        $this->assertCount(2, $bahans);
        $this->produk1 = $bahans[0];
        $this->produk2 = $bahans[1];

        $this->stageService = app(PurchasingStageService::class);
        $this->revisionService = app(PurchaseOrderRevisionService::class);
        $this->paymentService = app(PurchasingPaymentService::class);
    }

    protected function createPo(array $poOverrides = [], int $itemHarga = 1000000, int $itemQty = 100): PurchaseOrder
    {
        $stage = $poOverrides['stage_aktif'] ?? PoStage::STAGE_4;
        $po = PurchaseOrder::create(array_merge([
            'no_po' => 'PO-AUDIT-' . uniqid(),
            'no_invoice' => 'INV-AUDIT-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudang->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'cash',
            'status' => 'berjalan',
            'status_pembayaran' => 'belum_lunas',
            'stage_aktif' => $stage,
            'subtotal_produk' => $itemHarga,
            'grand_total' => $itemHarga,
            'created_by' => $this->purchasing->id,
        ], $poOverrides));

        $po->items()->create([
            'produk_id' => $this->produk1->id,
            'qty' => $itemQty,
            'harga_total' => $itemHarga,
            'net_total' => $itemHarga,
            'hpp_per_satuan' => $itemQty > 0 ? ($itemHarga / $itemQty) : 0,
        ]);

        if ($stage !== null) {
            $urutan = array_search($stage, PoStage::STAGES, true) ?: 4;
            $po->stages()->create([
                'stage' => $stage,
                'urutan' => $urutan,
                'putaran' => 1,
                'status' => 'berjalan',
                'waktu_mulai' => now()->subHours(2),
            ]);
        }

        return $po;
    }

    public function test_cancelled_po_cannot_be_paid_negative_nominal_rejected_and_not_marked_overdue(): void
    {
        $po = $this->createPo([
            'status' => 'dibatalkan',
            'skema_bayar' => 'tempo',
            'eta' => now()->subDays(10)->toDateString(),
            'tanggal_tempo' => now()->subDays(5)->toDateString(),
            'status_pembayaran' => 'belum_lunas',
        ]);

        $this->actingAs($this->purchasing);

        // 1. Pembayaran via HTTP & service ditolak
        $this->post(route('purchasing.pay', $po), [
            'skema' => 'tempo',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 500000,
        ])->assertSessionHasErrors();

        try {
            $this->paymentService->recordPayment($po, [
                'skema' => 'tempo',
                'tanggal_bayar' => now()->toDateString(),
                'nominal' => 500000,
            ]);
            $this->fail('recordPayment harus menolak PO dibatalkan.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        // 2. Nominal negatif pada PO aktif juga ditolak langsung oleh service
        $activePo = $this->createPo(['skema_bayar' => 'tempo']);
        try {
            $this->paymentService->recordPayment($activePo, [
                'skema' => 'tempo',
                'tanggal_bayar' => now()->toDateString(),
                'nominal' => -100000,
            ]);
            $this->fail('recordPayment harus menolak nominal <= 0.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('nominal', $e->errors());
        }

        // 3. Membuka show() dan menjalankan refreshAllOverdueStatuses() tidak boleh mengubah PO dibatalkan menjadi overdue
        $this->get(route('purchasing.show', $po))->assertOk();
        $this->assertNotEquals('overdue', $po->fresh()->status_pembayaran);

        $this->paymentService->refreshAllOverdueStatuses();
        $this->assertNotEquals('overdue', $po->fresh()->status_pembayaran);
    }

    public function test_cross_po_termin_id_injection_is_rejected(): void
    {
        $poA = $this->createPo(['skema_bayar' => 'termin'], 1000000, 100);
        $terminA = PurchaseOrderTermin::create([
            'po_id' => $poA->id,
            'termin_ke' => 1,
            'tanggal_tempo' => now()->addDays(14)->toDateString(),
            'nominal_tagihan' => 1000000,
            'nominal_dibayar' => 0,
            'status' => 'belum_lunas',
        ]);

        $poB = $this->createPo(['skema_bayar' => 'termin'], 800000, 80);
        $terminB = PurchaseOrderTermin::create([
            'po_id' => $poB->id,
            'termin_ke' => 1,
            'tanggal_tempo' => now()->addDays(14)->toDateString(),
            'nominal_tagihan' => 800000,
            'nominal_dibayar' => 0,
            'status' => 'belum_lunas',
        ]);

        $this->actingAs($this->purchasing);

        // Kirim termin milik PO B saat membayar PO A -> wajib ditolak!
        $res = $this->post(route('purchasing.pay', $poA), [
            'skema' => 'termin',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 400000,
            'termin_id' => $terminB->id,
        ]);
        $res->assertSessionHasErrors('termin_id');

        $this->assertEquals(0, Payment::where('po_id', $poA->id)->count());
        $this->assertEquals(0.0, (float) $terminA->fresh()->nominal_dibayar);
        $this->assertEquals(0.0, (float) $terminB->fresh()->nominal_dibayar);

        // Direct service check
        try {
            $this->paymentService->recordPayment($poA, [
                'skema' => 'termin',
                'tanggal_bayar' => now()->toDateString(),
                'nominal' => 400000,
                'termin_id' => $terminB->id,
            ]);
            $this->fail('recordPayment harus menolak termin_id milik PO lain.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('termin_id', $e->errors());
        }
    }

    public function test_revision_cannot_reduce_grand_total_below_already_paid_amount(): void
    {
        $po = $this->createPo(['skema_bayar' => 'termin', 'stage_aktif' => PoStage::STAGE_4], 1000000, 100);
        $this->paymentService->recordPayment($po, [
            'skema' => 'termin',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 600000,
        ]);

        try {
            $this->revisionService->submitCommitmentRevision($po, [
                'items' => [
                    ['produk_id' => $this->produk1->id, 'qty' => 40, 'harga_total' => 400000],
                ],
            ], 'Turunkan total di bawah nominal sudah dibayar', $this->purchasing);
            $this->fail('submitCommitmentRevision harus menolak grand_total < totalDibayar().');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        // Simulasikan revisi turun diajukan sebelum pembayaran, lalu pembayaran masuk Rp 600.000
        $po2 = $this->createPo(['skema_bayar' => 'termin', 'stage_aktif' => PoStage::STAGE_4], 1000000, 100);
        $revisi = $this->revisionService->submitCommitmentRevision($po2, [
            'items' => [
                ['produk_id' => $this->produk1->id, 'qty' => 40, 'harga_total' => 400000],
            ],
        ], 'Turunkan qty', $this->purchasing);

        Payment::create([
            'po_id' => $po2->id,
            'skema' => 'termin',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 600000,
        ]);

        try {
            $this->revisionService->approveRevision($revisi, $this->manager);
            $this->fail('approveRevision harus menolak bila total sudah dibayar melebihi grand_total baru.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_pending_revision_locks_stages_6_to_9_legacy_actions_direct_edit_on_final_and_negative_qc(): void
    {
        // 1. Stage 6 diblokir saat ada revisi menunggu
        $poStage6 = $this->createPo(['stage_aktif' => PoStage::STAGE_6], 1000000, 100);
        $this->revisionService->submitCommitmentRevision($poStage6, [
            'items' => [
                ['produk_id' => $this->produk1->id, 'qty' => 110, 'harga_total' => 1100000],
            ],
        ], 'Revisi sebelum pengemasan selesai', $this->purchasing);

        try {
            $this->stageService->completeStage6($poStage6, $this->purchasing, []);
            $this->fail('Stage 6 harus diblokir saat revisi menunggu.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('revisi', $e->errors());
        }

        // 2. Legacy receive / approve / update diblokir saat ada revisi menunggu
        $poLegacy = $this->createPo(['status' => 'dikirim_ke_gudang', 'stage_aktif' => PoStage::STAGE_6], 1000000, 100);
        // PO legacy sungguhan = tanpa baris po_stage (guard ensureLegacyFlow menolak PO bertahap).
        $poLegacy->stages()->delete();
        $this->revisionService->submitCommitmentRevision($poLegacy, [
            'items' => [
                ['produk_id' => $this->produk1->id, 'qty' => 100, 'harga_total' => 1200000],
            ],
        ], 'Revisi saat barang dikirim', $this->purchasing);

        $this->actingAs($this->gudangUser);
        $this->post(route('purchasing.receive', $poLegacy), [
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'items' => [
                ['po_item_id' => $poLegacy->items->first()->id, 'qty_diterima' => 100],
            ],
        ])->assertSessionHasErrors('revisi');

        // 3. Direct edit ditolak untuk PO selesai / dibatalkan
        $poCancelled = $this->createPo(['status' => 'dibatalkan', 'stage_aktif' => PoStage::STAGE_6]);
        try {
            $this->revisionService->updateOperationalFields($poCancelled, ['catatan' => 'Manipulasi PO batal'], $this->purchasing);
            $this->fail('directEdit harus menolak PO dibatalkan.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        // 4. Stage 7 (Unloading) menolak qty_fisik negatif
        $poStage7 = $this->createPo(['stage_aktif' => PoStage::STAGE_7]);
        $item7 = $poStage7->items()->firstOrFail();
        try {
            $this->stageService->completeStage7($poStage7, $this->gudangUser, [
                'items' => [
                    $item7->id => ['qty_fisik' => -10],
                ],
            ]);
            $this->fail('Stage 7 harus menolak qty_fisik negatif.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        // 5. Store PO dengan grand_total netto negatif (diskon_total melebihi subtotal) ditolak
        $this->actingAs($this->purchasing);
        $storeRes = $this->post(route('purchasing.store'), [
            'supplier_id' => $this->supplier->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'cash',
            'diskon_total' => 5000000,
            'items' => [
                ['produk_id' => $this->produk1->id, 'qty' => 10, 'harga_total' => 100000],
            ],
        ]);
        $storeRes->assertSessionHasErrors();
    }
}
