<?php

namespace Tests\Feature;

use App\Models\AnalisaLokal;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Stok;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
    }

    public function test_stok_aktual_berjalan_and_status_badge_evaluation(): void
    {
        $user = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($user);

        // Pastikan view stok.index merender header baru dan tidak merender label lama
        $indexRes = $this->get(route('stok.index'));
        $indexRes->assertOk();
        $indexRes->assertSee('Stok Aktual');
        $indexRes->assertSee('Stok Berjalan');
        $indexRes->assertSee('Status');
        $indexRes->assertDontSee('Kolom Stok');
        $indexRes->assertDontSee('Kolom Rencana');

        // Ambil produk bahan ALK-01 dan Gudang Bahan Baku Pusat (Batas Min MasterDataSeeder: 31.442,6)
        $alk = Produk::where('sku', 'ALK-01')->firstOrFail();
        $gBahan = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();

        $response = $this->getJson(route('stok.data', ['gudang_id' => $gBahan->id]));
        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('sku', 'ALK-01');
        $this->assertNotNull($row);
        $this->assertStringContainsString('31.442', $row['batas_minimum']);
        $this->assertStringContainsString('500.000 ml', $row['stok_aktual']);
        $this->assertStringContainsString('0 ml', $row['stok_berjalan']);
        $this->assertStringContainsString('AMAN', $row['status_stok']);

        // 1. Turunkan stok aktual di bawah Batas Min (10.000 <= 31.442,6) tanpa PO berjalan -> ORDER
        app(StokService::class)->setAbsolut($alk->id, $gBahan->id, 10000, 'Simulasi stok menipis');

        $rowLow = collect($this->getJson(route('stok.data', ['gudang_id' => $gBahan->id]))->json('data'))
            ->firstWhere('sku', 'ALK-01');
        $this->assertStringContainsString('10.000 ml', $rowLow['stok_aktual']);
        $this->assertStringContainsString('0 ml', $rowLow['stok_berjalan']);
        $this->assertStringContainsString('ORDER', $rowLow['status_stok']);

        // 2. Buat PO berjalan 25.000 ml -> Aktual tetap 10.000, Berjalan = 25.000, (10.000 + 25.000 - 31.442,6 > 0) -> AMAN
        $supplier = Supplier::firstOrFail();
        $po = PurchaseOrder::create([
            'no_po' => 'PO-ALERT-TEST-01',
            'supplier_id' => $supplier->id,
            'gudang_id' => $gBahan->id,
            'tanggal' => now()->toDateString(),
            'status' => 'berjalan',
            'created_by' => $user->id,
        ]);
        $poItem = $po->items()->create([
            'produk_id' => $alk->id,
            'qty' => 25000,
            'harga_total' => 25000 * 500,
            'hpp_per_satuan' => 500,
        ]);

        $rowCovered = collect($this->getJson(route('stok.data', ['gudang_id' => $gBahan->id]))->json('data'))
            ->firstWhere('sku', 'ALK-01');
        $this->assertStringContainsString('10.000 ml', $rowCovered['stok_aktual']);
        $this->assertStringContainsString('25.000 ml', $rowCovered['stok_berjalan']);
        $this->assertStringContainsString('AMAN', $rowCovered['status_stok']);

        // 3. Kurangi qty PO berjalan menjadi 20.000 ml -> (10.000 + 20.000 - 31.442,6 <= 0) -> ORDER
        $poItem->update(['qty' => 20000]);

        $rowDeficit = collect($this->getJson(route('stok.data', ['gudang_id' => $gBahan->id]))->json('data'))
            ->firstWhere('sku', 'ALK-01');
        $this->assertStringContainsString('10.000 ml', $rowDeficit['stok_aktual']);
        $this->assertStringContainsString('20.000 ml', $rowDeficit['stok_berjalan']);
        $this->assertStringContainsString('ORDER', $rowDeficit['status_stok']);

        // 4. Boundary tepat sama: batas_minimum = 30.000 -> (10.000 + 20.000 - 30.000 == 0 <= 0) -> ORDER
        AnalisaLokal::where('produk_id', $alk->id)->update(['batas_minimum' => 30000]);

        $rowBoundary = collect($this->getJson(route('stok.data', ['gudang_id' => $gBahan->id]))->json('data'))
            ->firstWhere('sku', 'ALK-01');
        $this->assertStringContainsString('ORDER', $rowBoundary['status_stok']);

        // 5. Item dengan Batas Min Analisa = 0 dan stok 0 + berjalan 0 -> tetap AMAN (bukan ORDER palsu)
        $noMinProduk = Produk::create([
            'sku' => 'NO-MIN-01',
            'nama' => 'Kemasan Tanpa Batas Min',
            'tipe' => 'kemas',
            'satuan' => 'pcs',
            'is_active' => true,
        ]);
        Stok::create([
            'produk_id' => $noMinProduk->id,
            'gudang_id' => $gBahan->id,
            'qty_saat_ini' => 0,
            'updated_at' => now(),
        ]);

        $rowNoMin = collect($this->getJson(route('stok.data', ['gudang_id' => $gBahan->id]))->json('data'))
            ->firstWhere('sku', 'NO-MIN-01');
        $this->assertNotNull($rowNoMin);
        $this->assertEquals('-', $rowNoMin['batas_minimum']);
        $this->assertStringContainsString('0 pcs', $rowNoMin['stok_aktual']);
        $this->assertStringContainsString('0 pcs', $rowNoMin['stok_berjalan']);
        $this->assertStringContainsString('AMAN', $rowNoMin['status_stok']);

        // 6. Toleransi presisi floating-point IEEE 754 pada boundary tepat sama (0.1 + 0.2 - 0.3 == 0 -> ORDER)
        app(StokService::class)->setAbsolut($alk->id, $gBahan->id, 0.1, 'Simulasi stok desimal 0.1');
        $poItem->update(['qty' => 0.2]);
        AnalisaLokal::where('produk_id', $alk->id)->update(['batas_minimum' => 0.3]);

        $rowFloatBoundary = collect($this->getJson(route('stok.data', ['gudang_id' => $gBahan->id]))->json('data'))
            ->firstWhere('sku', 'ALK-01');
        $this->assertNotNull($rowFloatBoundary);
        $this->assertStringContainsString('ORDER', $rowFloatBoundary['status_stok']);
    }
}
