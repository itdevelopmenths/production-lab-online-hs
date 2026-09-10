<?php

namespace Tests\Feature;

use App\Models\AnalisaLokalInput;
use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\LeadTimeStage;
use App\Models\Produk;
use App\Models\Stok;
use App\Models\User;
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

    public function test_two_column_stock_alert_reflects_physical_and_planned_availability(): void
    {
        $user = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($user);

        // Ambil produk bahan ALK-01 dan gudang bahan baku pusat
        $alk = Produk::where('sku', 'ALK-01')->firstOrFail();
        $gBahan = Gudang::where('tipe', 'bahan_baku')->firstOrFail();

        // Pastikan Batas Minimum ALK-01 ada dari Analisa Stok (MasterDataSeeder: 31.442,6)
        $response = $this->getJson(route('stok.data'));
        $response->assertStatus(200);

        $data = collect($response->json('data'));
        $row = $data->firstWhere('sku', 'ALK-01');

        $this->assertNotNull($row);
        $this->assertStringContainsString('31.442', $row['batas_minimum']);
        // Saat stok fisik 500.000 > 31.442,6 -> AMAN
        $this->assertStringContainsString('AMAN', $row['kolom_stok']);
        $this->assertStringContainsString('AMAN', $row['kolom_rencana']);

        // Sekarang buat batch dengan alokasi besar (misal 480.000 ml)
        // sehingga Kolom Rencana menjadi 20.000 ml (< 31.442,6) -> ORDER,
        // sedangkan Kolom Stok fisik tetap 500.000 ml -> AMAN
        $batch = BatchProduksi::create([
            'no_batch' => 'BTH-TEST-ALERT',
            'produk_id' => Produk::where('tipe', 'produk_jadi')->value('id') ?? $alk->id,
            'qty_rencana' => 12000,
            'gudang_operasional_id' => $gBahan->id,
            'status' => 'rencana',
            'tanggal' => now(),
            'created_by' => $user->id,
        ]);

        $batch->alokasi()->create([
            'bahan_id' => $alk->id,
            'qty_dialokasikan' => 480000,
            'status' => 'aktif',
        ]);

        $responseAfter = $this->getJson(route('stok.data'));
        $responseAfter->assertStatus(200);

        $rowAfter = collect($responseAfter->json('data'))->firstWhere('sku', 'ALK-01');

        // Kolom Stok fisik tetap AMAN
        $this->assertStringContainsString('AMAN', $rowAfter['kolom_stok']);
        // Kolom Rencana memperhitungkan rencana produksi aktif -> menjadi ORDER
        $this->assertStringContainsString('ORDER', $rowAfter['kolom_rencana']);

        // Bersihkan data dummy test
        $batch->alokasi()->delete();
        $batch->delete();
    }
}
