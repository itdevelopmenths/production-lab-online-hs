<?php

namespace Tests\Feature;

use App\Models\AnalisaLokal;
use App\Models\AnalisaImpor;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\Stok;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalisaModalCurrentReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_analisa_endpoints_include_current_system_reference_baselines(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $produk = Produk::where('sku', 'ALK-01')->firstOrFail();

        $produk->update(['harga_hpp' => 45000]);
        Stok::updateOrCreate(
            ['produk_id' => $produk->id, 'gudang_id' => $gudangPusat->id],
            ['qty_saat_ini' => 125.5]
        );

        // 1. Cek endpoint lokal.data
        $resLokal = $this->actingAs($manager)->getJson(route('analisa.lokal.data'));
        $resLokal->assertOk();
        $lokalItem = collect($resLokal->json('data'))->firstWhere('produk_id', $produk->id);
        $this->assertNotNull($lokalItem, 'Produk ALK-01 harus ada di lokal-data');
        $this->assertArrayHasKey('current_hpp', $lokalItem);
        $this->assertArrayHasKey('current_stok_aktual', $lokalItem);
        $this->assertEquals(45000, $lokalItem['current_hpp']);
        $this->assertEquals(125.5, $lokalItem['current_stok_aktual']);

        // 2. Cek endpoint impor.data
        $btl = Produk::where('sku', 'BTL-P50')->first();
        if ($btl) {
            $btl->update(['harga_hpp' => 1750]);
            AnalisaImpor::updateOrCreate(
                ['produk_id' => $btl->id],
                [
                    'lead_time' => 80,
                    'buffer_days' => 20,
                    'safety_stock' => 500,
                    'minimum_stock' => 1000,
                    'target_stock' => 2000,
                    'stok_saat_ini' => 300,
                    'inbound_before_eta' => 150,
                    'proyeksi' => 400,
                    'qty_order' => 1600,
                    'po' => 1600,
                    'status' => 'po',
                    'harga_per_satuan' => 1750,
                    'total_nominal_order' => 2800000,
                ]
            );
        }

        $resImpor = $this->actingAs($manager)->getJson(route('analisa.impor.data'));
        $resImpor->assertOk();
        $imporData = $resImpor->json('data');
        $this->assertNotEmpty($imporData, 'Impor data tidak boleh kosong');
        $firstImpor = collect($imporData)->firstWhere('produk_id', $btl?->id) ?? $imporData[0];
        $this->assertArrayHasKey('current_hpp', $firstImpor);
        $this->assertArrayHasKey('current_stok_aktual', $firstImpor);
        $this->assertArrayHasKey('current_inbound', $firstImpor);
        $this->assertArrayHasKey('empirical_avg_lt', $firstImpor);
        $this->assertArrayHasKey('empirical_max_lt', $firstImpor);
        if ($btl) {
            $this->assertEquals(1750, $firstImpor['current_hpp']);
        }

        // 3. Cek endpoint available-skus
        $resAvail = $this->actingAs($manager)->getJson(route('analisa.available-skus', ['q' => 'ALK-01']));
        $resAvail->assertOk();
        $availItem = collect($resAvail->json('data'))->firstWhere('id', $produk->id);
        $this->assertNotNull($availItem);
        $this->assertArrayHasKey('current_hpp', $availItem);
        $this->assertArrayHasKey('current_stok_aktual', $availItem);
        $this->assertEquals(45000, $availItem['current_hpp']);
        $this->assertEquals(125.5, $availItem['current_stok_aktual']);
    }

    public function test_modal_manual_lokal_renders_system_reference_pills_and_sync_actions(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();

        $response = $this->actingAs($manager)->get(route('analisa.index'));
        $response->assertOk();

        $response->assertSee('manualLokalForm.current_hpp', false);
        $response->assertSee('manualLokalForm.current_stok_aktual', false);
        $response->assertSee('Gunakan Nilai Sistem', false);
    }

    public function test_modal_manual_impor_renders_system_reference_pills_and_sync_actions(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();

        $response = $this->actingAs($manager)->get(route('analisa.index'));
        $response->assertOk();

        $response->assertSee('manualImporForm.current_hpp', false);
        $response->assertSee('manualImporForm.current_stok_aktual', false);
        $response->assertSee('manualImporForm.current_inbound', false);
        $response->assertSee('manualImporForm.empirical_avg_lt', false);
    }

    public function test_modal_add_sku_and_stage_lead_time_render_reference_indicators(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();

        $response = $this->actingAs($manager)->get(route('analisa.index'));
        $response->assertOk();

        $response->assertSee('addSkuForm.current_hpp', false);
        $response->assertSee('addSkuForm.current_stok_aktual', false);
        $response->assertSee('addSkuForm.harga_per_satuan = addSkuForm.current_hpp', false);
        $response->assertSee('stageForm.auto_average', false);
        $response->assertSee('copyAutoStages()', false);
        $response->assertSee('Gunakan Nilai Sistem (PO Otomatis)', false);
    }
}
