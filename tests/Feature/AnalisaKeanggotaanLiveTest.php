<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\RekomendasiOrderLokal;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Analisa Lokal/Impor: keanggotaan eksplisit (Add SKU / profil_analisa) dan dihitung live saat dibuka.
 */
class AnalisaKeanggotaanLiveTest extends TestCase
{
    use RefreshDatabase;

    private User $purchasing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
    }

    public function test_bahan_baru_tanpa_profil_tidak_otomatis_masuk_analisa(): void
    {
        $baru = Produk::create([
            'sku' => 'BHN-BARU-01', 'nama' => 'Bahan Baru', 'tipe' => 'bahan', 'satuan' => 'ml',
            'satuan_order_moq' => 100, 'is_active' => true,
        ]);

        $lokalIds = collect($this->actingAs($this->purchasing)->getJson(route('analisa.lokal.data'))->json('data'))->pluck('produk_id');
        $imporIds = collect($this->getJson(route('analisa.impor.data'))->json('data'))->pluck('produk_id');

        $this->assertNotContains($baru->id, $lokalIds);
        $this->assertNotContains($baru->id, $imporIds);

        // Setelah didaftarkan (profil lokal), ikut dihitung.
        $baru->update(['profil_analisa' => 'lokal']);
        $lokalIds = collect($this->getJson(route('analisa.lokal.data'))->json('data'))->pluck('produk_id');
        $this->assertContains($baru->id, $lokalIds);
    }

    public function test_tab_lokal_menghitung_ulang_stok_live_setiap_dibuka(): void
    {
        $alk = Produk::where('sku', 'ALK-01')->firstOrFail();
        $pusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $stok = app(StokService::class);

        $stok->setAbsolut($alk->id, $pusat->id, 8000, 'uji');
        $this->actingAs($this->purchasing)->getJson(route('analisa.lokal.data'))->assertOk();
        $this->assertEquals(8000, (float) RekomendasiOrderLokal::where('produk_id', $alk->id)->value('stok_saat_ini'));

        // Stok berubah: dalam 60 detik hasil terakhir dipakai ulang (pindah tab/filter tidak menghitung ulang)...
        $stok->setAbsolut($alk->id, $pusat->id, 12000, 'uji');
        $this->getJson(route('analisa.lokal.data'))->assertOk();
        $this->assertEquals(8000, (float) RekomendasiOrderLokal::where('produk_id', $alk->id)->value('stok_saat_ini'));

        // ...tombol Muat Ulang (refresh=1) langsung menghitung ulang.
        $this->getJson(route('analisa.lokal.data', ['refresh' => 1]))->assertOk();
        $this->assertEquals(12000, (float) RekomendasiOrderLokal::where('produk_id', $alk->id)->value('stok_saat_ini'));

        // Setelah lewat 60 detik, membuka tab otomatis menghitung ulang tanpa tombol.
        $stok->setAbsolut($alk->id, $pusat->id, 9000, 'uji');
        $this->travel(61)->seconds();
        $this->getJson(route('analisa.lokal.data'))->assertOk();
        $this->assertEquals(9000, (float) RekomendasiOrderLokal::where('produk_id', $alk->id)->value('stok_saat_ini'));
    }
}
