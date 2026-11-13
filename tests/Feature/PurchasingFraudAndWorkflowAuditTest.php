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

    public function test_payment_blocked_while_commitment_revision_pending_then_uses_revised_price_after_approval(): void
    {
        $po = $this->createPo(['skema_bayar' => 'cash', 'stage_aktif' => PoStage::STAGE_4], 1000000, 100);

        $revisi = $this->revisionService->submitCommitmentRevision($po, [
            'items' => [
                ['produk_id' => $this->produk1->id, 'qty' => 100, 'harga_total' => 1500000],
            ],
        ], 'Kenaikan harga supplier 500rb', $this->purchasing);

        $this->actingAs($this->purchasing);

        // 1. UI show tidak boleh menampilkan form pembayaran maupun tombol bayar Stage 4 saat revisi menunggu
        $showRes = $this->get(route('purchasing.show', $po));
        $showRes->assertOk();
        $showRes->assertDontSee('id="form-pembayaran"', false);
        $showRes->assertSee('Pembayaran dikunci', false);

        // 2. DataTable AP tidak boleh menampilkan link Bayar saat revisi menunggu
        $apRes = $this->getJson(route('purchasing.data-ap'));
        $apRes->assertOk();
        $apRow = collect($apRes->json('data'))->firstWhere('id', $po->id);
        $this->assertNotNull($apRow);
        $this->assertStringNotContainsString('#form-pembayaran', (string) ($apRow['action'] ?? ''));

        // 3. HTTP pay dengan harga lama harus ditolak
        $payRes = $this->post(route('purchasing.pay', $po), [
            'skema' => 'cash',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 1000000,
        ]);
        $payRes->assertSessionHasErrors();
        $this->assertEquals(0, Payment::where('po_id', $po->id)->count());

        // 4. Direct service call juga harus menolak
        try {
            $this->paymentService->recordPayment($po, [
                'skema' => 'cash',
                'tanggal_bayar' => now()->toDateString(),
                'nominal' => 1000000,
            ]);
            $this->fail('recordPayment harus menolak saat ada revisi menunggu.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('revisi', $e->errors());
        }

        // 5. Manager approve revisi -> harga aktif berubah ke Rp 1.500.000
        $this->actingAs($this->manager);
        $this->post(route('purchasing.revisi.approve', $revisi))->assertRedirect();
        $po->refresh();
        $this->assertEquals(1500000.0, (float) $po->grand_total);

        // 6. Bayar dengan harga lama Rp 1.000.000 (cash) tetap ditolak karena kurang bayar vs Rp 1.500.000
        $this->actingAs($this->purchasing);
        $oldPricePay = $this->post(route('purchasing.pay', $po), [
            'skema' => 'cash',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 1000000,
        ]);
        $oldPricePay->assertSessionHasErrors('nominal');
        $this->assertEquals(0, Payment::where('po_id', $po->id)->count());

        // 7. Bayar dengan harga hasil revisi Rp 1.500.000 berhasil
        $newPricePay = $this->post(route('purchasing.pay', $po), [
            'skema' => 'cash',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 1500000,
        ]);
        $newPricePay->assertRedirect();
        $newPricePay->assertSessionHas('success');
        $this->assertEquals(1500000.0, (float) $po->fresh()->totalDibayar());
        $this->assertEquals('lunas', $po->fresh()->status_pembayaran);
    }

    public function test_cancelled_po_auto_cancels_pending_revision_and_exits_approval_queue(): void
    {
        $po = $this->createPo(['status' => 'berjalan', 'stage_aktif' => PoStage::STAGE_4], 1000000, 100);
        $revisi = $this->revisionService->submitCommitmentRevision($po, [
            'items' => [
                ['produk_id' => $this->produk1->id, 'qty' => 100, 'harga_total' => 1250000],
            ],
        ], 'Revisi harga sebelum dibatalkan', $this->purchasing);

        $this->actingAs($this->manager);

        // Sebelum dibatalkan: muncul di antrean approval index & datatable
        $beforeIdx = $this->get(route('purchasing.index'));
        $beforeIdx->assertOk();
        $this->assertTrue($beforeIdx->viewData('pendingRevisis')->contains('id', $revisi->id));

        // Batalkan PO melalui route purchasing.cancel
        $cancelRes = $this->post(route('purchasing.cancel', $po), [
            'alasan' => 'PO salah supplier',
        ]);
        $cancelRes->assertRedirect();
        $cancelRes->assertSessionHas('success');

        $po->refresh();
        $revisi->refresh();

        $this->assertEquals('dibatalkan', $po->status);
        $this->assertEquals(PoRevisi::STATUS_DIBATALKAN, $revisi->status);

        // 1. Setelah dibatalkan: tidak boleh muncul lagi di antrean approval index & pending count
        $afterIdx = $this->get(route('purchasing.index'));
        $afterIdx->assertOk();
        $this->assertFalse($afterIdx->viewData('pendingRevisis')->contains('id', $revisi->id));
        $afterIdx->assertDontSee($po->no_po);

        // 2. DataTable approval_queue dan has_pending_revisi tidak boleh menandainya menunggu approval
        $queueRes = $this->getJson(route('purchasing.data', ['approval_queue' => 1]));
        $queueRes->assertOk();
        $this->assertNull(collect($queueRes->json('data'))->firstWhere('id', $po->id));

        $allDataRes = $this->getJson(route('purchasing.data'));
        $allDataRes->assertOk();
        $row = collect($allDataRes->json('data'))->firstWhere('id', $po->id);
        $this->assertNotNull($row);
        $this->assertFalse((bool) ($row['has_pending_revisi'] ?? true));

        // 3. Halaman show tidak boleh lagi menampilkan tombol Setujui / Tolak revisi
        $showRes = $this->get(route('purchasing.show', $po));
        $showRes->assertOk();
        $showRes->assertDontSee('Setujui Revisi');
        $showRes->assertDontSee(route('purchasing.revisi.approve', $revisi), false);

        // 4. Approve / reject revisi pada PO dibatalkan harus ditolak
        $this->post(route('purchasing.revisi.approve', $revisi))->assertSessionHasErrors();
        $this->post(route('purchasing.revisi.reject', $revisi), ['alasan' => 'Tes tolak'])->assertSessionHasErrors();

        // 5. Juga uji pembatalan PO yang sebelumnya di Stage 2 (approval) & Stage 6 (pengemasan)
        $poStage2 = $this->createPo(['status' => 'diajukan', 'stage_aktif' => PoStage::STAGE_2]);
        $this->stageService->cancelPo($poStage2, $this->manager, 'Batal dari Stage 2');

        $afterIdx2 = $this->get(route('purchasing.index'));
        $this->assertEquals($afterIdx->viewData('pendingApprovalCount'), $afterIdx2->viewData('pendingApprovalCount'));
        $afterIdx2->assertDontSee($poStage2->no_po);
        $queueRes2 = $this->getJson(route('purchasing.data', ['approval_queue' => 1]));
        $this->assertNull(collect($queueRes2->json('data'))->firstWhere('id', $poStage2->id));

        $poStage6 = $this->createPo(['status' => 'berjalan', 'stage_aktif' => PoStage::STAGE_6]);
        $revisiStage6 = $this->revisionService->submitCommitmentRevision($poStage6, [
            'items' => [
                ['produk_id' => $this->produk1->id, 'qty' => 90, 'harga_total' => 900000],
            ],
        ], 'Kurangi qty di stage 6', $this->purchasing);
        $this->stageService->cancelPo($poStage6, $this->manager, 'Batal via service');

        $this->assertEquals(PoRevisi::STATUS_DIBATALKAN, $revisiStage6->fresh()->status);

        $dashRes = $this->actingAs($this->purchasing)->get(route('dashboard'));
        $dashRes->assertOk();
        $dashRes->assertDontSee($poStage6->no_po);
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

    public function test_revision_payload_tampering_negative_amounts_and_ui_spoofing_are_blocked(): void
    {
        $po = $this->createPo(['stage_aktif' => PoStage::STAGE_4], 1000000, 100);
        $this->actingAs($this->purchasing);

        // 1. Spoofing grand_total / subtotal_produk agar tampil murah di UI Manager padahal harga item mahal
        $spoofRes = $this->post(route('purchasing.revisi.submit', $po), [
            'alasan' => 'Usulan perubahan harga',
            'data_usulan' => [
                'subtotal_produk' => 100000,
                'grand_total' => 100000,
                'items' => [
                    ['produk_id' => $this->produk1->id, 'qty' => 100, 'harga_total' => 5000000],
                ],
            ],
        ]);
        $spoofRes->assertRedirect();
        $spoofRes->assertSessionHas('success');

        $revisi = PoRevisi::where('po_id', $po->id)->latest('id')->firstOrFail();
        $this->assertEquals(5000000.0, (float) $revisi->data_usulan['subtotal_produk']);
        $this->assertEquals(5000000.0, (float) $revisi->data_usulan['grand_total']);

        $showRes = $this->actingAs($this->manager)->get(route('purchasing.show', $po));
        $showRes->assertOk();
        $showRes->assertSee('5.000.000');

        // Reject agar bisa uji input invalid berikutnya
        $this->post(route('purchasing.revisi.reject', $revisi), ['alasan' => 'Bersihkan revisi test']);

        // 2. Harga negatif / produk asing / qty <= 0 / total netto negatif harus ditolak
        $this->actingAs($this->purchasing);

        $this->post(route('purchasing.revisi.submit', $po), [
            'alasan' => 'Harga item negatif',
            'data_usulan' => [
                'items' => [
                    ['produk_id' => $this->produk1->id, 'qty' => 10, 'harga_total' => -50000],
                ],
            ],
        ])->assertSessionHasErrors();

        $this->post(route('purchasing.revisi.submit', $po), [
            'alasan' => 'Produk tidak ada di PO',
            'data_usulan' => [
                'items' => [
                    ['produk_id' => $this->produk2->id, 'qty' => 10, 'harga_total' => 100000],
                ],
            ],
        ])->assertSessionHasErrors();

        $this->post(route('purchasing.revisi.submit', $po), [
            'alasan' => 'Net total negatif akibat diskon berlebihan',
            'data_usulan' => [
                'diskon_total' => 2000000,
                'items' => [
                    ['produk_id' => $this->produk1->id, 'qty' => 100, 'harga_total' => 1000000],
                ],
            ],
        ])->assertSessionHasErrors();
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
