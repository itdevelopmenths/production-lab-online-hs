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
    private User $adminUser;
    private Supplier $supplierA;
    private Supplier $supplierB;
    private Gudang $gudangPusat;
    private Produk $produkA;
    private Produk $produkB;
    private Kategori $kategori;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->purchasingUser = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->adminUser = User::where('email', 'admin@heavenscent.id')->firstOrFail();

        $this->kategori = Kategori::firstOrCreate([
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
            'kategori_id' => $this->kategori->id,
            'tipe' => 'bahan',
            'satuan' => 'L',
            'satuan_order_moq' => 100,
            'is_active' => true,
        ]);

        $this->produkB = Produk::create([
            'sku' => 'MAT-02',
            'nama' => 'Bahan Baku B',
            'nama_produk' => 'Bahan Baku B',
            'kategori_id' => $this->kategori->id,
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

    public function test_store_produk_with_supplier_assignments_creates_pivot_and_sets_primary_supplier(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('produk.store'), [
            'sku' => 'MAT-MULTI-01',
            'nama_produk' => 'Bahan Campuran',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'bahan',
            'satuan' => 'l',
            'satuan_order_moq' => 10,
            'is_active' => 1,
            'supplier_assignments' => [
                ['supplier_id' => $this->supplierA->id, 'status' => 'aktif'],
                ['supplier_id' => $this->supplierB->id, 'status' => 'nonaktif'],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('produk.index'));

        $produk = Produk::where('sku', 'MAT-MULTI-01')->firstOrFail();
        $this->assertEquals($this->supplierA->id, $produk->supplier_id);
        $this->assertEquals(1, $produk->activeSuppliers()->count());
        $this->assertEquals(2, $produk->suppliers()->count());
        $this->assertDatabaseHas('produk_supplier', [
            'produk_id' => $produk->id,
            'supplier_id' => $this->supplierA->id,
            'status' => 'aktif',
        ]);
        $this->assertDatabaseHas('produk_supplier', [
            'produk_id' => $produk->id,
            'supplier_id' => $this->supplierB->id,
            'status' => 'nonaktif',
        ]);
    }

    public function test_update_produk_with_supplier_assignments_syncs_pivot(): void
    {
        ProdukSupplier::create([
            'produk_id' => $this->produkA->id,
            'supplier_id' => $this->supplierA->id,
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('produk.update', $this->produkA), [
            'sku' => $this->produkA->sku,
            'nama_produk' => 'Bahan Baku A Update',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'bahan',
            'satuan' => 'l',
            'satuan_order_moq' => 15,
            'is_active' => 1,
            'supplier_assignments' => [
                ['supplier_id' => $this->supplierB->id, 'status' => 'aktif'],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('produk.index'));

        $this->assertDatabaseHas('produk_supplier', [
            'produk_id' => $this->produkA->id,
            'supplier_id' => $this->supplierB->id,
            'status' => 'aktif',
        ]);
        $this->assertDatabaseMissing('produk_supplier', [
            'produk_id' => $this->produkA->id,
            'supplier_id' => $this->supplierA->id,
        ]);
    }

    public function test_store_supplier_with_produk_ids_syncs_pivot(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('supplier.store'), [
            'nama' => 'PT Pemasok Baru',
            'kategori' => 'lokal',
            'is_active' => 1,
            'produk_ids' => [$this->produkA->id, $this->produkB->id],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('supplier.index'));

        $supplier = Supplier::where('nama', 'PT Pemasok Baru')->firstOrFail();
        $this->assertEquals(2, $supplier->activeProduks()->count());
        $this->assertDatabaseHas('produk_supplier', [
            'supplier_id' => $supplier->id,
            'produk_id' => $this->produkA->id,
            'status' => 'aktif',
        ]);
        $this->assertDatabaseHas('produk_supplier', [
            'supplier_id' => $supplier->id,
            'produk_id' => $this->produkB->id,
            'status' => 'aktif',
        ]);
    }

    public function test_update_supplier_with_produk_ids_syncs_pivot(): void
    {
        ProdukSupplier::create([
            'produk_id' => $this->produkA->id,
            'supplier_id' => $this->supplierA->id,
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('supplier.update', $this->supplierA), [
            'nama' => $this->supplierA->nama,
            'kategori' => $this->supplierA->kategori,
            'is_active' => 1,
            'produk_ids' => [$this->produkB->id],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('supplier.index'));

        $this->assertDatabaseHas('produk_supplier', [
            'supplier_id' => $this->supplierA->id,
            'produk_id' => $this->produkB->id,
            'status' => 'aktif',
        ]);
        $this->assertDatabaseMissing('produk_supplier', [
            'supplier_id' => $this->supplierA->id,
            'produk_id' => $this->produkA->id,
        ]);
    }

    public function test_select_data_produk_filters_by_supplier_id(): void
    {
        ProdukSupplier::create([
            'produk_id' => $this->produkA->id,
            'supplier_id' => $this->supplierA->id,
            'status' => 'aktif',
        ]);

        ProdukSupplier::create([
            'produk_id' => $this->produkB->id,
            'supplier_id' => $this->supplierB->id,
            'status' => 'aktif',
        ]);

        // Filter by supplierA -> should only return produkA
        $response = $this->actingAs($this->adminUser)->getJson(route('produk.select-data', ['supplier_id' => $this->supplierA->id]));
        $response->assertOk();
        $items = collect($response->json('items'));
        $this->assertTrue($items->contains('id', $this->produkA->id));
        $this->assertFalse($items->contains('id', $this->produkB->id));

        // When status is nonaktif -> should not return produkA
        ProdukSupplier::where('produk_id', $this->produkA->id)
            ->where('supplier_id', $this->supplierA->id)
            ->update(['status' => 'nonaktif']);

        $response2 = $this->actingAs($this->adminUser)->getJson(route('produk.select-data', ['supplier_id' => $this->supplierA->id]));
        $response2->assertOk();
        $items2 = collect($response2->json('items'));
        $this->assertFalse($items2->contains('id', $this->produkA->id));
    }

    public function test_delete_supplier_rejected_if_has_po_with_status_berjalan(): void
    {
        PurchaseOrder::create([
            'no_po' => 'PO-BERJALAN-001',
            'supplier_id' => $this->supplierA->id,
            'gudang_id' => $this->gudangPusat->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'termin',
            'status' => 'berjalan',
            'subtotal_produk' => 500000,
            'grand_total' => 500000,
            'created_by' => $this->adminUser->id,
        ]);

        // JSON Request
        $responseJson = $this->actingAs($this->adminUser)->deleteJson(route('supplier.destroy', $this->supplierA));
        $responseJson->assertStatus(422);
        $responseJson->assertJsonFragment([
            'message' => "Supplier '{$this->supplierA->nama}' tidak dapat dihapus karena masih terikat pada Purchase Order aktif.",
        ]);

        // Supplier still exists
        $this->assertDatabaseHas('supplier', ['id' => $this->supplierA->id]);

        // Non-JSON Request
        $responseWeb = $this->actingAs($this->adminUser)->delete(route('supplier.destroy', $this->supplierA));
        $responseWeb->assertSessionHas('error');
        $this->assertDatabaseHas('supplier', ['id' => $this->supplierA->id]);
    }

    public function test_supplier_form_submits_selected_produk_ids_independently_of_search_filter(): void
    {
        ProdukSupplier::create([
            'produk_id' => $this->produkA->id,
            'supplier_id' => $this->supplierA->id,
            'status' => 'aktif',
        ]);

        foreach ([route('supplier.create'), route('supplier.edit', $this->supplierA)] as $url) {
            $response = $this->actingAs($this->adminUser)->get($url);
            $response->assertOk();
            $html = $response->getContent();

            // Checkbox visual tidak boleh memegang name="produk_ids[]" agar item tersembunyi oleh search filter tidak hilang saat submit
            $this->assertDoesNotMatchRegularExpression(
                '/<input\s+type="checkbox"[^>]*name="produk_ids\[\]"/s',
                $html
            );

            // Hidden input kanonik harus di-render dari selectedIds di luar filteredProducts
            $this->assertStringContainsString('<template x-for="id in selectedIds"', $html);
            $this->assertStringContainsString('<input type="hidden" name="produk_ids[]" :value="id">', $html);

            // Mendukung pencarian multi-keyword dan tab filter pilihan
            $this->assertStringContainsString('parsedClauses', $html);
            $this->assertStringContainsString("filterMode: 'all'", $html);
        }
    }

    public function test_supplier_form_old_input_handles_empty_and_multiple_produk_ids_cleanly(): void
    {
        ProdukSupplier::create([
            'produk_id' => $this->produkA->id,
            'supplier_id' => $this->supplierA->id,
            'status' => 'aktif',
        ]);

        // Validasi gagal saat 0 produk dipilih (hidden produk_ids = "") tidak boleh menyuntikkan ID 0 maupun revert ke relasi DB lama
        $emptyRedirect = $this->actingAs($this->adminUser)
            ->from(route('supplier.edit', $this->supplierA))
            ->followingRedirects()
            ->put(route('supplier.update', $this->supplierA), [
                'nama' => '',
                'kategori' => 'lokal',
                'is_active' => 1,
                'produk_ids' => '',
            ]);

        $emptyRedirect->assertOk();
        $emptyRedirect->assertSee('supplierProdukChecklist', false);
        $this->assertSame(
            1,
            preg_match("/supplierProdukChecklist\(JSON\.parse\('[^']+'\),\s*(?:JSON\.parse\('([^']+)'\)|(\[[^\]]*\]))\)/", $emptyRedirect->getContent(), $emptyMatches)
        );
        $emptyArg = !empty($emptyMatches[1]) ? json_decode('"' . $emptyMatches[1] . '"') : $emptyMatches[2];
        $this->assertSame([], json_decode($emptyArg, true));

        // Validasi gagal saat multiple produk dipilih harus tetap mempertahankan semua ID di old input
        $multiRedirect = $this->actingAs($this->adminUser)
            ->from(route('supplier.create'))
            ->followingRedirects()
            ->post(route('supplier.store'), [
                'nama' => '',
                'kategori' => 'lokal',
                'is_active' => 1,
                'produk_ids' => [$this->produkA->id, $this->produkB->id],
            ]);

        $multiRedirect->assertOk();
        $this->assertSame(
            1,
            preg_match("/supplierProdukChecklist\(JSON\.parse\('[^']+'\),\s*(?:JSON\.parse\('([^']+)'\)|(\[[^\]]*\]))\)/", $multiRedirect->getContent(), $multiMatches)
        );
        $multiArg = !empty($multiMatches[1]) ? json_decode('"' . $multiMatches[1] . '"') : $multiMatches[2];
        $this->assertSame(
            [$this->produkA->id, $this->produkB->id],
            json_decode($multiArg, true)
        );
    }
}
