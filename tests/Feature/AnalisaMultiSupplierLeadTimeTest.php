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
}
