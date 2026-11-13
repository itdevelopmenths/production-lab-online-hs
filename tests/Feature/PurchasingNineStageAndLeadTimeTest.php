<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\PoLeadTime;
use App\Models\PoStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierLtSummary;
use App\Models\SupplierProdukLtSummary;
use App\Models\User;
use App\Services\Purchasing\PurchasingStageService;
use App\Services\Purchasing\SupplierLeadTimeService;
use App\Services\StokService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchasingNineStageAndLeadTimeTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchasing;
    protected User $manager;
    protected User $gudangUser;
    protected User $finance;
    protected Supplier $supplier;
    protected Gudang $gudang;
    protected Produk $produk1;
    protected Produk $produk2;
    protected PurchasingStageService $stageService;
    protected SupplierLeadTimeService $ltService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $this->finance = User::where('email', 'purchasing@heavenscent.id')->firstOrFail(); // Has pay permission

        $this->supplier = Supplier::active()->firstOrFail();
        $this->gudang = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();

        $produks = Produk::bahan()->active()->take(2)->get();
        $this->produk1 = $produks[0];
        $this->produk2 = $produks[1];

        $this->stageService = app(PurchasingStageService::class);
        $this->ltService = app(SupplierLeadTimeService::class);
    }

    protected function createSamplePo(string $skemaBayar = 'tempo'): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-' . uniqid(),
            'no_invoice' => 'INV-' . uniqid(),
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

    /**
     * TC-01: Happy path siklus lengkap Purchasing 9-stage.
     */
    public function test_tc01_happy_path_full_nine_stage_purchasing_lifecycle(): void
    {
        $t0 = Carbon::now()->subDays(5);
        $po = $this->createSamplePo('tempo');
        $po->stages()->where('stage', PoStage::STAGE_1)->update(['waktu_mulai' => $t0]);

        // Stage 1 -> 2
        $t1 = $t0->copy()->addHours(2);
        $this->stageService->completeStage1($po, $this->purchasing, $t1);
        $this->assertEquals(PoStage::STAGE_2, $po->stage_aktif);

        // Stage 2 -> 3 (Approved by Manager)
        $t2 = $t1->copy()->addHours(3);
        $this->stageService->approveStage2($po, $this->manager, $t2);
        $this->assertEquals(PoStage::STAGE_3, $po->stage_aktif);

        // Stage 3 -> 4 (Konfirmasi Supplier)
        $t3 = $t2->copy()->addHours(4);
        $this->stageService->completeStage3($po, $this->purchasing, [], $t3);
        $this->assertEquals(PoStage::STAGE_4, $po->stage_aktif);

        // Stage 4 -> 5 (Tempo)
        $t4 = $t3->copy()->addHours(1);
        $this->stageService->completeStage4Tempo($po, $this->purchasing, $t4);
        $this->assertEquals(PoStage::STAGE_5, $po->stage_aktif);

        // Stage 5 -> 7 (Terbit PO langsung ke Pengiriman - Alur 8 Tahap Konsolidasi)
        $t5 = $t4->copy()->addHours(2);
        $this->stageService->completeStage5($po, $this->purchasing, $t5);
        $this->assertEquals(PoStage::STAGE_7, $po->stage_aktif);

        // Stage 7 -> 8 (Pengiriman ke Unloading)
        $t7 = $t5->copy()->addHours(24);
        $this->stageService->completeStage7($po, $this->purchasing, ['ekspedisi' => 'JNE Cargo', 'no_resi' => 'JNE123456'], $t7);
        $this->assertEquals(PoStage::STAGE_8, $po->stage_aktif);

        // Stage 8 -> 9 (Unloading di Gudang, belum posting stok)
        $t8 = $t7->copy()->addHours(5);
        $stokAwal1 = app(StokService::class)->saldo($this->produk1->id, $this->gudang->id);
        $this->stageService->completeStage8($po, $this->gudangUser, [
            'kondisi' => 'baik',
            'items' => [
                ['po_item_id' => $po->items[0]->id, 'qty_fisik' => 100],
                ['po_item_id' => $po->items[1]->id, 'qty_fisik' => 50],
            ],
        ], $t8);
        $this->assertEquals(PoStage::STAGE_9, $po->stage_aktif);
        // Stok belum bertambah
        $this->assertEquals($stokAwal1, app(StokService::class)->saldo($this->produk1->id, $this->gudang->id));

        // Stage 9 (Input Sistem & Posting Stok)
        $t9 = $t8->copy()->addHours(2);
        $this->stageService->completeStage9($po, $this->gudangUser, $t9);
        $po->refresh();

        $this->assertEquals('selesai', $po->status);
        // Stok sudah bertambah
        $this->assertEquals($stokAwal1 + 100, app(StokService::class)->saldo($this->produk1->id, $this->gudang->id));

        // Cek pencatatan lead time
        $poLt = PoLeadTime::where('po_id', $po->id)->first();
        $this->assertNotNull($poLt);
        $this->assertTrue($poLt->is_valid);
        $this->assertGreaterThan(0, $poLt->total_lt_menit);
        $this->assertGreaterThan(0, (float) $poLt->total_lt_hari);
    }

    /**
     * TC-02: Transisi stage tidak berurutan ditolak.
     */
    public function test_tc02_out_of_order_stage_transition_is_rejected(): void
    {
        $po = $this->createSamplePo('tempo');
        $this->assertEquals(PoStage::STAGE_1, $po->stage_aktif);

        $this->expectException(ValidationException::class);
        // Mencoba langsung approve Stage 2 saat stage aktif masih Stage 1
        $this->stageService->approveStage2($po, $this->manager);
    }

    /**
     * TC-03: Pencatatan waktu aktual, durasi per stage, dan total lead time.
     */
    public function test_tc03_actual_timestamps_and_duration_calculation(): void
    {
        $start = Carbon::now()->subHours(5);
        $end = Carbon::now()->subHours(2);

        $po = $this->createSamplePo('tempo');
        $po->stages()->where('stage', PoStage::STAGE_1)->update(['waktu_mulai' => $start]);

        $this->stageService->completeStage1($po, $this->purchasing, $end);

        $stage1 = $po->stages()->where('stage', PoStage::STAGE_1)->first();
        $this->assertEquals(180, $stage1->durasi_menit); // 3 jam = 180 menit
    }

    /**
     * TC-04: Validasi waktu selesai tidak boleh sebelum waktu mulai.
     */
    public function test_tc04_waktu_selesai_cannot_be_earlier_than_waktu_mulai(): void
    {
        $start = Carbon::now()->subHours(2);
        $invalidEnd = Carbon::now()->subHours(4);

        $po = $this->createSamplePo('tempo');
        $po->stages()->where('stage', PoStage::STAGE_1)->update(['waktu_mulai' => $start]);

        $this->expectException(ValidationException::class);
        $this->stageService->completeStage1($po, $this->purchasing, $invalidEnd);
    }

    /**
     * TC-05: Waktu aktual tidak boleh melebihi waktu sistem saat pencatatan.
     */
    public function test_tc05_waktu_selesai_cannot_exceed_future_time(): void
    {
        $futureEnd = Carbon::now()->addDays(2);

        $po = $this->createSamplePo('tempo');

        $this->expectException(ValidationException::class);
        $this->stageService->completeStage1($po, $this->purchasing, $futureEnd);
    }

    /**
     * TC-06: Kontinuitas waktu antar stage (Stage N waktu_mulai = Stage N-1 waktu_selesai).
     */
    public function test_tc06_stage_time_continuity(): void
    {
        $t1 = Carbon::now()->subHours(3);
        $po = $this->createSamplePo('tempo');

        $this->stageService->completeStage1($po, $this->purchasing, $t1);

        $stage2 = $po->stages()->where('stage', PoStage::STAGE_2)->first();
        $this->assertEquals($t1->toDateTimeString(), $stage2->waktu_mulai->toDateTimeString());
    }

    /**
     * Test alur 8-tahap: Stage 5 (PO) langsung membuka Stage 7 (Pengiriman), dan Stage 9 dapat menyimpan No. Resi.
     */
    public function test_stage_5_transitions_directly_to_stage_7_and_stage_9_saves_resi(): void
    {
        $po = $this->createSamplePo('tempo');
        $po->stage_aktif = PoStage::STAGE_5;
        $po->save();
        $po->stages()->create([
            'stage' => PoStage::STAGE_5,
            'urutan' => 5,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subHours(2),
        ]);

        $this->stageService->completeStage5($po, $this->purchasing);
        $this->assertEquals(PoStage::STAGE_7, $po->fresh()->stage_aktif);

        $stage7 = $po->stages()->where('stage', PoStage::STAGE_7)->first();
        $this->assertNotNull($stage7);
        $this->assertEquals('berjalan', $stage7->status);

        // Advance to Stage 9
        $this->stageService->completeStage7($po, $this->purchasing, ['ekspedisi' => 'SiCepat Cargo']);
        $this->stageService->completeStage8($po, $this->gudangUser, [
            'gudang_id' => $this->gudang->id,
            'kondisi' => 'baik',
            'items' => [
                $po->items->first()->id => ['po_item_id' => $po->items->first()->id, 'qty_fisik' => 100],
            ],
        ]);
        $this->assertEquals(PoStage::STAGE_9, $po->fresh()->stage_aktif);

        // Complete Stage 9 with new no_resi via HTTP controller
        $this->actingAs($this->gudangUser);
        $res9 = $this->post(route('purchasing.stages.complete', [$po, PoStage::STAGE_9]), [
            'no_resi' => 'RESI-TEST-999',
        ]);
        $res9->assertSessionHasNoErrors();
        $this->assertEquals('RESI-TEST-999', $po->fresh()->pengiriman?->no_resi);
    }

    /**
     * TC-07: Stage Pengemasan (6) dan Pengiriman (7) adalah entitas terpisah.
     */
    public function test_tc07_pengemasan_and_pengiriman_are_separate_stages(): void
    {
        $po = $this->createSamplePo('tempo');
        $po->stage_aktif = PoStage::STAGE_6;
        $po->save();
        $po->stages()->create([
            'stage' => PoStage::STAGE_6,
            'urutan' => 6,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subHours(2),
        ]);

        $this->stageService->completeStage6($po, $this->purchasing, ['jumlah_koli' => 3]);
        $this->assertEquals(PoStage::STAGE_7, $po->stage_aktif);

        $stage6 = $po->stages()->where('stage', PoStage::STAGE_6)->first();
        $stage7 = $po->stages()->where('stage', PoStage::STAGE_7)->first();

        $this->assertNotNull($stage6);
        $this->assertNotNull($stage7);
        $this->assertEquals('selesai', $stage6->status);
        $this->assertEquals('berjalan', $stage7->status);
    }

    /**
     * TC-08: Pengiriman tidak dapat diselesaikan tanpa informasi ekspedisi.
     */
    public function test_tc08_shipping_stage_requires_expedition_info(): void
    {
        $po = $this->createSamplePo('tempo');
        $po->stage_aktif = PoStage::STAGE_7;
        $po->save();
        $po->stages()->create([
            'stage' => PoStage::STAGE_7,
            'urutan' => 7,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subHours(2),
        ]);

        $this->expectException(ValidationException::class);
        $this->stageService->completeStage7($po, $this->purchasing, ['ekspedisi' => '']);
    }

    /**
     * TC-10 & TC-11: Stage 8 (Unloading) mencatat fisik tanpa stok, Stage 9 posting stok.
     */
    public function test_tc10_and_tc11_unloading_and_input_sistem_stock_posting(): void
    {
        $po = $this->createSamplePo('tempo');
        $po->stage_aktif = PoStage::STAGE_8;
        $po->save();
        $po->stages()->create([
            'stage' => PoStage::STAGE_8,
            'urutan' => 8,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subHours(3),
        ]);

        $stokAwal = app(StokService::class)->saldo($this->produk1->id, $this->gudang->id);

        $bardat = $this->stageService->completeStage8($po, $this->gudangUser, [
            'kondisi' => 'baik',
            'items' => [
                ['po_item_id' => $po->items[0]->id, 'qty_fisik' => 100],
            ],
        ], now()->subHours(1));

        $this->assertEquals('belum', $bardat->status_posting);
        $this->assertEquals($stokAwal, app(StokService::class)->saldo($this->produk1->id, $this->gudang->id));

        // Now run Stage 9
        $this->stageService->completeStage9($po, $this->gudangUser, now());
        $bardat->refresh();

        $this->assertEquals('diposting', $bardat->status_posting);
        $this->assertEquals($stokAwal + 100, app(StokService::class)->saldo($this->produk1->id, $this->gudang->id));
    }

    /**
     * Uji unloading dengan kondisi rusak sebagian & associative items key dari form Blade.
     * Memastikan qty_fisik aktual yang rusak sebagian tidak ter-reset ke kuantitas PO saat Stage 9 selesai.
     */
    public function test_stage_8_unloading_partial_damage_with_associative_items_persists_qty_fisik_and_posts_correct_stock_in_stage_9(): void
    {
        $po = $this->createSamplePo('tempo');
        $po->stage_aktif = PoStage::STAGE_8;
        $po->save();
        $po->stages()->create([
            'stage' => PoStage::STAGE_8,
            'urutan' => 8,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subHours(2),
        ]);

        $stokAwal1 = app(StokService::class)->saldo($this->produk1->id, $this->gudang->id);
        $stokAwal2 = app(StokService::class)->saldo($this->produk2->id, $this->gudang->id);

        $item1 = $po->items[0]; // qty = 100
        $item2 = $po->items[1]; // qty = 50

        // Kirim payload dengan struktur associative array persis seperti yang dikirim oleh form HTML Blade
        $this->actingAs($this->gudangUser);
        $response = $this->post(route('purchasing.stages.complete', [$po, PoStage::STAGE_8]), [
            'physical_check' => [
                'kondisi' => 'rusak_sebagian',
                'items' => [
                    $item1->id => [
                        'qty_fisik' => 80, // 20 rusak
                        'keterangan_selisih' => '20 unit rusak dalam perjalanan',
                    ],
                    $item2->id => [
                        'qty_fisik' => 50, // lengkap
                        'keterangan_selisih' => null,
                    ],
                ],
            ],
            'waktu_selesai' => now()->toDateTimeString(),
            'catatan' => 'Unloading fisik dengan 20 unit rusak pada item 1',
        ]);
        $response->assertSessionHasNoErrors();

        $bardat = $po->barangDatang()->where('status_posting', 'belum')->first();
        $this->assertNotNull($bardat);
        $this->assertEquals('rusak_sebagian', $bardat->kondisi);

        $bItem1 = $bardat->items()->where('po_item_id', $item1->id)->firstOrFail();
        $bItem2 = $bardat->items()->where('po_item_id', $item2->id)->firstOrFail();

        $this->assertEquals(80, (float) $bItem1->qty_fisik);
        $this->assertEquals(-20, (float) $bItem1->selisih);
        $this->assertEquals('20 unit rusak dalam perjalanan', $bItem1->keterangan_selisih);

        $this->assertEquals(50, (float) $bItem2->qty_fisik);
        $this->assertEquals(0, (float) $bItem2->selisih);

        // Stage 9: Posting Stok
        $this->assertEquals(PoStage::STAGE_9, $po->fresh()->stage_aktif);

        // Verifikasi angka pada tabel Riwayat Penerimaan & Verifikasi Tahap 9 tampil akurat (tidak 0 saat belum posting) dan tanpa ,00
        $preStage9View = $this->get(route('purchasing.show', $po));
        $preStage9View->assertOk();
        $preStage9View->assertSee('Riwayat Penerimaan Barang di Gudang');
        $preStage9View->assertSee('Verifikasi dan Input Sistem Penerimaan (Tahap 9)');
        $preStage9View->assertSee('-20 (Kurang)');
        $preStage9View->assertSee('-20 (Kurang/Rusak)');
        $preStage9View->assertDontSee('100,00');
        $preStage9View->assertDontSee('80,00');
        $preStage9View->assertDontSee('50,00');
        $preStage9View->assertDontSee('-20,00');

        $preHtml = $preStage9View->getContent();
        $this->assertSame(
            1,
            preg_match('/aria-label="Detail Barang Datang".*?<tbody\b[^>]*>(.*?)<\/tbody>/s', $preHtml, $riwayatBody)
        );
        $this->assertMatchesRegularExpression('/<td[^>]*>\s*80\b/', $riwayatBody[1]);
        $this->assertMatchesRegularExpression('/<td[^>]*>\s*50\b/', $riwayatBody[1]);
        $this->assertDoesNotMatchRegularExpression('/<td[^>]*>\s*0\s*(?:<span[^>]*>[^<]*<\/span>)?\s*<\/td>/', $riwayatBody[1]);

        $response9 = $this->post(route('purchasing.stages.complete', [$po, PoStage::STAGE_9]), [
            'waktu_selesai' => now()->toDateTimeString(),
            'catatan' => 'Posting stok hasil unloading',
        ]);
        $response9->assertSessionHasNoErrors();

        $bardat->refresh();
        $this->assertEquals('diposting', $bardat->status_posting);
        $bItem1->refresh();
        $bItem2->refresh();

        // qty_diterima harus 80 (bukan 100!), dan 50
        $this->assertEquals(80, (float) $bItem1->qty_diterima);
        $this->assertEquals(50, (float) $bItem2->qty_diterima);

        // Stok di gudang hanya bertambah 80 untuk produk 1 (bukan 100!)
        $this->assertEquals($stokAwal1 + 80, app(StokService::class)->saldo($this->produk1->id, $this->gudang->id));
        $this->assertEquals($stokAwal2 + 50, app(StokService::class)->saldo($this->produk2->id, $this->gudang->id));

        // qtyDiterima di PO item harus 80
        $this->assertEquals(80, $item1->fresh()->qtyDiterima());

        // Verifikasi Idempotensi & UI Safety:
        // 1. Tampilan view setelah Stage 9 selesai menyembunyikan panel Tahap 9 (karena data sudah ada di Riwayat Penerimaan) dan tanpa form submit posting ulang
        $viewResponse = $this->get(route('purchasing.show', $po));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Riwayat Penerimaan Barang di Gudang');
        $viewResponse->assertSee('Stok Diposting');
        $viewResponse->assertSee('-20 (Kurang)');
        $viewResponse->assertDontSee('100,00');
        $viewResponse->assertDontSee('80,00');
        $viewResponse->assertDontSee('-20,00');
        $viewResponse->assertDontSee('Verifikasi dan Input Sistem Penerimaan (Tahap 9)');
        $viewResponse->assertDontSee('Audit Penerimaan dan Posting Stok (Tahap 9 - Selesai)');
        $viewResponse->assertDontSee('Posting Stok ke Gudang & Selesaikan PO');

        // 2. Jika route completeStage9 dipanggil ulang (double-submission), sistem menolak secara elegan
        $repeatResponse = $this->post(route('purchasing.stages.complete', [$po, PoStage::STAGE_9]), [
            'waktu_selesai' => now()->toDateTimeString(),
        ]);
        $repeatResponse->assertSessionHasErrors('stage');
        $this->assertStringContainsString('telah berstatus Selesai', session('errors')->first('stage'));
    }

    /**
     * TC-12: Reject pada Stage 2 mencatat riwayat putaran.
     */
    public function test_tc12_stage_2_rejection_creates_new_round_from_stage_1(): void
    {
        $po = $this->createSamplePo('tempo');
        $this->stageService->completeStage1($po, $this->purchasing, now()->subHours(4));
        $this->assertEquals(PoStage::STAGE_2, $po->stage_aktif);

        $this->stageService->rejectStage2($po, $this->manager, 'Harga bahan terlalu tinggi');
        $po->refresh();

        $this->assertEquals(PoStage::STAGE_1, $po->stage_aktif);

        $rejectedStage2 = $po->stages()->where('stage', PoStage::STAGE_2)->first();
        $this->assertEquals('dikembalikan', $rejectedStage2->status);
        $this->assertEquals('Harga bahan terlalu tinggi', $rejectedStage2->catatan);

        $newStage1 = $po->stages()->where('stage', PoStage::STAGE_1)->where('putaran', 2)->first();
        $this->assertNotNull($newStage1);
        $this->assertEquals('berjalan', $newStage1->status);
    }

    /**
     * TC-13: Perhitungan rata-rata lead time 1-desimal half-up.
     */
    public function test_tc13_lead_time_days_precision_1_decimal_half_up(): void
    {
        // 1440 menit = 1.0 hari
        $this->assertEquals(1.0, SupplierLeadTimeService::minutesToDays(1440));
        
        // 2088 menit / 1440 = 1.45 hari -> round half-up digit ke-2 >= 5 => 1.5 hari
        $this->assertEquals(1.5, SupplierLeadTimeService::minutesToDays(2088));

        // 2073 menit / 1440 = 1.439 hari -> round digit ke-2 < 5 => 1.4 hari
        $this->assertEquals(1.4, SupplierLeadTimeService::minutesToDays(2073));
    }

    /**
     * TC-14: Invalidasi data lead time oleh Manager.
     */
    public function test_tc14_manager_can_invalidate_lead_time_transaction(): void
    {
        $po = $this->createSamplePo('tempo');
        $po->stage_aktif = PoStage::STAGE_9;
        $po->save();
        $po->stages()->create([
            'stage' => PoStage::STAGE_9,
            'urutan' => 9,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subHours(1),
        ]);

        $bardat = $po->barangDatang()->create([
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'status_posting' => 'belum',
            'created_by' => $this->gudangUser->id,
        ]);
        $bardat->items()->create([
            'po_item_id' => $po->items[0]->id,
            'qty_fisik' => 100,
        ]);

        $this->stageService->completeStage9($po, $this->gudangUser, now());

        $poLt = PoLeadTime::where('po_id', $po->id)->firstOrFail();
        $this->assertTrue($poLt->is_valid);

        $summaryBefore = SupplierLtSummary::where('supplier_id', $this->supplier->id)->first();
        $this->assertNotNull($summaryBefore);
        $this->assertGreaterThan(0, $summaryBefore->jumlah_transaksi);

        // Manager invalidates
        $this->ltService->invalidateTransaction($poLt, $this->manager, 'Ekspedisi mogok luar biasa (anomali force majeure)');

        $poLt->refresh();
        $this->assertFalse($poLt->is_valid);
        $this->assertEquals('Ekspedisi mogok luar biasa (anomali force majeure)', $poLt->alasan_tidak_valid);

        $summaryAfter = SupplierLtSummary::where('supplier_id', $this->supplier->id)->first();
        $this->assertEquals($summaryBefore->jumlah_transaksi - 1, $summaryAfter->jumlah_transaksi);
    }

    /**
     * TC-15: Pembatalan PO oleh Manager sebelum Stage 8.
     */
    public function test_tc15_manager_can_cancel_po_before_stage_8(): void
    {
        $po = $this->createSamplePo('tempo');
        $this->stageService->completeStage1($po, $this->purchasing, now()->subHours(3));

        $this->stageService->cancelPo($po, $this->manager, 'Vendor kehabisan stok bahan mentah');
        $po->refresh();

        $this->assertEquals('dibatalkan', $po->status);
        $this->assertEquals('Vendor kehabisan stok bahan mentah', $po->alasan_batal);
        $this->assertEquals($this->manager->id, $po->dibatalkan_oleh);
    }

    /**
     * TC-26 & TC-27: Stage 4 via 'Dibayarkan' (DP 30% dan validasi minimal 1 payment).
     */
    public function test_tc26_and_tc27_stage_4_dibayarkan_with_partial_payment(): void
    {
        $po = $this->createSamplePo('termin');
        $po->stage_aktif = PoStage::STAGE_4;
        $po->save();
        $po->stages()->create([
            'stage' => PoStage::STAGE_4,
            'urutan' => 4,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subHours(2),
        ]);

        // TC-27: Tanpa payment harus gagal
        try {
            $this->stageService->completeStage4WithPayment($po, $this->purchasing);
            $this->fail('Harusnya melempar ValidationException');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        // Catat DP 30% (300.000 dari 1.000.000)
        $po->payments()->create([
            'skema' => 'termin',
            'nominal' => 300000,
            'tanggal_bayar' => now()->toDateString(),
        ]);
        $po->refreshStatusPembayaran();
        $po->save();

        $this->assertEquals('parsial', $po->status_pembayaran);

        // TC-26: Tekan Dibayarkan -> Berhasil lanjut ke Stage 5 meski belum lunas
        $this->stageService->completeStage4WithPayment($po, $this->purchasing);
        $po->refresh();

        $this->assertEquals(PoStage::STAGE_5, $po->stage_aktif);
        $this->assertNotEquals('lunas', $po->status_pembayaran);
    }

    /**
     * TC-28: Stage 4 via 'Lanjut Tempo' hanya untuk skema tempo tanpa payment.
     */
    public function test_tc28_stage_4_lanjut_tempo_without_payment(): void
    {
        $po = $this->createSamplePo('tempo');
        $po->stage_aktif = PoStage::STAGE_4;
        $po->save();
        $po->stages()->create([
            'stage' => PoStage::STAGE_4,
            'urutan' => 4,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subHours(2),
        ]);

        $this->stageService->completeStage4Tempo($po, $this->purchasing);
        $po->refresh();

        $this->assertEquals(PoStage::STAGE_5, $po->stage_aktif);
        $this->assertEquals(0, $po->payments()->count());
    }

    /**
     * TC-30: Dual-level lead time calculation (Supplier level vs Supplier-Produk level).
     */
    public function test_tc30_dual_level_lead_time_updates_both_levels(): void
    {
        $po = $this->createSamplePo('tempo');
        $po->stage_aktif = PoStage::STAGE_9;
        $po->save();
        $po->stages()->create([
            'stage' => PoStage::STAGE_9,
            'urutan' => 9,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subHours(1),
        ]);

        $bardat = $po->barangDatang()->create([
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'status_posting' => 'belum',
            'created_by' => $this->gudangUser->id,
        ]);
        $bardat->items()->create(['po_item_id' => $po->items[0]->id, 'qty_fisik' => 100]);
        $bardat->items()->create(['po_item_id' => $po->items[1]->id, 'qty_fisik' => 50]);

        $this->stageService->completeStage9($po, $this->gudangUser, now());

        // Level 1: Supplier level
        $sSum = SupplierLtSummary::where('supplier_id', $this->supplier->id)->first();
        $this->assertNotNull($sSum);
        $this->assertGreaterThan(0, $sSum->jumlah_transaksi);

        // Level 2: Supplier-Produk level (keduanya bertambah)
        $sp1 = SupplierProdukLtSummary::where('supplier_id', $this->supplier->id)
            ->where('produk_id', $this->produk1->id)
            ->first();
        $sp2 = SupplierProdukLtSummary::where('supplier_id', $this->supplier->id)
            ->where('produk_id', $this->produk2->id)
            ->first();

        $this->assertNotNull($sp1);
        $this->assertNotNull($sp2);
        $this->assertGreaterThan(0, $sp1->jumlah_transaksi);
        $this->assertGreaterThan(0, $sp2->jumlah_transaksi);
    }

    public function test_completed_po_keeps_hr_duration_instead_of_selesai_in_sla_column(): void
    {
        // 1. PO selesai via 9-stage (2.5 hari = 3600 menit)
        $poStaged = $this->createSamplePo('tempo');
        $poStaged->stage_aktif = PoStage::STAGE_9;
        $poStaged->save();
        $poStaged->stages()->create([
            'stage' => PoStage::STAGE_9,
            'urutan' => 9,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subMinutes(3600),
        ]);

        $bardat1 = $poStaged->barangDatang()->create([
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'status_posting' => 'belum',
            'created_by' => $this->gudangUser->id,
        ]);
        $bardat1->items()->create(['po_item_id' => $poStaged->items[0]->id, 'qty_fisik' => 100]);
        $bardat1->items()->create(['po_item_id' => $poStaged->items[1]->id, 'qty_fisik' => 50]);

        $this->stageService->completeStage9($poStaged, $this->gudangUser, now());

        // 2. PO selesai lebih lama (5 hari = 7200 menit) pada supplier yang sama -> melewati Avg supplier
        $poLate = $this->createSamplePo('tempo');
        $poLate->stage_aktif = PoStage::STAGE_9;
        $poLate->save();
        $poLate->stages()->create([
            'stage' => PoStage::STAGE_9,
            'urutan' => 9,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subMinutes(7200),
        ]);

        $bardat2 = $poLate->barangDatang()->create([
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'status_posting' => 'belum',
            'created_by' => $this->gudangUser->id,
        ]);
        $bardat2->items()->create(['po_item_id' => $poLate->items[0]->id, 'qty_fisik' => 100]);
        $bardat2->items()->create(['po_item_id' => $poLate->items[1]->id, 'qty_fisik' => 50]);

        $this->stageService->completeStage9($poLate, $this->gudangUser, now());

        // 3. PO legacy / langsung selesai tanpa PoLeadTime maupun stage durasi (selisih tanggal PO ke tanggal tiba = 3 hari)
        $poLegacy = PurchaseOrder::create([
            'no_po' => 'PO-LEGACY-' . uniqid(),
            'no_invoice' => 'INV-LEGACY-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudang->id,
            'tanggal' => now()->subDays(3)->toDateString(),
            'skema_bayar' => 'cash',
            'status' => 'selesai',
            'subtotal_produk' => 100000,
            'grand_total' => 100000,
            'created_by' => $this->purchasing->id,
        ]);
        $poLegacy->barangDatang()->create([
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'created_by' => $this->gudangUser->id,
        ]);

        $this->actingAs($this->manager);
        $res = $this->getJson(route('purchasing.data'));
        $res->assertOk();

        $rows = collect($res->json('data'));
        $rowStaged = $rows->firstWhere('no_po', $poStaged->no_po);
        $rowLate = $rows->firstWhere('no_po', $poLate->no_po);
        $rowLegacy = $rows->firstWhere('no_po', $poLegacy->no_po);

        $this->assertNotNull($rowStaged);
        $this->assertNotSame('Selesai', $rowStaged['sla']);
        $this->assertStringContainsString('2.5 hr', $rowStaged['sla']);

        $this->assertNotNull($rowLate);
        $this->assertNotSame('Selesai', $rowLate['sla']);
        $this->assertStringContainsString('⚠️ 5 hr', $rowLate['sla']);

        $this->assertNotNull($rowLegacy);
        $this->assertNotSame('Selesai', $rowLegacy['sla']);
        $this->assertStringContainsString('3 hr', $rowLegacy['sla']);
    }
}

