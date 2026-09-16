<?php

namespace Tests\Feature;

use App\Models\Produk;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UomManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
    }

    public function test_manager_can_access_uom_index_and_datatables(): void
    {
        $this->actingAs($this->manager);

        $response = $this->get(route('uom.index'));
        $response->assertOk();
        $response->assertSee('Master Satuan Unit (UOM)');

        $jsonResponse = $this->getJson(route('uom.data'));
        $jsonResponse->assertOk();
        $jsonResponse->assertJsonStructure(['data']);
    }

    public function test_manager_can_create_new_uom(): void
    {
        $this->actingAs($this->manager);

        $response = $this->post(route('uom.store'), [
            'kode' => 'vial',
            'nama' => 'Vial Sampel',
            'kategori' => 'Kemasan',
            'deskripsi' => 'Botol mini sampel 2ml',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('uom.index'));
        $this->assertDatabaseHas('uom', [
            'kode' => 'vial',
            'nama' => 'Vial Sampel',
            'kategori' => 'Kemasan',
        ]);
    }

    public function test_uom_code_must_be_unique(): void
    {
        $this->actingAs($this->manager);

        $response = $this->post(route('uom.store'), [
            'kode' => 'pcs',
            'nama' => 'Pieces Duplicate',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('kode');
    }

    public function test_manager_can_update_uom(): void
    {
        $this->actingAs($this->manager);

        $uom = Uom::where('kode', 'botol')->firstOrFail();

        $response = $this->put(route('uom.update', $uom), [
            'kode' => 'btl',
            'nama' => 'Botol Kemasan Primer',
            'kategori' => 'Kemasan',
            'deskripsi' => 'Diperbarui',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('uom.index'));
        $this->assertDatabaseHas('uom', [
            'id' => $uom->id,
            'kode' => 'btl',
            'nama' => 'Botol Kemasan Primer',
        ]);
    }

    public function test_uom_cannot_be_deleted_if_used_by_product(): void
    {
        $this->actingAs($this->manager);

        $uom = Uom::where('kode', 'pcs')->firstOrFail();
        $this->assertTrue(Produk::where('satuan', 'pcs')->exists());

        $response = $this->deleteJson(route('uom.destroy', $uom));
        $response->assertStatus(422);
        $this->assertDatabaseHas('uom', ['id' => $uom->id]);
    }

    public function test_unused_uom_can_be_deleted(): void
    {
        $this->actingAs($this->manager);

        $uom = Uom::create([
            'kode' => 'temp-uom',
            'nama' => 'Temporary Unit',
            'is_active' => true,
        ]);

        $response = $this->deleteJson(route('uom.destroy', $uom));
        $response->assertOk();
        $this->assertDatabaseMissing('uom', ['id' => $uom->id]);
    }

    public function test_product_form_requires_valid_uom(): void
    {
        $this->actingAs($this->manager);

        $kategori = \App\Models\Kategori::firstOrCreate(['nama' => 'Bahan Baku']);

        // Invalid UOM should fail validation
        $response = $this->post(route('produk.store'), [
            'sku' => 'TEST-SKU-001',
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Produk Uji UOM',
            'tipe' => 'bahan',
            'satuan' => 'satuan_tidak_ada',
            'faktor_konversi' => 1.0000,
            'satuan_order_moq' => 10,
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('satuan');

        // Valid UOM should succeed
        $validResponse = $this->post(route('produk.store'), [
            'sku' => 'TEST-SKU-001',
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Produk Uji UOM',
            'tipe' => 'bahan',
            'satuan' => 'ml',
            'faktor_konversi' => 1.0000,
            'satuan_order_moq' => 10,
            'is_active' => 1,
        ]);

        $validResponse->assertRedirect(route('produk.index'));
        $this->assertDatabaseHas('produk', [
            'sku' => 'TEST-SKU-001',
            'satuan' => 'ml',
        ]);
    }
}
