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
}
