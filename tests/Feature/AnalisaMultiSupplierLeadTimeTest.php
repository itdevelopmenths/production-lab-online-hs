<?php

namespace Tests\Feature;

use App\Models\AnalisaLokal;
use App\Models\AnalisaImpor;
use App\Models\Gudang;
use App\Models\LeadTimeLokal;
use App\Models\LeadTimeLokalStage;
use App\Models\Produk;
use App\Models\ProdukSupplier;
use App\Models\Supplier;
use App\Models\SupplierProdukLtSummary;
use App\Models\User;
use App\Services\AnalisaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalisaMultiSupplierLeadTimeTest extends TestCase
{
    use RefreshDatabase;

    private User $purchasing;
    private User $manager;
    private Produk $produkLokal;
    private Produk $produkImpor;
    private Supplier $supplierA;
    private Supplier $supplierB;
    private AnalisaService $analisaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->purchasing = User::factory()->create(['name' => 'Staff Purchasing']);
        $this->purchasing->assignRole('purchasing');

        $this->manager = User::factory()->create(['name' => 'Manager']);
        $this->manager->assignRole('manager');

        $this->supplierA = Supplier::create([
            'nama' => 'Supplier Alpha Indo',
            'kode' => 'SUP-ALP',
            'kategori' => 'lokal',
            'is_active' => true,
        ]);

        $this->supplierB = Supplier::create([
            'nama' => 'Supplier Beta Global',
            'kode' => 'SUP-BET',
            'kategori' => 'lokal',
            'is_active' => true,
        ]);

        $this->produkLokal = Produk::create([
            'nama' => 'Bahan Ekstrak Mawar',
            'sku' => 'BB-MAWAR',
            'tipe' => 'bahan',
            'profil_analisa' => 'lokal',
            'satuan' => 'Liter',
            'supplier_id' => $this->supplierA->id,
            'is_active' => true,
        ]);

        $this->produkImpor = Produk::create([
            'nama' => 'Bahan Kimia Fixative',
            'sku' => 'BB-FIX',
            'tipe' => 'bahan',
            'profil_analisa' => 'bahan_impor',
            'satuan' => 'Kg',
            'supplier_id' => $this->supplierA->id,
            'is_active' => true,
        ]);

        // Link product to suppliers
        ProdukSupplier::updateOrCreate(
            ['produk_id' => $this->produkLokal->id, 'supplier_id' => $this->supplierA->id],
            ['harga_terakhir' => 150000, 'min_order_qty' => 10, 'status' => 'aktif']
        );

        ProdukSupplier::updateOrCreate(
            ['produk_id' => $this->produkLokal->id, 'supplier_id' => $this->supplierB->id],
            ['harga_terakhir' => 140000, 'min_order_qty' => 25, 'status' => 'aktif']
        );

        $this->analisaService = app(AnalisaService::class);
    }

    /**
     * TC-16: Resolusi Lead Time Supplier-Produk
     * Jika ada histori transaksi PO >= 1 di summary, gunakan data otomatis.
     * Jika tidak ada histori, fallback ke konfigurasi manual.
     */
    public function test_resolve_supplier_produk_lead_time_with_empirical_summary_and_fallback(): void
    {
        // 1. Setting manual default pada stage
        LeadTimeLokalStage::create([
            'produk_id' => $this->produkLokal->id,
            'skenario' => 'average',
            'perencanaan' => 1.0,
            'approval' => 1.0,
            'supplier_confirm' => 1.0,
            'payment' => 1.0,
            'po' => 1.0,
            'pengemasan' => 2.0,
            'pengiriman' => 3.0,
            'unloading' => 1.0,
            'input' => 1.0, // total = 13.0 hari
        ]);

        LeadTimeLokalStage::create([
            'produk_id' => $this->produkLokal->id,
            'skenario' => 'max',
            'perencanaan' => 2.0,
            'approval' => 2.0,
            'supplier_confirm' => 2.0,
            'payment' => 2.0,
            'po' => 2.0,
            'pengemasan' => 4.0,
            'pengiriman' => 5.0,
            'unloading' => 2.0,
            'input' => 2.0, // total = 23.0 hari
        ]);

        // Supplier B belum ada PO -> harus fallback ke manual (12.0 avg, 23.0 max)
        $resB = $this->analisaService->resolveSupplierProdukLeadTimeLokal($this->produkLokal->id, $this->supplierB->id);
        $this->assertEquals('manual', $resB['sumber_lt']);
        $this->assertEquals(12.0, (float) $resB['total_average_lead_time']);
        $this->assertEquals(23.0, (float) $resB['total_max_lead_time']);
        $this->assertEquals(11.0, (float) $resB['safety_stock']); // 23 - 12 = 11

        // 2. Buat ringkasan empiris otomatis untuk Supplier A (e.g. 7.4 hari avg, 11.8 hari max)
        SupplierProdukLtSummary::create([
            'supplier_id' => $this->supplierA->id,
            'produk_id' => $this->produkLokal->id,
            'total_po_selesai' => 3,
            'average_lt_days' => 7.4,
            'max_lt_days' => 11.8,
            'last_calculated_at' => now(),
        ]);

        $resA = $this->analisaService->resolveSupplierProdukLeadTimeLokal($this->produkLokal->id, $this->supplierA->id);
        $this->assertEquals('otomatis', $resA['sumber_lt']);
        $this->assertEquals(7.4, (float) $resA['total_average_lead_time']);
        $this->assertEquals(11.8, (float) $resA['total_max_lead_time']);
        $this->assertEquals(4.4, (float) $resA['safety_stock']); // 11.8 - 7.4 = 4.4
    }

    /**
     * TC-17: Presisi Lead Time Hari 1 Desimal & Pembulatan Half-Up
     * < 5 dibulatkan ke 0, >= 5 dibulatkan ke +0.1
     */
    public function test_lead_time_days_precision_one_decimal_half_up(): void
    {
        $this->assertEquals(5.3, AnalisaService::roundLeadTimeDays(5.34));
        $this->assertEquals(5.4, AnalisaService::roundLeadTimeDays(5.35));
        $this->assertEquals(10.0, AnalisaService::roundLeadTimeDays(9.95));
        $this->assertEquals(0.1, AnalisaService::roundLeadTimeDays(0.05));
        $this->assertEquals(0.0, AnalisaService::roundLeadTimeDays(0.04));
    }

    /**
     * TC-31: Strictly 1 Active Row per Produk ID saat Switching Supplier
     * Saat user mengganti supplier terpilih pada analisa, baris analisa di-update (bukan diduplikasi).
     */
    public function test_switching_supplier_maintains_single_active_row_per_product(): void
    {
        // 1. Generate analisa awal untuk produk lokal
        $this->analisaService->generateLokal($this->produkLokal->id, $this->manager->id);

        $initialRowsCount = AnalisaLokal::where('produk_id', $this->produkLokal->id)->count();
        $this->assertEquals(1, $initialRowsCount);

        $analisaRow = AnalisaLokal::where('produk_id', $this->produkLokal->id)->first();
        $this->assertEquals($this->supplierA->id, $analisaRow->supplier_terpilih_id);

        // 2. Ganti supplier terpilih ke Supplier B via service / API
        $this->actingAs($this->manager)->postJson(route('analisa.select-supplier'), [
            'produk_id' => $this->produkLokal->id,
            'supplier_id' => $this->supplierB->id,
        ])->assertOk();

        // 3. Verifikasi: jumlah baris pada analisa_lokal untuk produk ini TETAP PERSIS 1
        $afterSwitchCount = AnalisaLokal::where('produk_id', $this->produkLokal->id)->count();
        $this->assertEquals(1, $afterSwitchCount);

        $freshRow = AnalisaLokal::where('produk_id', $this->produkLokal->id)->first();
        $this->assertEquals($this->supplierB->id, $freshRow->supplier_terpilih_id);

        // 4. Test vendor comparison endpoint
        $compareResponse = $this->actingAs($this->purchasing)->getJson(route('analisa.compare-suppliers', $this->produkLokal));
        $compareResponse->assertOk();
        $suppliersList = $compareResponse->json('suppliers');
        $this->assertCount(2, $suppliersList);

        $currentSupplierItem = collect($suppliersList)->firstWhere('is_current', true);
        $this->assertEquals($this->supplierB->id, $currentSupplierItem['supplier_id']);
    }

    /**
     * Data 9 tahap empiris hasil Purchasing (SupplierProdukLtStageSummary) harus muncul di Tab 4
     * Rincian 9 Tahap Lead Time untuk supplier terpilih, dan tetap fallback ke LeadTimeLokalStage manual
     * saat supplier belum memiliki histori PO.
     */
    public function test_lokal_data_stages_uses_empirical_purchasing_stage_summary_and_fallbacks_to_manual(): void
    {
        LeadTimeLokalStage::create([
            'produk_id' => $this->produkLokal->id,
            'skenario' => 'average',
            'perencanaan' => 1.0,
            'approval' => 1.0,
            'supplier_confirm' => 1.0,
            'payment' => 1.0,
            'po' => 1.0,
            'pengemasan' => 2.0,
            'pengiriman' => 3.0,
            'unloading' => 1.0,
            'input' => 1.0, // total = 12.0
        ]);

        LeadTimeLokalStage::create([
            'produk_id' => $this->produkLokal->id,
            'skenario' => 'max',
            'perencanaan' => 2.0,
            'approval' => 2.0,
            'supplier_confirm' => 2.0,
            'payment' => 2.0,
            'po' => 2.0,
            'pengemasan' => 4.0,
            'pengiriman' => 5.0,
            'unloading' => 2.0,
            'input' => 2.0, // total = 23.0
        ]);

        $this->analisaService->generateLokal($this->produkLokal->id, $this->manager->id);

        // Catat ringkasan empiris 9 tahap dari Purchasing untuk Supplier A
        $stageValues = [
            \App\Models\PoStage::STAGE_1 => [1.0, 1.5],
            \App\Models\PoStage::STAGE_2 => [0.5, 1.0],
            \App\Models\PoStage::STAGE_3 => [2.0, 3.0],
            \App\Models\PoStage::STAGE_4 => [1.0, 1.5],
            \App\Models\PoStage::STAGE_5 => [0.5, 1.0],
            \App\Models\PoStage::LEGACY_STAGE_PENGEMASAN => [3.0, 4.0],
            \App\Models\PoStage::STAGE_6 => [2.0, 3.0],
            \App\Models\PoStage::STAGE_7 => [0.5, 1.0],
            \App\Models\PoStage::STAGE_8 => [0.5, 1.0],
        ];
        foreach ($stageValues as $stageKey => [$avgDays, $maxDays]) {
            \App\Models\SupplierProdukLtStageSummary::create([
                'supplier_id' => $this->supplierA->id,
                'produk_id' => $this->produkLokal->id,
                'stage' => $stageKey,
                'jumlah_transaksi' => 2,
                'total_menit' => (int) round($avgDays * 1440 * 2),
                'average_hari' => $avgDays,
                'max_hari' => $maxDays,
            ]);
        }

        $resA = $this->actingAs($this->purchasing)->getJson(route('analisa.lokal.data'));
        $resA->assertOk();
        $rowA = collect($resA->json('tables.stages'))->firstWhere('produk_id', $this->produkLokal->id);

        $this->assertNotNull($rowA);
        $this->assertSame('otomatis', $rowA['sumber_lt']);
        $this->assertSame(2, $rowA['jumlah_transaksi']);
        $this->assertEquals($this->supplierA->id, $rowA['supplier_id']);
        $this->assertEquals(1.0, $rowA['average']['perencanaan']);
        $this->assertEquals(0.5, $rowA['average']['approval']);
        $this->assertEquals(2.0, $rowA['average']['supplier_confirm']);
        $this->assertEquals(1.0, $rowA['average']['payment']);
        $this->assertEquals(0.5, $rowA['average']['po']);
        $this->assertEquals(3.0, $rowA['average']['pengemasan']);
        $this->assertEquals(2.0, $rowA['average']['pengiriman']);
        $this->assertEquals(0.5, $rowA['average']['unloading']);
        $this->assertEquals(0.5, $rowA['average']['input']);
        $this->assertEquals(11.0, $rowA['average']['total']);
        $this->assertEquals(17.0, $rowA['max']['total']);

        // Pindah supplier ke Supplier B (0 PO) -> harus fallback ke data manual 12.0 & 23.0 tanpa ter-overwrite
        $this->actingAs($this->manager)->postJson(route('analisa.select-supplier'), [
            'produk_id' => $this->produkLokal->id,
            'supplier_id' => $this->supplierB->id,
        ])->assertOk();

        $resB = $this->actingAs($this->purchasing)->getJson(route('analisa.lokal.data'));
        $resB->assertOk();
        $rowB = collect($resB->json('tables.stages'))->firstWhere('produk_id', $this->produkLokal->id);

        $this->assertNotNull($rowB);
        $this->assertSame('manual', $rowB['sumber_lt']);
        $this->assertEquals($this->supplierB->id, $rowB['supplier_id']);
        $this->assertEquals($this->supplierB->nama, $rowB['supplier_nama']);
        $this->assertEquals(12.0, $rowB['average']['total']);
        $this->assertEquals(23.0, $rowB['max']['total']);
    }

    /**
     * Penyelesaian Stage 9 pada Purchasing (completeStage9) dan invalidasi PO oleh Manager
     * harus otomatis memperbarui working analisa yang sudah ada termasuk rincian 9 tahap di Tab 4.
     */
    public function test_purchasing_stage9_completion_and_invalidation_refresh_analisa_stages(): void
    {
        $gudang = Gudang::create([
            'kode' => 'GD-LT-01',
            'nama' => 'Gudang Bahan Utama',
            'tipe' => 'bahan_baku',
            'is_active' => true,
        ]);
        $gudangUser = User::factory()->create(['name' => 'Staff Gudang']);
        $gudangUser->assignRole('gudang');

        // Produk lokal belum punya manual LeadTimeLokalStage, working table analisa sudah ter-generate
        $this->analisaService->generateLokal($this->produkLokal->id, $this->manager->id);

        $beforeRes = $this->actingAs($this->purchasing)->getJson(route('analisa.lokal.data'));
        $beforeRow = collect($beforeRes->json('tables.stages'))->firstWhere('produk_id', $this->produkLokal->id);
        $this->assertNotNull($beforeRow);
        $this->assertSame('manual', $beforeRow['sumber_lt']);

        // Jalankan siklus lengkap 9 tahap Purchasing dengan durasi terukur
        $stageService = app(\App\Services\Purchasing\PurchasingStageService::class);
        $ltService = app(\App\Services\Purchasing\SupplierLeadTimeService::class);

        $t0 = now()->subDays(12);
        $po = \App\Models\PurchaseOrder::create([
            'no_po' => 'PO-LT-AUTO-01',
            'no_invoice' => 'INV-LT-AUTO-01',
            'supplier_id' => $this->supplierA->id,
            'gudang_id' => $gudang->id,
            'tanggal' => $t0->toDateString(),
            'skema_bayar' => 'tempo',
            'status' => 'draft',
            'subtotal_produk' => 500000,
            'grand_total' => 500000,
            'created_by' => $this->purchasing->id,
        ]);
        $poItem = $po->items()->create([
            'produk_id' => $this->produkLokal->id,
            'qty' => 10,
            'harga_total' => 500000,
            'hpp_per_satuan' => 50000,
        ]);

        $stageService->initializePoStages($po, $t0, $this->purchasing);
        $t1 = $t0->copy()->addHours(24); // stage 1 = 1.0 hari
        $stageService->completeStage1($po, $this->purchasing, $t1);
        $t2 = $t1->copy()->addHours(12); // stage 2 = 0.5 hari
        $stageService->approveStage2($po, $this->manager, $t2);
        $t3 = $t2->copy()->addHours(24); // stage 3 = 1.0 hari
        $stageService->completeStage3($po, $this->purchasing, [], $t3);
        $t4 = $t3->copy()->addHours(12); // stage 4 = 0.5 hari
        $stageService->completeStage4Tempo($po, $this->purchasing, $t4);
        $t5 = $t4->copy()->addHours(12); // stage 5 = 0.5 hari
        $stageService->completeStage5($po, $this->purchasing, $t5);
        $t6 = $t5->copy()->addHours(72); // stage 6 (pengiriman) = 3.0 hari
        $stageService->completeStage6($po, $this->purchasing, ['ekspedisi' => 'JNE', 'no_resi' => 'RESI-01', 'jumlah_koli' => 1, 'berat_kg' => 10], $t6);
        $t7 = $t6->copy()->addHours(12); // stage 7 (unloading) = 0.5 hari
        $stageService->completeStage7($po, $gudangUser, [
            'kondisi' => 'baik',
            'items' => [['po_item_id' => $poItem->id, 'qty_fisik' => 10]],
        ], $t7);
        $t8 = $t7->copy()->addHours(12); // stage 8 (input sistem) = 0.5 hari
        $stageService->completeStage8($po, $gudangUser, $t8);

        // Tanpa memanggil generateLokal secara manual lagi, data analisa & Tab 4 otomatis menjadi 'otomatis'
        $analisaRow = AnalisaLokal::where('produk_id', $this->produkLokal->id)->firstOrFail();
        $this->assertSame('otomatis', $analisaRow->sumber_lt);
        $this->assertEquals(7.5, (float) $analisaRow->total_average_lead_time);

        $afterRes = $this->actingAs($this->purchasing)->getJson(route('analisa.lokal.data'));
        $afterRes->assertOk();
        $afterRow = collect($afterRes->json('tables.stages'))->firstWhere('produk_id', $this->produkLokal->id);
        $this->assertNotNull($afterRow);
        $this->assertSame('otomatis', $afterRow['sumber_lt']);
        $this->assertEquals(1.0, $afterRow['average']['perencanaan']);
        $this->assertEquals(0.5, $afterRow['average']['approval']);
        $this->assertEquals(1.0, $afterRow['average']['supplier_confirm']);
        $this->assertEquals(0.5, $afterRow['average']['payment']);
        $this->assertEquals(0.5, $afterRow['average']['po']);
        $this->assertEquals(0.0, $afterRow['average']['pengemasan']);
        $this->assertEquals(3.0, $afterRow['average']['pengiriman']);
        $this->assertEquals(0.5, $afterRow['average']['unloading']);
        $this->assertEquals(0.5, $afterRow['average']['input']);
        $this->assertEquals(7.5, $afterRow['average']['total']);

        // Saat Manager menginvalidasi PoLeadTime (jumlah_transaksi turun menjadi 0), Tab 4 harus kembali ke 'manual'
        $poLt = \App\Models\PoLeadTime::where('po_id', $po->id)->firstOrFail();
        $ltService->invalidateTransaction($poLt, $this->manager, 'Data uji tidak valid');

        $invalidRes = $this->actingAs($this->purchasing)->getJson(route('analisa.lokal.data'));
        $invalidRow = collect($invalidRes->json('tables.stages'))->firstWhere('produk_id', $this->produkLokal->id);
        $this->assertNotNull($invalidRow);
        $this->assertSame('manual', $invalidRow['sumber_lt']);
    }

    /**
     * Opsi 1 (Lokal): Tab Working Data ADU menampilkan seluruh kombinasi supplier-produk
     * (termasuk supplier yang pernah menyuplai via histori PO) dengan ADU murni per produk
     * dan Lead Time / Safety Stock / Batas Minimum / Target Stock masing-masing supplier,
     * sementara Tab Rekomendasi Order tetap 1 baris aktif per produk.
     */
    public function test_lokal_adu_table_lists_all_supplier_product_pairs_with_supplier_independent_adu(): void
    {
        $supplierC = Supplier::create([
            'nama' => 'PT C Histori Saja',
            'kode' => 'SUP-C',
            'kategori' => 'lokal',
            'is_active' => true,
        ]);

        // Input pemakaian produk: 3.000 / 30 = ADU 100 per hari
        \App\Models\AnalisaLokalInput::create([
            'produk_id' => $this->produkLokal->id,
            'terjual_rata_rata_4bulan' => 3000,
            'review_period' => 15,
        ]);
        LeadTimeLokal::create([
            'produk_id' => $this->produkLokal->id,
            'total_average_lead_time' => 5.0,
            'total_max_lead_time' => 7.0,
            'tambahan_buffer_hari' => 2.0,
            'safety_stock' => 4.0,
        ]);

        // PT A (aktif, 5 avg / 7 max -> SS 4), PT B (aktif, 8 avg / 12 max -> SS 6), PT C (hanya histori PO, 10 avg / 13 max -> SS 5)
        SupplierProdukLtSummary::create([
            'supplier_id' => $this->supplierA->id,
            'produk_id' => $this->produkLokal->id,
            'jumlah_transaksi' => 2,
            'average_lt_hari' => 5.0,
            'max_lt_hari' => 7.0,
            'diperbarui_pada' => now(),
        ]);
        SupplierProdukLtSummary::create([
            'supplier_id' => $this->supplierB->id,
            'produk_id' => $this->produkLokal->id,
            'jumlah_transaksi' => 3,
            'average_lt_hari' => 8.0,
            'max_lt_hari' => 12.0,
            'diperbarui_pada' => now(),
        ]);
        SupplierProdukLtSummary::create([
            'supplier_id' => $supplierC->id,
            'produk_id' => $this->produkLokal->id,
            'jumlah_transaksi' => 1,
            'average_lt_hari' => 10.0,
            'max_lt_hari' => 13.0,
            'diperbarui_pada' => now(),
        ]);

        $this->analisaService->generateLokal($this->produkLokal->id, $this->manager->id);

        $res = $this->actingAs($this->purchasing)->getJson(route('analisa.lokal.data'));
        $res->assertOk();

        // Tab 1 Rekomendasi tetap 1 baris untuk produk ini (supplier aktif = PT A)
        $rekRows = collect($res->json('data'))->where('produk_id', $this->produkLokal->id);
        $this->assertCount(1, $rekRows);

        // Tab 2 Working Data ADU menampilkan 3 baris (PT A, PT B, PT C)
        $aduRows = collect($res->json('tables.analisa'))->where('produk_id', $this->produkLokal->id)->values();
        $this->assertCount(3, $aduRows);

        $rowA = $aduRows->firstWhere('supplier_id', $this->supplierA->id);
        $rowB = $aduRows->firstWhere('supplier_id', $this->supplierB->id);
        $rowC = $aduRows->firstWhere('supplier_id', $supplierC->id);

        $this->assertNotNull($rowA);
        $this->assertNotNull($rowB);
        $this->assertNotNull($rowC);

        // ADU sama persis di ketiga supplier (100/hari, tidak bergantung supplier)
        $this->assertEquals(100, $rowA['adu']);
        $this->assertEquals(100, $rowB['adu']);
        $this->assertEquals(100, $rowC['adu']);

        // Lead time, safety stock, batas minimum, target stock mengikuti masing-masing supplier
        $this->assertTrue($rowA['is_active_supplier']);
        $this->assertEquals(900, $rowA['batas_minimum']);   // 100 * (5 + 4)
        $this->assertEquals(2400, $rowA['target_stock']);   // 100 * (5 + 4 + 15)

        $this->assertFalse($rowB['is_active_supplier']);
        $this->assertEquals(1400, $rowB['batas_minimum']);  // 100 * (8 + 6)
        $this->assertEquals(2900, $rowB['target_stock']);   // 100 * (8 + 6 + 15)

        $this->assertFalse($rowC['is_active_supplier']);
        $this->assertSame('riwayat_po', $rowC['relasi_status']);
        $this->assertEquals(1500, $rowC['batas_minimum']);  // 100 * (10 + 5)
        $this->assertEquals(3000, $rowC['target_stock']);   // 100 * (10 + 5 + 15)
    }

    /**
     * Opsi 1 (Impor): ADU tidak boleh berubah saat supplier (dan Lead Time) berbeda/diganti,
     * sementara Buffer Days, Safety Stock, Min Stock, & Target Stock dihitung per supplier.
     */
    public function test_impor_adu_is_supplier_independent_and_lists_all_supplier_product_pairs(): void
    {
        ProdukSupplier::updateOrCreate(
            ['produk_id' => $this->produkImpor->id, 'supplier_id' => $this->supplierA->id],
            ['status' => 'aktif']
        );
        ProdukSupplier::updateOrCreate(
            ['produk_id' => $this->produkImpor->id, 'supplier_id' => $this->supplierB->id],
            ['status' => 'aktif']
        );

        SupplierProdukLtSummary::create([
            'supplier_id' => $this->supplierA->id,
            'produk_id' => $this->produkImpor->id,
            'jumlah_transaksi' => 2,
            'average_lt_hari' => 30.0,
            'max_lt_hari' => 40.0,
            'diperbarui_pada' => now(),
        ]);
        SupplierProdukLtSummary::create([
            'supplier_id' => $this->supplierB->id,
            'produk_id' => $this->produkImpor->id,
            'jumlah_transaksi' => 2,
            'average_lt_hari' => 60.0,
            'max_lt_hari' => 75.0,
            'diperbarui_pada' => now(),
        ]);

        // Set OUT = 10.000 selama 100 hari -> ADU = 100.0 tepat
        AnalisaImpor::create([
            'produk_id' => $this->produkImpor->id,
            'supplier_terpilih_id' => $this->supplierA->id,
            'out' => 10000,
            'periode_mulai' => '2026-01-01',
            'periode_akhir' => '2026-04-10', // 100 hari inklusif
            'jumlah_hari_periode' => 100,
            'review_period' => 30,
            'klasifikasi_abc' => 'c',
            'tambahan_buffer_hari' => 0,
            'stok_saat_ini' => 500,
            'inbound_before_eta' => 0,
        ]);

        $this->analisaService->generateImpor($this->produkImpor->id, $this->manager->id);

        $beforeSwitch = AnalisaImpor::where('produk_id', $this->produkImpor->id)->firstOrFail();
        $this->assertEqualsWithDelta(100.0, (float) $beforeSwitch->adu_base, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $beforeSwitch->adu_eta, 0.001);

        $res = $this->actingAs($this->purchasing)->getJson(route('analisa.impor.data'));
        $res->assertOk();

        // Tab 1 Rekomendasi Impor tetap 1 baris
        $this->assertCount(1, collect($res->json('data'))->where('produk_id', $this->produkImpor->id));

        // Tab 2 Working Data ADU Impor menampilkan 2 baris (Supplier A & Supplier B)
        $aduRows = collect($res->json('tables.analisa'))->where('produk_id', $this->produkImpor->id)->values();
        $this->assertCount(2, $aduRows);

        $rowA = $aduRows->firstWhere('supplier_id', $this->supplierA->id);
        $rowB = $aduRows->firstWhere('supplier_id', $this->supplierB->id);

        // ADU identik di kedua supplier (100/hari) meski LT berbeda (30 vs 60)
        $this->assertEqualsWithDelta(100.0, (float) $rowA['adu'], 0.01);
        $this->assertEqualsWithDelta(100.0, (float) $rowB['adu'], 0.01);
        $this->assertEquals(3000, $rowA['minimum_stock']);  // 100 * 30
        $this->assertEquals(1000, $rowA['safety_stock']);   // 100 * (40 - 30)
        $this->assertEquals(7000, $rowA['target_stock']);   // 100 * (30 + 10 + 30)
        $this->assertEquals(6000, $rowB['minimum_stock']);  // 100 * 60
        $this->assertEquals(1500, $rowB['safety_stock']);   // 100 * (75 - 60)
        $this->assertEquals(10500, $rowB['target_stock']);  // 100 * (60 + 15 + 30)

        // Ganti supplier aktif ke Supplier B -> ADU tetap 100.0, hanya LT & target berubah
        $this->actingAs($this->manager)->postJson(route('analisa.select-supplier'), [
            'produk_id' => $this->produkImpor->id,
            'supplier_id' => $this->supplierB->id,
        ])->assertOk();

        $afterSwitch = AnalisaImpor::where('produk_id', $this->produkImpor->id)->firstOrFail();
        $this->assertEqualsWithDelta(100.0, (float) $afterSwitch->adu_base, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $afterSwitch->adu_eta, 0.001);
        $this->assertEquals(60.0, (float) $afterSwitch->lead_time);
        $this->assertEquals(6000, (int) $afterSwitch->minimum_stock);
    }
}
