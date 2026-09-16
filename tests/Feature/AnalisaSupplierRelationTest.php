<?php

namespace Tests\Feature;

use App\Models\Produk;
use App\Models\RekomendasiOrderLokal;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AnalisaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalisaSupplierRelationTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchasing;
    protected Produk $alkohol;
    protected Produk $botol;
    protected Supplier $supplierLokal;
    protected Supplier $supplierImpor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Model::preventLazyLoading(true);

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->alkohol = Produk::where('sku', 'ALK-01')->firstOrFail();
        $this->botol = Produk::where('sku', 'BTL-P50')->firstOrFail();
        $this->supplierLokal = Supplier::where('nama', 'PT Alkohol Nusantara')->firstOrFail();
        $this->supplierImpor = Supplier::where('nama', 'Glass Bottle Import Co.')->firstOrFail();
    }

    public function test_produk_belongs_to_supplier_and_supplier_has_many_produk(): void
    {
        $this->assertNotNull($this->alkohol->supplier_id);
        $this->assertEquals($this->supplierLokal->id, $this->alkohol->supplier_id);
        $this->assertInstanceOf(Supplier::class, $this->alkohol->supplier);
        $this->assertEquals('PT Alkohol Nusantara', $this->alkohol->supplier->nama);

        $this->assertNotNull($this->botol->supplier_id);
        $this->assertEquals($this->supplierImpor->id, $this->botol->supplier_id);
        $this->assertInstanceOf(Supplier::class, $this->botol->supplier);
        $this->assertEquals('Glass Bottle Import Co.', $this->botol->supplier->nama);

        // Test reverse relation
        $this->assertTrue($this->supplierLokal->produk->contains('id', $this->alkohol->id));
        $this->assertTrue($this->supplierImpor->produk->contains('id', $this->botol->id));
    }

    public function test_lokal_data_endpoint_returns_supplier_id_and_nama(): void
    {
        app(AnalisaService::class)->generateLokal($this->alkohol->id, $this->purchasing->id);

        $response = $this->actingAs($this->purchasing)
            ->getJson(route('analisa.lokal.data'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'produk_id',
                        'sku',
                        'nama',
                        'supplier_id',
                        'supplier_nama',
                        'supplier_kategori',
                    ],
                ],
            ]);

        $item = collect($response->json('data'))->firstWhere('produk_id', $this->alkohol->id);
        $this->assertNotNull($item);
        $this->assertEquals($this->supplierLokal->id, $item['supplier_id']);
        $this->assertEquals('PT Alkohol Nusantara', $item['supplier_nama']);
        $this->assertEquals('lokal', $item['supplier_kategori']);
    }

    public function test_impor_data_endpoint_returns_supplier_id_and_nama(): void
    {
        app(AnalisaService::class)->generateImpor($this->botol->id, $this->purchasing->id);

        $response = $this->actingAs($this->purchasing)
            ->getJson(route('analisa.impor.data'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'produk_id',
                        'sku',
                        'nama',
                        'supplier_id',
                        'supplier_nama',
                        'supplier_kategori',
                    ],
                ],
            ]);

        $item = collect($response->json('data'))->firstWhere('produk_id', $this->botol->id);
        $this->assertNotNull($item);
        $this->assertEquals($this->supplierImpor->id, $item['supplier_id']);
        $this->assertEquals('Glass Bottle Import Co.', $item['supplier_nama']);
        $this->assertEquals('impor', $item['supplier_kategori']);
    }

    public function test_create_po_form_auto_preselects_supplier_when_selected_items_share_supplier(): void
    {
        app(AnalisaService::class)->generateLokal($this->alkohol->id, $this->purchasing->id);
        $rek = RekomendasiOrderLokal::where('produk_id', $this->alkohol->id)->firstOrFail();

        $response = $this->actingAs($this->purchasing)
            ->get(route('analisa.create-po', ['ids' => $rek->id]));

        $response->assertOk();
        $response->assertViewHas('defaultSupplierId', $this->supplierLokal->id);
        $response->assertSee('PT Alkohol Nusantara');
    }

    public function test_analisa_index_blade_renders_supplier_filter_and_supplier_badges(): void
    {
        $response = $this->actingAs($this->purchasing)
            ->get(route('analisa.index'));

        $response->assertOk();
        $response->assertSee('supplierFilter');
        $response->assertSee('Semua Supplier');
        $response->assertSee('PT Alkohol Nusantara');
        $response->assertSee('Glass Bottle Import Co.');
        $response->assertSee('r.supplier_nama');
    }
}
