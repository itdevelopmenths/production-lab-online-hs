<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Stok;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StokRencanaAndFormattingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
        $this->seed();
    }

    public function test_stok_data_formatting_and_rencana_calculation_for_materials_and_finished_goods(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $gBahan = Gudang::where('tipe', 'bahan_baku')->firstOrFail();
        $gOps = Gudang::where('tipe', 'operasional')->firstOrFail();

        $bahan = Produk::where('sku', 'ALK-01')->firstOrFail();
        $produkJadi = Produk::where('sku', 'GOH-P50')->firstOrFail();

        // Ensure stock exists
        $stokService = app(StokService::class);
        $stokService->setAbsolut($bahan->id, $gOps->id, 1000, 'Init stock');
        $stokService->setAbsolut($produkJadi->id, $gOps->id, 50, 'Init stock');

        // Create active batch production with allocation on ALK-01 (100 ml allocated) and 10 pcs GOH-P50 planned
        $batch = BatchProduksi::create([
            'no_batch' => 'BATCH-TEST-STOK',
            'produk_id' => $produkJadi->id,
            'qty_rencana' => 10,
            'gudang_operasional_id' => $gOps->id,
            'status' => 'rencana',
            'tanggal' => now()->toDateString(),
            'created_by' => $manager->id,
        ]);

        $batch->alokasi()->create([
            'bahan_id' => $bahan->id,
            'qty_dialokasikan' => 100,
            'status' => 'aktif',
        ]);

        // Request DataTables Stok
        $res = $this->getJson(route('stok.data', ['gudang_id' => $gOps->id]));
        $res->assertOk();
        $data = collect($res->json('data'));

        // 1. Check Bahan Baku (ALK-01): Fisik = 1.000, Alokasi = 100 -> Kolom Rencana = 900
        $rowBahan = $data->firstWhere('sku', 'ALK-01');
        $this->assertNotNull($rowBahan);
        $this->assertStringContainsString('1.000 ml', $rowBahan['kolom_stok']);
        $this->assertStringContainsString('900 ml', $rowBahan['kolom_rencana']);
        $this->assertStringNotContainsString('1000.00', $rowBahan['kolom_stok']);

        // 2. Check Produk Jadi (GOH-P50): Fisik = 50, Rencana Batch = 10 -> Kolom Rencana = 60
        $rowJadi = $data->firstWhere('sku', 'GOH-P50');
        $this->assertNotNull($rowJadi);
        $this->assertStringContainsString('50 pcs', $rowJadi['kolom_stok']);
        $this->assertStringContainsString('60 pcs', $rowJadi['kolom_rencana']);
    }

    public function test_stok_price_masking_for_roles_without_price_permission(): void
    {
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $this->actingAs($gudangUser);

        $res = $this->getJson(route('stok.data'));
        $res->assertOk();
        $data = $res->json('data');

        foreach ($data as $row) {
            $this->assertEquals('—', $row['hpp']);
            $this->assertEquals('—', $row['nilai_stok']);
        }
    }

    public function test_stok_category_and_location_filtering(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        // Filter only bahan
        $resBahan = $this->getJson(route('stok.data', ['kategori' => 'bahan']));
        $resBahan->assertOk();
        $dataBahan = collect($resBahan->json('data'));
        $this->assertTrue($dataBahan->every(fn ($r) => in_array($r['sku'], ['ALK-01', 'OIL-GOH', 'BTL-P50', 'CAP-01'])));

        // Filter only produk jadi
        $resJadi = $this->getJson(route('stok.data', ['kategori' => 'produk_jadi']));
        $resJadi->assertOk();
        $dataJadi = collect($resJadi->json('data'));
        $this->assertTrue($dataJadi->every(fn ($r) => $r['sku'] === 'GOH-P50'));
    }

    public function test_stok_hpp_calculation_and_decimal_precision_formatting(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $gOps = Gudang::where('tipe', 'operasional')->firstOrFail();
        $supplier = \App\Models\Supplier::firstOrFail();

        // 1. Create a raw material with fractional HPP (e.g. 526.35) via Purchase Order
        $p1 = Produk::create([
            'sku' => 'DEC-TEST-01',
            'nama' => 'Bahan Desimal 2 Digit',
            'tipe' => 'bahan',
            'satuan' => 'gram',
            'harga_hpp' => 0,
            'status' => 'aktif',
        ]);

        $stokService = app(StokService::class);
        $stokService->setAbsolut($p1->id, $gOps->id, 10, 'Init stock');

        $po = PurchaseOrder::create([
            'no_po' => 'PO-DEC-01',
            'supplier_id' => $supplier->id,
            'gudang_id' => $gOps->id,
            'tanggal' => now()->toDateString(),
            'status' => 'selesai',
            'created_by' => $manager->id,
        ]);

        $po->items()->create([
            'produk_id' => $p1->id,
            'qty' => 100,
            'harga_total' => 52635,
            'net_total' => 52635,
            'hpp_per_satuan' => 526.35,
        ]);

        // 2. Create another item with 4 decimal places (e.g. 106666.6667)
        $p2 = Produk::create([
            'sku' => 'DEC-TEST-02',
            'nama' => 'Bahan Desimal 4 Digit',
            'tipe' => 'bahan',
            'satuan' => 'kg',
            'harga_hpp' => 0,
            'status' => 'aktif',
        ]);
        $stokService->setAbsolut($p2->id, $gOps->id, 3, 'Init stock');

        $po->items()->create([
            'produk_id' => $p2->id,
            'qty' => 3,
            'harga_total' => 320000,
            'net_total' => 320000,
            'hpp_per_satuan' => 106666.6667,
        ]);

        // 3. Create an item without PO, but with harga_hpp on master produk (e.g. 15000.75)
        $p3 = Produk::create([
            'sku' => 'DEC-TEST-03',
            'nama' => 'Bahan Fallback Master',
            'tipe' => 'bahan',
            'satuan' => 'pcs',
            'harga_hpp' => 15000.75,
            'status' => 'aktif',
        ]);
        $stokService->setAbsolut($p3->id, $gOps->id, 2, 'Init stock');

        // Request stok data
        $res = $this->getJson(route('stok.data', ['gudang_id' => $gOps->id]));
        $res->assertOk();
        $data = collect($res->json('data'));

        $row1 = $data->firstWhere('sku', 'DEC-TEST-01');
        $this->assertNotNull($row1);
        $this->assertEquals('Rp 526,35', $row1['hpp']);
        $this->assertEquals('Rp 5.263,50', $row1['nilai_stok']);

        $row2 = $data->firstWhere('sku', 'DEC-TEST-02');
        $this->assertNotNull($row2);
        $this->assertEquals('Rp 106.666,67', $row2['hpp']);

        $row3 = $data->firstWhere('sku', 'DEC-TEST-03');
        $this->assertNotNull($row3);
        $this->assertEquals('Rp 15.000,75', $row3['hpp']);
        $this->assertEquals('Rp 30.001,50', $row3['nilai_stok']);
    }
}
