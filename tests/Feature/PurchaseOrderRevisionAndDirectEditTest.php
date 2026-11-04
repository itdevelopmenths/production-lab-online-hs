<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\PoLogPerubahan;
use App\Models\PoRevisi;
use App\Models\PoStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderRevisionService;
use App\Services\Purchasing\PurchasingStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseOrderRevisionAndDirectEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchasing;
    protected User $manager;
    protected Supplier $supplier;
    protected Gudang $gudang;
    protected Produk $produk1;
    protected PurchasingStageService $stageService;
    protected PurchaseOrderRevisionService $revisionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->supplier = Supplier::active()->firstOrFail();
        $this->gudang = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();

        $this->produk1 = Produk::bahan()->active()->firstOrFail();

        $this->stageService = app(PurchasingStageService::class);
        $this->revisionService = app(PurchaseOrderRevisionService::class);
    }

    protected function createSamplePo(string $stage = PoStage::STAGE_4): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-' . uniqid(),
            'no_invoice' => 'INV-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudang->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'tempo',
            'status' => 'berjalan',
            'stage_aktif' => $stage,
            'subtotal_produk' => 1000000,
            'grand_total' => 1000000,
            'created_by' => $this->purchasing->id,
        ]);

        $po->items()->create([
            'produk_id' => $this->produk1->id,
            'qty' => 100,
            'harga_total' => 1000000,
            'hpp_per_satuan' => 10000,
        ]);

        $po->stages()->create([
            'stage' => $stage,
            'urutan' => 4,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subHours(2),
        ]);

        return $po;
    }

    /**
     * TC-33: Edit langsung field operasional tanpa approval tercatat di PO_LOG_PERUBAHAN.
     */
    public function test_tc33_direct_edit_operational_fields_logs_to_po_log_perubahan(): void
    {
        $po = $this->createSamplePo(PoStage::STAGE_7);

        $this->revisionService->updateOperationalFields($po, [
            'no_resi' => 'RESI-NEW-999',
            'ekspedisi' => 'SiCepat Cargo',
            'catatan' => 'Catatan operasional diperbarui',
        ], $this->purchasing);

        $po->refresh();
        $this->assertEquals('Catatan operasional diperbarui', $po->catatan);
        $this->assertEquals('RESI-NEW-999', $po->pengiriman?->no_resi);
        $this->assertEquals('SiCepat Cargo', $po->pengiriman?->ekspedisi);

        // Audit log tercatat
        $logs = PoLogPerubahan::where('po_id', $po->id)->get();
        $this->assertGreaterThanOrEqual(3, $logs->count());
        $this->assertTrue($logs->contains('nama_field', 'pengiriman.no_resi'));
        $this->assertTrue($logs->contains('nama_field', 'pengiriman.ekspedisi'));
        $this->assertTrue($logs->contains('nama_field', 'catatan'));
    }

    /**
     * TC-34: Revisi menunggu memblokir penyelesaian Stage 3, 4, 5.
     */
    public function test_tc34_pending_revision_blocks_stage_completion(): void
    {
        $po = $this->createSamplePo(PoStage::STAGE_4);

        // Ajukan revisi
        $this->revisionService->submitCommitmentRevision($po, [
            'items' => [
                ['produk_id' => $this->produk1->id, 'qty' => 120, 'harga_total' => 1200000],
            ],
        ], 'Supplier minta penambahan volume', $this->purchasing);

        // Coba selesaikan Stage 4 tempo -> harus diblokir
        $this->expectException(ValidationException::class);
        $this->stageService->completeStage4Tempo($po, $this->purchasing);
    }

    /**
     * TC-35: Approval revisi oleh Manager memperbarui data aktif dan menaikkan revisi_ke.
     */
    public function test_tc35_revision_approval_updates_active_data_and_increments_revisi_ke(): void
    {
        $po = $this->createSamplePo(PoStage::STAGE_4);
        $this->assertEquals(0, $po->revisi_ke);

        $revisi = $this->revisionService->submitCommitmentRevision($po, [
            'items' => [
                ['produk_id' => $this->produk1->id, 'qty' => 150, 'harga_total' => 1500000],
            ],
        ], 'Penambahan kuantitas bahan baku', $this->purchasing);

        $this->revisionService->approveRevision($revisi, $this->manager);
        $po->refresh();

        $this->assertEquals(1, $po->revisi_ke);
        $this->assertEquals(1500000, (float) $po->grand_total);
        $this->assertEquals(150, (float) $po->items->first()->qty);
        $this->assertEquals(PoRevisi::STATUS_DISETUJUI, $revisi->status);
    }

    /**
     * TC-36: Penolakan revisi mempertahankan data asli dan mewajibkan alasan.
     */
    public function test_tc36_revision_rejection_requires_reason_and_keeps_original_data(): void
    {
        $po = $this->createSamplePo(PoStage::STAGE_4);

        $revisi = $this->revisionService->submitCommitmentRevision($po, [
            'items' => [
                ['produk_id' => $this->produk1->id, 'qty' => 200, 'harga_total' => 2000000],
            ],
        ], 'Penambahan kuantitas', $this->purchasing);

        // Coba reject tanpa alasan -> gagal
        try {
            $this->revisionService->rejectRevision($revisi, $this->manager, '');
            $this->fail('Harusnya gagal tanpa alasan');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        // Reject dengan alasan valid
        $this->revisionService->rejectRevision($revisi, $this->manager, 'Budget tidak mencukupi untuk penambahan');
        $po->refresh();

        $this->assertEquals(0, $po->revisi_ke);
        $this->assertEquals(1000000, (float) $po->grand_total);
        $this->assertEquals(100, (float) $po->items->first()->qty);
        $this->assertEquals(PoRevisi::STATUS_DITOLAK, $revisi->status);
        $this->assertEquals('Budget tidak mencukupi untuk penambahan', $revisi->catatan_keputusan);
    }

    /**
     * TC-37: Usulan revisi komitmen ditolak pada Stage 8, Stage 9, selesai, atau dibatalkan.
     */
    public function test_tc37_commitment_revision_rejected_at_stage_8_and_later(): void
    {
        $po = $this->createSamplePo(PoStage::STAGE_8);

        $this->expectException(ValidationException::class);
        $this->revisionService->submitCommitmentRevision($po, [
            'items' => [
                ['produk_id' => $this->produk1->id, 'qty' => 120, 'harga_total' => 1200000],
            ],
        ], 'Revisi terlambat saat barang sudah unloading', $this->purchasing);
    }

    /**
     * TC-29: Pembayaran susulan setelah Stage 4 selesai tidak memengaruhi stage.
     */
    public function test_tc29_subsequent_payment_after_stage_4_does_not_affect_stage(): void
    {
        $po = $this->createSamplePo(PoStage::STAGE_5);

        // Catat pembayaran susulan
        $paymentService = app(\App\Services\Purchasing\PurchasingPaymentService::class);
        $paymentService->recordPayment($po, [
            'skema' => 'termin',
            'nominal' => 400000,
            'tanggal_bayar' => now()->toDateString(),
        ]);
        $po->refresh();

        // Stage tetap Stage 5
        $this->assertEquals(PoStage::STAGE_5, $po->stage_aktif);
        $this->assertEquals(400000, $po->totalDibayar());
    }
}
