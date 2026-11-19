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

    public function test_stage3_view_and_complete_persists_no_referensi_supplier(): void
    {
        $po = $this->createSamplePo(PoStage::STAGE_3);

        $showRes = $this->actingAs($this->purchasing)->get(route('purchasing.show', $po));
        $showRes->assertOk();
        $showRes->assertSee('name="confirmation[no_referensi_supplier]"', false);
        $showRes->assertDontSee('name="confirmation[no_referensi]"', false);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.stages.complete', [$po, PoStage::STAGE_3]), [
                'confirmation' => [
                    'no_referensi_supplier' => 'SO-SUP-2026-01',
                ],
            ])
            ->assertRedirect();

        $po->refresh();
        $this->assertSame('SO-SUP-2026-01', $po->no_referensi_supplier);
        // Setelah konfirmasi supplier, PO menunggu approval Manager (belum pembayaran)
        $this->assertSame(PoStage::STAGE_2, $po->stage_aktif);

        // Legacy payload key confirmation[no_referensi] remains backward-compatible
        $poLegacy = $this->createSamplePo(PoStage::STAGE_3);
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.stages.complete', [$poLegacy, PoStage::STAGE_3]), [
                'confirmation' => [
                    'no_referensi' => 'SO-LEGACY-99',
                ],
            ])
            ->assertRedirect();

        $poLegacy->refresh();
        $this->assertSame('SO-LEGACY-99', $poLegacy->no_referensi_supplier);
    }

    public function test_revision_history_card_is_hidden_when_po_has_no_revisions(): void
    {
        $po = $this->createSamplePo(PoStage::STAGE_4);

        $this->actingAs($this->purchasing)
            ->get(route('purchasing.show', $po))
            ->assertOk()
            ->assertDontSee('Histori Revisi Komitmen');

        $this->actingAs($this->manager)
            ->get(route('purchasing.show', $po))
            ->assertOk()
            ->assertDontSee('Histori Revisi Komitmen');
    }

    public function test_revision_history_card_displays_all_statuses_and_hides_prices_from_gudang(): void
    {
        // Gudang baru dapat melihat PO sejak Tahap 5 (PO Terbit).
        $po = $this->createSamplePo(PoStage::STAGE_5);
        $originalItem = $po->items->first();
        $gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        // Revisi 1: Disetujui (qty 100 -> 120, total 1.000.000 -> 1.200.000)
        $rev1 = $this->revisionService->submitCommitmentRevision($po, [
            'items' => [
                [
                    'po_item_id' => $originalItem->id,
                    'produk_id' => $this->produk1->id,
                    'qty' => 120,
                    'harga_total' => 1200000,
                ],
            ],
        ], 'Penambahan qty batch awal', $this->purchasing);
        $this->revisionService->approveRevision($rev1, $this->manager);

        // Revisi 2: Ditolak (qty 120 -> 150, total 1.200.000 -> 1.500.000)
        $po->refresh();
        $rev2 = $this->revisionService->submitCommitmentRevision($po, [
            'items' => [
                [
                    'po_item_id' => $originalItem->id,
                    'produk_id' => $this->produk1->id,
                    'qty' => 150,
                    'harga_total' => 1500000,
                ],
            ],
        ], 'Usulan tambahan melebihi kapasitas gudang', $this->purchasing);
        $this->revisionService->rejectRevision($rev2, $this->manager, 'Plafon anggaran bulan ini penuh');

        // PO tanpa revisi pending aktif, hanya memiliki histori revisi disetujui & ditolak
        $managerRes = $this->actingAs($this->manager)->get(route('purchasing.show', $po));
        $managerRes->assertOk();
        $managerRes->assertDontSee('Antrean Persetujuan Revisi Komitmen');
        $managerRes->assertSee('Histori Revisi Komitmen');
        $managerRes->assertSee('<caption id="histori-revisi-caption"', false);
        $managerRes->assertSee('aria-labelledby="histori-revisi-caption"', false);
        $managerRes->assertSee('<th scope="col"', false);
        $managerRes->assertSee('<th scope="row"', false);
        $managerRes->assertSee('Revisi ke-1');
        $managerRes->assertSee('Disetujui');
        $managerRes->assertSee('Penambahan qty batch awal');
        $managerRes->assertSee('Revisi ke-2');
        $managerRes->assertSee('Ditolak');
        $managerRes->assertSee('Usulan tambahan melebihi kapasitas gudang');
        $managerRes->assertSee('Plafon anggaran bulan ini penuh');
        $managerRes->assertSee($this->purchasing->name);
        $managerRes->assertSee($this->manager->name);
        $managerRes->assertSee('Rp 1.000.000');
        $managerRes->assertSee('Rp 1.200.000');
        $managerRes->assertSee('+Rp 200.000');
        $managerRes->assertSee('Rp 1.500.000');
        $managerRes->assertSee('+Rp 300.000');

        // Gudang dapat melihat kartu histori non-finansial (status, alasan, catatan, perubahan qty)
        // tetapi TIDAK boleh melihat nominal rupiah apa pun
        $gudangRes = $this->actingAs($gudang)->get(route('purchasing.show', $po));
        $gudangRes->assertOk();
        $gudangRes->assertSee('Histori Revisi Komitmen');
        $gudangRes->assertSee('Penambahan qty batch awal');
        $gudangRes->assertSee('Plafon anggaran bulan ini penuh');
        $gudangRes->assertDontSee('1.000.000');
        $gudangRes->assertDontSee('1.200.000');
        $gudangRes->assertDontSee('1.500.000');
        $gudangRes->assertDontSee('200.000');
        $gudangRes->assertDontSee('300.000');

        // Kasus PO dibatalkan saat ada revisi menunggu -> status revisi Dibatalkan tetap tercatat di histori
        $cancelledPo = $this->createSamplePo(PoStage::STAGE_4);
        $cancelledItem = $cancelledPo->items->first();
        $revPending = $this->revisionService->submitCommitmentRevision($cancelledPo, [
            'items' => [
                [
                    'po_item_id' => $cancelledItem->id,
                    'produk_id' => $this->produk1->id,
                    'qty' => 200,
                    'harga_total' => 2400000,
                ],
            ],
        ], 'Revisi sebelum pembatalan PO', $this->purchasing);
        $revPending->update([
            'status' => PoRevisi::STATUS_DIBATALKAN,
            'catatan_keputusan' => 'Dibatalkan otomatis karena PO dibatalkan.',
            'diputuskan_oleh' => $this->manager->id,
            'diputuskan_pada' => now(),
        ]);
        $cancelledPo->update(['status' => 'dibatalkan']);

        $cancelledRes = $this->actingAs($this->manager)->get(route('purchasing.show', $cancelledPo));
        $cancelledRes->assertOk();
        $cancelledRes->assertSee('Histori Revisi Komitmen');
        $cancelledRes->assertSee('Dibatalkan');
        $cancelledRes->assertSee('Revisi sebelum pembatalan PO');
        $cancelledRes->assertSee('Dibatalkan otomatis karena PO dibatalkan.');
        $cancelledRes->assertDontSee('Setujui Revisi Komitmen');
    }

    /**
     * Setelah approval Manager, PO terkunci: tidak ada direct edit maupun pengajuan revisi.
     */
    public function test_direct_edit_and_revision_routes_are_removed_after_approval_lock(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('purchasing.direct-edit'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('purchasing.revisi.submit'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('purchasing.revisi.approve'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('purchasing.revisi.reject'));
    }

    public function test_po_edit_page_open_before_approval_but_locked_from_approval_onward(): void
    {
        foreach ([PoStage::STAGE_1, PoStage::STAGE_3] as $stage) {
            $po = $this->createSamplePo($stage);
            $this->actingAs($this->purchasing)
                ->get(route('purchasing.edit', $po))
                ->assertOk();
        }

        foreach ([PoStage::STAGE_2, PoStage::STAGE_4, PoStage::STAGE_5, PoStage::STAGE_6] as $stage) {
            $po = $this->createSamplePo($stage);
            $this->actingAs($this->purchasing)
                ->get(route('purchasing.edit', $po))
                ->assertRedirect(route('purchasing.show', $po))
                ->assertSessionHas('error');
        }
    }

    public function test_po_update_is_rejected_once_approval_stage_reached(): void
    {
        $po = $this->createSamplePo(PoStage::STAGE_2);

        $this->actingAs($this->purchasing)
            ->put(route('purchasing.update', $po), [])
            ->assertSessionHasErrors('stage_aktif');
    }

    public function test_supplier_confirmation_comes_before_manager_approval_in_stage_order(): void
    {
        $this->assertSame(
            ['perencanaan', 'konfirmasi_supplier', 'approval', 'pembayaran', 'po_terbit', 'pengiriman', 'unloading', 'input_sistem'],
            array_values(PoStage::STAGES)
        );
    }
}
