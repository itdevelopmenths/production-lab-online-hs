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

    /**
     * TC-38: Usulan revisi komitmen dengan satuan beli, faktor konversi, dan biaya global.
     */
    public function test_tc38_commitment_revision_with_uom_conversion_and_global_costs(): void
    {
        $po = $this->createSamplePo(PoStage::STAGE_4);
        $originalItem = $po->items->first();
        $originalItemId = $originalItem->id;

        // User purchasing mengajukan revisi dengan satuan beli box (isi 25), diskon, ppn, ongkir, adjustment
        $response = $this->actingAs($this->purchasing)->post(route('purchasing.revisi.submit', $po), [
            'alasan' => 'Penyesuaian kuantiti ke satuan box dan diskon promo supplier',
            'data_usulan' => [
                'items' => [
                    [
                        'po_item_id' => $originalItemId,
                        'produk_id' => $this->produk1->id,
                        'qty_satuan_beli' => 10, // 10 box
                        'satuan_beli' => 'box',
                        'faktor_konversi' => 25.0, // 1 box = 25 pcs -> total 250 pcs
                        'qty' => 250,
                        'harga_total' => 2500000,
                    ],
                ],
                'diskon_total' => 100000,
                'ppn_nominal' => 264000, // 11% dari (2.500.000 - 100.000)
                'ongkos_kirim' => 50000,
                'adjustment' => -14000, // Pembulatan
            ],
        ]);

        $response->assertRedirect();
        $po->refresh();

        $this->assertEquals(1, $po->revisis()->where('status', 'menunggu')->count());
        $revisi = $po->revisis()->where('status', 'menunggu')->first();

        // Validasi snapshot usulan
        $this->assertEquals(10, $revisi->data_usulan['items'][0]['qty_satuan_beli']);
        $this->assertEquals('box', $revisi->data_usulan['items'][0]['satuan_beli']);
        $this->assertEquals(100000, $revisi->data_usulan['diskon_total']);

        // Manager menyetujui revisi
        $approveResponse = $this->actingAs($this->manager)->post(route('purchasing.revisi.approve', $revisi));
        $approveResponse->assertRedirect();

        $po->refresh();
        $this->assertEquals(1, $po->revisi_ke);
        // Subtotal = 2.500.000, Diskon = 100.000, PPN = 264.000, Ongkir = 50.000, Adj = -14.000
        // Grand Total = 2.500.000 - 100.000 + 264.000 + 50.000 - 14.000 = 2.700.000
        $this->assertEquals(2500000, (float) $po->subtotal_produk);
        $this->assertEquals(100000, (float) $po->diskon_total);
        $this->assertEquals(264000, (float) $po->ppn_nominal);
        $this->assertEquals(50000, (float) $po->ongkos_kirim);
        $this->assertEquals(-14000, (float) $po->adjustment);
        $this->assertEquals(2700000, (float) $po->grand_total);

        // Pastikan ID item tetap sama (in-place update, bukan re-create)
        $this->assertCount(1, $po->items);
        $updatedItem = $po->items->first();
        $this->assertEquals($originalItemId, $updatedItem->id);
        $this->assertEquals(10, (float) $updatedItem->qty_satuan_beli);
        $this->assertEquals('box', $updatedItem->satuan_beli);
        $this->assertEquals(25.0, (float) $updatedItem->faktor_konversi);
        $this->assertEquals(250, (float) $updatedItem->qty); // 10 * 25
        $this->assertEquals(2500000, (float) $updatedItem->harga_total);

        // HPP per satuan dasar = net total / qty dasar = 2.700.000 / 250 = 10.800
        $this->assertEquals(10800, (float) $updatedItem->hpp_per_satuan);
    }

    public function test_revision_approval_card_displays_old_vs_new_deltas_and_hides_prices_from_gudang(): void
    {
        $po = $this->createSamplePo(PoStage::STAGE_4);
        $originalItem = $po->items->first();
        $gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $this->actingAs($this->purchasing)->post(route('purchasing.revisi.submit', $po), [
            'alasan' => 'Penyesuaian qty ke 10 box isi 25 pcs dan diskon supplier',
            'data_usulan' => [
                'items' => [
                    [
                        'po_item_id' => $originalItem->id,
                        'produk_id' => $this->produk1->id,
                        'qty_satuan_beli' => 10,
                        'satuan_beli' => 'box',
                        'faktor_konversi' => 25.0,
                        'qty' => 250,
                        'harga_total' => 2500000,
                    ],
                ],
                'subtotal_produk' => 2500000,
                'diskon_total' => 100000,
                'ppn_nominal' => 264000,
                'ongkos_kirim' => 50000,
                'adjustment' => -14000,
                'grand_total' => 2700000,
            ],
        ])->assertRedirect();

        $revisi = $po->revisis()->where('status', PoRevisi::STATUS_MENUNGGU)->firstOrFail();

        // 1. Manager melihat perbandingan lengkap (data_lama -> data_usulan + selisih) dan kontrol approval
        $managerRes = $this->actingAs($this->manager)->get(route('purchasing.show', $po));
        $managerRes->assertOk();
        $managerHtml = $managerRes->getContent();

        $managerRes->assertSee('Antrean Persetujuan Revisi Komitmen');
        $managerRes->assertSee('Revisi ke-1');
        $managerRes->assertSee('Menunggu Approval Manager');
        $managerRes->assertSee('Penyesuaian qty ke 10 box isi 25 pcs dan diskon supplier');
        // KPI / Summary dampak finansial menampilkan snapshot lama (data_lama), usulan baru, dan selisih (+Rp 1.700.000)
        $managerRes->assertSee('Grand Total Sebelumnya');
        $managerRes->assertSee('Grand Total Usulan Baru');
        $managerRes->assertSee('+Rp 1.700.000');
        $managerRes->assertSee('-Rp 100.000');
        $managerRes->assertSee('2.700.000');
        $managerRes->assertSee('264.000');
        // Tabel & dialog semantik modern
        $managerRes->assertSee('Dampak Finansial PO', false);
        $managerRes->assertSee('<th scope="col"', false);
        $managerRes->assertSee('<dialog id="reject-revisi-dialog-' . $revisi->id . '"', false);
        $managerRes->assertSee('Setujui Revisi Komitmen');
        $managerRes->assertSee('Tolak Usulan');

        // Pastikan baris finansial lama di card Antrean Persetujuan tidak jatuh menjadi '—' akibat salah baca $rev->data_sebelum
        $this->assertMatchesRegularExpression(
            '/Grand Total Sebelumnya.*?Rp 1\.000\.000/s',
            $managerHtml
        );

        // 2. User Gudang tidak boleh melihat nominal rupiah, ringkasan finansial, maupun tombol approval manager
        $gudangRes = $this->actingAs($gudang)->get(route('purchasing.show', $po));
        $gudangRes->assertOk();
        $gudangRes->assertSee('Antrean Persetujuan Revisi Komitmen');
        $gudangRes->assertSee('10 box');
        $gudangRes->assertDontSee('2.700.000');
        $gudangRes->assertDontSee('1.700.000');
        $gudangRes->assertDontSee('264.000');
        $gudangRes->assertDontSee('Grand Total Sebelumnya');
        $gudangRes->assertDontSee('Setujui Revisi Komitmen');
        $gudangRes->assertDontSee('Tolak Usulan');
    }

    public function test_direct_edit_updates_eta_logs_changes_and_syncs_payment_status(): void
    {
        $po = $this->createSamplePo(PoStage::STAGE_3);
        $po->update([
            'eta' => now()->subDays(3)->toDateString(),
            'status_pembayaran' => 'overdue',
        ]);

        $newEta = now()->addDays(10)->toDateString();

        // 1. Update via HTTP updates eta, logs po_log_perubahan, and clears overdue status
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.direct-edit', $po), [
                'fields' => [
                    'eta' => $newEta,
                    'catatan' => 'Jadwal tiba diperbarui supplier',
                ],
            ])
            ->assertRedirect();

        $po->refresh();
        $this->assertEquals($newEta, $po->eta?->format('Y-m-d'));
        $this->assertEquals('belum_lunas', $po->status_pembayaran);
        $this->assertTrue(
            PoLogPerubahan::where('po_id', $po->id)->where('nama_field', 'eta')->where('nilai_baru', $newEta)->exists()
        );

        // 2. Submitting the same eta when editing another field must not create a duplicate eta log
        $etaLogCountBefore = PoLogPerubahan::where('po_id', $po->id)->where('nama_field', 'eta')->count();
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.direct-edit', $po), [
                'fields' => [
                    'eta' => $newEta,
                    'catatan' => 'Update catatan saja',
                ],
            ])
            ->assertRedirect();

        $this->assertSame(
            $etaLogCountBefore,
            PoLogPerubahan::where('po_id', $po->id)->where('nama_field', 'eta')->count()
        );

        // 3. Clearing eta to empty string persists null cleanly
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.direct-edit', $po), [
                'fields' => ['eta' => ''],
            ])
            ->assertRedirect();

        $po->refresh();
        $this->assertNull($po->eta);

        // 4. Invalid date fails validation (web route flashes validation errors to session)
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.direct-edit', $po), [
                'fields' => ['eta' => 'not-a-date'],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('fields.eta');
    }

    public function test_purchasing_show_uses_single_eta_in_header_and_direct_edit_without_stage_eta_inputs(): void
    {
        $initialEta = now()->addDays(15)->toDateString();
        $poStage3 = $this->createSamplePo(PoStage::STAGE_3);
        $poStage3->update(['eta' => $initialEta]);

        $res3 = $this->actingAs($this->purchasing)->get(route('purchasing.show', $poStage3));
        $res3->assertOk();
        $res3->assertSee(now()->addDays(15)->format('d/m/Y'));
        $res3->assertDontSee('name="confirmation[eta_supplier]"', false);
        $res3->assertDontSee('name="fields[eta_supplier]"', false);
        $res3->assertSee('name="fields[eta]"', false);

        $poStage7 = $this->createSamplePo(PoStage::STAGE_7);
        $poStage7->update(['eta' => $initialEta]);

        $res7 = $this->actingAs($this->purchasing)->get(route('purchasing.show', $poStage7));
        $res7->assertOk();
        $res7->assertSee('aria-label="Edit ETA"', false);
        $res7->assertDontSee('name="shipping[eta_tiba]"', false);

        // Finalized PO hides quick-edit ETA trigger
        $poStage7->update(['status' => 'selesai']);
        $resClosed = $this->actingAs($this->purchasing)->get(route('purchasing.show', $poStage7));
        $resClosed->assertOk();
        $resClosed->assertDontSee('aria-label="Edit ETA"', false);
    }

    public function test_direct_edit_validates_legacy_date_fields_and_avoids_duplicate_pengiriman_eta_logs(): void
    {
        $po = $this->createSamplePo(PoStage::STAGE_7);
        $etaTiba = now()->addDays(5)->toDateString();

        // 1. Invalid eta_supplier fails request validation cleanly (no 500 Carbon exception)
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.direct-edit', $po), [
                'fields' => ['eta_supplier' => 'not-a-date'],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('fields.eta_supplier');

        // 2. Resubmitting unchanged pengiriman.eta_tiba does not duplicate PoLogPerubahan entries
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.direct-edit', $po), [
                'fields' => ['eta_tiba' => $etaTiba],
            ])
            ->assertRedirect();

        $etaTibaLogsBefore = PoLogPerubahan::where('po_id', $po->id)->where('nama_field', 'pengiriman.eta_tiba')->count();

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.direct-edit', $po), [
                'fields' => ['eta_tiba' => $etaTiba, 'no_resi' => 'RESI-999'],
            ])
            ->assertRedirect();

        $this->assertSame(
            $etaTibaLogsBefore,
            PoLogPerubahan::where('po_id', $po->id)->where('nama_field', 'pengiriman.eta_tiba')->count()
        );

        // 3. Direct service call with invalid date throws ValidationException
        $this->expectException(ValidationException::class);
        $this->revisionService->updateOperationalFields($po, ['eta' => 'invalid-date'], $this->purchasing);
    }

    public function test_direct_edit_rejects_finalized_po_statuses(): void
    {
        foreach (['selesai', 'dibatalkan'] as $finalStatus) {
            $po = $this->createSamplePo(PoStage::STAGE_7);
            $po->update([
                'status' => $finalStatus,
                'catatan' => 'Catatan awal',
            ]);

            $this->actingAs($this->purchasing)
                ->post(route('purchasing.direct-edit', $po), [
                    'fields' => [
                        'catatan' => 'Diubah paksa',
                        'eta' => now()->addDays(20)->toDateString(),
                    ],
                ])
                ->assertRedirect()
                ->assertSessionHasErrors('status');

            $po->refresh();
            $this->assertSame('Catatan awal', $po->catatan);
        }
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
        $this->assertSame(PoStage::STAGE_4, $po->stage_aktif);

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
}

