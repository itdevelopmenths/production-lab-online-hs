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
            \App\Models\PoStage::STAGE_6 => [3.0, 4.0],
            \App\Models\PoStage::STAGE_7 => [2.0, 3.0],
            \App\Models\PoStage::STAGE_8 => [0.5, 1.0],
            \App\Models\PoStage::STAGE_9 => [0.5, 1.0],
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
        $t6 = $t5->copy()->addHours(48); // stage 6 = 2.0 hari
        $stageService->completeStage6($po, $this->purchasing, ['jumlah_koli' => 1, 'berat_kg' => 10], $t6);
        $t7 = $t6->copy()->addHours(72); // stage 7 = 3.0 hari
        $stageService->completeStage7($po, $this->purchasing, ['ekspedisi' => 'JNE', 'no_resi' => 'RESI-01'], $t7);
        $t8 = $t7->copy()->addHours(12); // stage 8 = 0.5 hari
        $stageService->completeStage8($po, $gudangUser, [
            'kondisi' => 'baik',
            'items' => [['po_item_id' => $poItem->id, 'qty_fisik' => 10]],
        ], $t8);
        $t9 = $t8->copy()->addHours(12); // stage 9 = 0.5 hari
        $stageService->completeStage9($po, $gudangUser, $t9);

        // Tanpa memanggil generateLokal secara manual lagi, data analisa & Tab 4 otomatis menjadi 'otomatis'
        $analisaRow = AnalisaLokal::where('produk_id', $this->produkLokal->id)->firstOrFail();
        $this->assertSame('otomatis', $analisaRow->sumber_lt);
        $this->assertEquals(9.5, (float) $analisaRow->total_average_lead_time);

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
        $this->assertEquals(2.0, $afterRow['average']['pengemasan']);
        $this->assertEquals(3.0, $afterRow['average']['pengiriman']);
        $this->assertEquals(0.5, $afterRow['average']['unloading']);
        $this->assertEquals(0.5, $afterRow['average']['input']);
        $this->assertEquals(9.5, $afterRow['average']['total']);

        // Saat Manager menginvalidasi PoLeadTime (jumlah_transaksi turun menjadi 0), Tab 4 harus kembali ke 'manual'
        $poLt = \App\Models\PoLeadTime::where('po_id', $po->id)->firstOrFail();
        $ltService->invalidateTransaction($poLt, $this->manager, 'Data uji tidak valid');

        $invalidRes = $this->actingAs($this->purchasing)->getJson(route('analisa.lokal.data'));
        $invalidRow = collect($invalidRes->json('tables.stages'))->firstWhere('produk_id', $this->produkLokal->id);
        $this->assertNotNull($invalidRow);
        $this->assertSame('manual', $invalidRow['sumber_lt']);
    }
}
