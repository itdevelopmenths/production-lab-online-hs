<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukSupplier;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdukMultiSupplierTest extends TestCase
{
    use RefreshDatabase;

    private User $purchasingUser;
    private Supplier $supplierA;
    private Supplier $supplierB;
    private Gudang $gudangPusat;
    private Produk $produkA;
    private Produk $produkB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->purchasingUser = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();

        $kategori = Kategori::firstOrCreate([
            'kode' => 'BB',
        ], [
            'nama' => 'Bahan Baku',
        ]);

        $this->gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();

        $this->supplierA = Supplier::create([
            'nama' => 'PT Supplier Utama',
            'kategori' => 'lokal',
            'termin_default' => 'termin',
            'is_active' => true,
        ]);

        $this->supplierB = Supplier::create([
            'nama' => 'PT Supplier Sekunder',
            'kategori' => 'lokal',
            'termin_default' => 'tempo',
            'is_active' => true,
        ]);

        $this->produkA = Produk::create([
            'sku' => 'MAT-01',
            'nama' => 'Bahan Baku A',
            'nama_produk' => 'Bahan Baku A',
            'kategori_id' => $kategori->id,
            'tipe' => 'bahan',
            'satuan' => 'L',
            'satuan_order_moq' => 100,
            'is_active' => true,
        ]);

        $this->produkB = Produk::create([
            'sku' => 'MAT-02',
            'nama' => 'Bahan Baku B',
            'nama_produk' => 'Bahan Baku B',
            'kategori_id' => $kategori->id,
            'tipe' => 'bahan',
            'satuan' => 'L',
            'satuan_order_moq' => 50,
            'is_active' => true,
        ]);
    }

    public function test_product_can_have_multiple_active_suppliers(): void
    {
        ProdukSupplier::create([
            'produk_id' => $this->produkA->id,
            'supplier_id' => $this->supplierA->id,
            'status' => 'aktif',
        ]);

        ProdukSupplier::create([
            'produk_id' => $this->produkA->id,
            'supplier_id' => $this->supplierB->id,
            'status' => 'aktif',
        ]);

        $this->assertEquals(2, $this->produkA->activeSuppliers()->count());
        $this->assertTrue($this->produkA->activeSuppliers->contains($this->supplierA));
        $this->assertTrue($this->produkA->activeSuppliers->contains($this->supplierB));
    }

    public function test_can_toggle_supplier_status_between_aktif_and_nonaktif(): void
    {
        $rel = ProdukSupplier::create([
            'produk_id' => $this->produkA->id,
            'supplier_id' => $this->supplierA->id,
            'status' => 'aktif',
        ]);

        $this->assertEquals(1, $this->produkA->activeSuppliers()->count());

        $rel->update(['status' => 'nonaktif']);

        $this->assertEquals(0, $this->produkA->activeSuppliers()->count());
        $this->assertEquals(1, $this->produkA->suppliers()->count());
    }

    public function test_tc_32_po_with_item_registered_to_supplier_succeeds(): void
    {
        ProdukSupplier::create([
            'produk_id' => $this->produkA->id,
            'supplier_id' => $this->supplierA->id,
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->purchasingUser)->post(route('purchasing.store'), [
            'supplier_id' => $this->supplierA->id,
            'gudang_id' => $this->gudangPusat->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'termin',
            'items' => [
                [
                    'produk_id' => $this->produkA->id,
                    'qty' => 100,
                    'harga_total' => 1000000,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $this->supplierA->id,
        ]);
    }

    public function test_tc_32_po_with_item_not_registered_to_supplier_is_rejected(): void
    {
        // produkA terdaftar pada supplierA, tetapi TIDAK terdaftar pada supplierB
        ProdukSupplier::create([
            'produk_id' => $this->produkA->id,
            'supplier_id' => $this->supplierA->id,
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->purchasingUser)->post(route('purchasing.store'), [
            'supplier_id' => $this->supplierB->id,
            'gudang_id' => $this->gudangPusat->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'termin',
            'items' => [
                [
                    'produk_id' => $this->produkA->id,
                    'qty' => 100,
                    'harga_total' => 1000000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('purchase_orders', [
            'supplier_id' => $this->supplierB->id,
        ]);
    }

    public function test_tc_32_po_with_item_having_nonaktif_status_is_rejected(): void
    {
        ProdukSupplier::create([
            'produk_id' => $this->produkA->id,
            'supplier_id' => $this->supplierA->id,
            'status' => 'nonaktif',
        ]);

        $response = $this->actingAs($this->purchasingUser)->post(route('purchasing.store'), [
            'supplier_id' => $this->supplierA->id,
            'gudang_id' => $this->gudangPusat->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'termin',
            'items' => [
                [
                    'produk_id' => $this->produkA->id,
                    'qty' => 100,
                    'harga_total' => 1000000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('items');
    }
}
