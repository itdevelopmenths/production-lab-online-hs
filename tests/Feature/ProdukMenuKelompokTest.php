<?php

namespace Tests\Feature;

use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdukMenuKelompokTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
    }

    private function tipeDariData(array $params): array
    {
        $json = $this->actingAs($this->manager)
            ->getJson(route('produk.data', array_merge(['draw' => 1, 'start' => 0, 'length' => 500], $params)))
            ->assertOk()
            ->json('data');

        $skus = collect($json)->map(fn ($row) => strip_tags($row['sku']))->all();

        return Produk::whereIn('sku', $skus)->pluck('tipe')->unique()->sort()->values()->all();
    }

    public function test_sidebar_memisahkan_menu_bahan_kemasan_dan_produk_jadi(): void
    {
        $this->actingAs($this->manager)->get(route('produk.index'))
            ->assertOk()
            ->assertSee('Master Bahan Baku & Kemasan', false)
            ->assertSee(route('produk.index', ['kelompok' => 'produk_jadi']), false);

        $this->actingAs($this->manager)->get(route('produk.index', ['kelompok' => 'produk_jadi']))
            ->assertOk()
            ->assertSee('Master Produk Jadi')
            ->assertSee('Tambah Produk Jadi');
    }

    public function test_data_bahan_tidak_memuat_produk_jadi_dan_sebaliknya(): void
    {
        Produk::create(['sku' => 'PJ-MENU-01', 'nama' => 'PJ Menu', 'tipe' => 'produk_jadi', 'satuan' => 'pcs']);

        $this->assertNotContains('produk_jadi', $this->tipeDariData(['kelompok' => 'bahan']));
        $this->assertSame(['produk_jadi'], $this->tipeDariData(['kelompok' => 'produk_jadi']));

        // Filter tipe di luar kelompok diabaikan: tetap hanya tipe kelompok bahan.
        $this->assertNotContains('produk_jadi', $this->tipeDariData(['kelompok' => 'bahan', 'tipe' => 'produk_jadi']));
        $this->assertSame(['kemas'], $this->tipeDariData(['kelompok' => 'bahan', 'tipe' => 'kemas']));
    }

    public function test_form_tambah_hanya_menampilkan_tipe_sesuai_menu(): void
    {
        $this->actingAs($this->manager)->get(route('produk.create', ['kelompok' => 'produk_jadi']))
            ->assertOk()
            ->assertSee('value="produk_jadi"', false)
            ->assertDontSee('value="kemas"', false);

        $this->actingAs($this->manager)->get(route('produk.create'))
            ->assertOk()
            ->assertSee('value="kemas"', false)
            ->assertDontSee('value="produk_jadi"', false);
    }

    public function test_index_url_per_kelompok(): void
    {
        $this->assertSame(route('produk.index'), Produk::indexUrl(Produk::kelompokDariTipe('kemas')));
        $this->assertSame(
            route('produk.index', ['kelompok' => 'produk_jadi']),
            Produk::indexUrl(Produk::kelompokDariTipe('produk_jadi'))
        );
    }
}
