<?php

namespace Tests\Feature;

use App\Models\AnalisaFulfillmentInput;
use App\Models\AnalisaImporMeta;
use App\Models\AnalisaLokalInput;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\RiwayatAnalisa;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AnalisaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ISO/IEC 25010 Quality Assurance:
 * Analisa Stok Scenarios from Worst-Case to Best-Case.
 */
class AnalisaStokTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Model::preventLazyLoading(true);

        // Contoh PRD ALK-01 memakai Stok Saat Ini 8.000 ml. Analisa bahan membaca stok live Gudang Bahan Baku,
        // jadi saldo GD-PUSAT diset 8.000 (seeder mengisi 500.000 untuk kebutuhan test produksi).
        $alk = Produk::where('sku', 'ALK-01')->firstOrFail();
        app(\App\Services\StokService::class)->setAbsolut($alk->id, Gudang::where('kode', 'GD-PUSAT')->value('id'), 8000, 'Fixture contoh PRD');
    }

    // ==========================================
    // 🌟 1. BEST-CASE SCENARIOS (GOLDEN PATHS)
    // ==========================================

    public function test_best_case_analisa_bahan_lokal_formula_matches_prd_reference(): void
    {
        $input = AnalisaLokalInput::with('produk')->firstOrFail();
        $analisaService = app(AnalisaService::class);

        $hasil = $analisaService->lokal($input);

        // PRD verification: ALK-01 reference values
        $this->assertEquals(13, $hasil['total_avg_lead_time']);
        $this->assertEquals(22, $hasil['total_max_lead_time']);
        $this->assertEquals(11, $hasil['safety_stock_hari']);
        $this->assertEquals('order', $hasil['status']);
        // Sesuai Excel: selisih terhadap Target (8000 - 51094) = -43094 -> MOQ 5000 = 45000
        $this->assertEquals(45000, $hasil['qty_order']);
        $this->assertEquals(45000 * 526, $hasil['total_nominal_order']);

        // Test API Endpoint
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->actingAs($purchasing);

        $response = $this->getJson(route('analisa.lokal.data'));
        $response->assertOk();
        $response->assertJsonFragment([
            'sku' => $input->produk->sku,
            'status' => 'order',
            'qty_order' => 45000,
        ]);
    }

    public function test_best_case_analisa_bahan_impor_formula_and_endpoint(): void
    {
        $meta = AnalisaImporMeta::with('produk')->firstOrFail();

        $analisaService = app(AnalisaService::class);
        $hasil = $analisaService->generateImpor($meta->produk_id);

        $this->assertGreaterThanOrEqual(1, $hasil['generated_count']);

        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->actingAs($purchasing);

        $response = $this->getJson(route('analisa.impor.data'));
        $response->assertOk();
        $response->assertJsonFragment([
            'sku' => $meta->produk->sku,
        ]);
    }

    public function test_best_case_analisa_fulfillment_formula_and_endpoint(): void
    {
        $goh = Produk::produkJadi()->firstOrFail();
        $inputs = AnalisaFulfillmentInput::with('gudang')->where('produk_id', $goh->id)->get();
        $this->assertCount(3, $inputs);

        $analisaService = app(AnalisaService::class);
        $hasil = $analisaService->fulfillment($inputs, (float) ($goh->satuan_order_moq ?? 1));

        $this->assertArrayHasKey('batas_minimum_total', $hasil);
        $this->assertArrayHasKey('target_stock_total', $hasil);
        $this->assertArrayHasKey('qty_order', $hasil);
        $this->assertCount(3, $hasil['per_gudang']);

        $fulfillmentUser = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();
        $this->actingAs($fulfillmentUser);

        $response = $this->getJson(route('analisa.fulfillment.data'));
        $response->assertOk();
        $response->assertJsonFragment([
            'sku' => $goh->sku,
        ]);
    }

    public function test_best_case_create_po_automatically_from_analisa(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $supplier = Supplier::firstOrFail();
        $alk = Produk::bahan()->firstOrFail();

        $this->actingAs($purchasing);

        $response = $this->post(route('analisa.create-po'), [
            'supplier_id' => $supplier->id,
            'catatan' => 'Pesanan urgent supply chain',
            'items' => [
                [
                    'produk_id' => $alk->id,
                    'qty' => 25000,
                    'harga_total' => 25000 * 526,
                ],
            ],
        ]);

        $po = PurchaseOrder::latest()->firstOrFail();
        $response->assertRedirect(route('purchasing.show', $po));
        $this->assertTrue($po->dari_analisa);
        $this->assertEquals('draft', $po->status);
        $this->assertEquals('Pesanan urgent supply chain', $po->catatan);
        $this->assertCount(1, $po->items);
        $this->assertEquals(25000, (float) $po->items->first()->qty);
    }

    public function test_best_case_saving_snapshot_to_riwayat_analisa(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->actingAs($purchasing);

        $this->post(route('analisa.snapshot'), [
            'tipe' => 'bahan_lokal',
        ])->assertSessionHas('success');

        $this->assertGreaterThan(0, RiwayatAnalisa::where('tipe', 'bahan_lokal')->count());

        $response = $this->getJson(route('analisa.riwayat.data'));
        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
    }

    // ==========================================
    // 🔍 2. BOUNDARY / EDGE-CASE SCENARIOS
    // ==========================================

    public function test_boundary_case_stock_exactly_at_target_stock_triggers_order(): void
    {
        $analisaService = app(AnalisaService::class);
        $input = AnalisaLokalInput::with('produk')->firstOrFail();

        // Pemicu ORDER mengikuti Excel: Stok + Akan Datang <= Target Stock
        $hasil = $analisaService->lokal($input);
        // Kolom stok_saat_ini ber-cast decimal:2; Target ALK-01 = 51094.225 akan dibulatkan KE ATAS (.23)
        // dan melewati target. Pakai pembulatan ke bawah 2 desimal = nilai terbesar yang masih <= Target.
        $exactBatasMin = floor($hasil['target_stock'] * 100) / 100;

        // Case A: Exactly Equal -> triggers 'order'
        $input->stok_saat_ini = $exactBatasMin;
        $input->akan_datang = 0;
        $hasilEqual = $analisaService->lokal($input);
        $this->assertEquals('order', $hasilEqual['status']);

        // Case B: Just Above by 1 unit -> triggers 'tidak' (AMAN)
        $input->stok_saat_ini = $exactBatasMin + 1;
        $input->akan_datang = 0;
        $hasilAbove = $analisaService->lokal($input);
        $this->assertEquals('tidak', $hasilAbove['status']);
        $this->assertEquals(0, $hasilAbove['qty_order']);
    }

    public function test_boundary_case_moq_rounding_boundary(): void
    {
        $analisaService = app(AnalisaService::class);
        $input = AnalisaLokalInput::with('produk')->firstOrFail();
        $hasil = $analisaService->lokal($input);

        // Difference of only 1 unit should round up to full MOQ of 5,000
        $input->stok_saat_ini = $hasil['target_stock'] - 1;
        $input->akan_datang = 0;
        $res = $analisaService->lokal($input);
        $this->assertEquals('order', $res['status']);
        $this->assertEquals(5000, $res['qty_order']);
    }

    // ==========================================
    // ⚠️ 3. WORST-CASE / NEGATIVE SCENARIOS
    // ==========================================

    public function test_worst_case_zero_average_sales_handled_without_division_by_zero(): void
    {
        $analisaService = app(AnalisaService::class);
        $input = AnalisaLokalInput::with('produk')->firstOrFail();

        // Tidak ada pemakaian bahan di batch produksi maupun proyeksi BOM -> ADU 0
        \App\Models\KartuStok::where('referensi_tipe', 'batch_produksi')->delete();
        \App\Models\AnalisaFulfillmentInput::query()->delete();
        $input->stok_saat_ini = 0;
        $input->akan_datang = 0;

        $hasil = $analisaService->lokal($input);
        $this->assertEquals(0, $hasil['adu']);
        $this->assertEquals(0, $hasil['batas_minimum']);
        $this->assertEquals(0, $hasil['target_stock']);
    }

    public function test_worst_case_create_po_from_analisa_validation_failures(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->actingAs($purchasing);

        // 1. Missing supplier
        $response = $this->post(route('analisa.create-po'), [
            'supplier_id' => 999999,
            'items' => [
                ['produk_id' => 1, 'qty' => 10, 'harga_total' => 1000],
            ],
        ]);
        $response->assertSessionHasErrors('supplier_id');

        // 2. Zero qty
        $supplier = Supplier::firstOrFail();
        $response2 = $this->post(route('analisa.create-po'), [
            'supplier_id' => $supplier->id,
            'items' => [
                ['produk_id' => 1, 'qty' => 0, 'harga_total' => 1000],
            ],
        ]);
        $response2->assertSessionHasErrors('items.0.qty');

        // 3. Negative price
        $response3 = $this->post(route('analisa.create-po'), [
            'supplier_id' => $supplier->id,
            'items' => [
                ['produk_id' => 1, 'qty' => 10, 'harga_total' => -100],
            ],
        ]);
        $response3->assertSessionHasErrors('items.0.harga_total');
    }

    public function test_worst_case_invalid_snapshot_type_rejected(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->actingAs($purchasing);

        $response = $this->post(route('analisa.snapshot'), [
            'tipe' => 'invalid_random_type',
        ]);
        $response->assertSessionHasErrors('tipe');
    }
}
