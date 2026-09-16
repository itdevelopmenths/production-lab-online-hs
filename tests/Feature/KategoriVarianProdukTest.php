<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\User;
use App\Models\Varian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KategoriVarianProdukTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
    }

    public function test_manager_can_access_kategori_index_and_datatables(): void
    {
        $this->actingAs($this->manager);

        $response = $this->get(route('kategori.index'));
        $response->assertOk();
        $response->assertSee('Master Kategori Produk');

        $jsonResponse = $this->getJson(route('kategori.data'));
        $jsonResponse->assertOk();
        $jsonResponse->assertJsonStructure(['data']);
    }

    public function test_manager_can_create_and_update_kategori(): void
    {
        $this->actingAs($this->manager);

        $createResponse = $this->post(route('kategori.store'), [
            'nama' => 'Body Mist Tester',
        ]);
        $createResponse->assertRedirect(route('kategori.index'));
        $this->assertDatabaseHas('kategori', ['nama' => 'Body Mist Tester']);

        $kategori = Kategori::where('nama', 'Body Mist Tester')->firstOrFail();

        $updateResponse = $this->put(route('kategori.update', $kategori), [
            'nama' => 'Body Mist Premium',
        ]);
        $updateResponse->assertRedirect(route('kategori.index'));
        $this->assertDatabaseHas('kategori', ['id' => $kategori->id, 'nama' => 'Body Mist Premium']);
    }

    public function test_kategori_name_must_be_unique(): void
    {
        $this->actingAs($this->manager);

        $kategori = Kategori::create(['nama' => 'Kategori Duplikat']);

        $response = $this->post(route('kategori.store'), [
            'nama' => 'Kategori Duplikat',
        ]);
        $response->assertSessionHasErrors('nama');
    }

    public function test_kategori_cannot_be_deleted_if_has_varians_or_products(): void
    {
        $this->actingAs($this->manager);

        $kategori = Kategori::create(['nama' => 'Kategori Terikat']);
        $varian = Varian::create(['kategori_id' => $kategori->id, 'nama' => 'Varian Uji']);

        // Cannot delete because it has varian
        $deleteResponse = $this->deleteJson(route('kategori.destroy', $kategori));
        $deleteResponse->assertStatus(422);
        $this->assertDatabaseHas('kategori', ['id' => $kategori->id]);

        // Delete varian first
        $varian->delete();

        // Now attach a product to kategori
        $produk = Produk::create([
            'sku' => 'TEST-KAT-01',
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Produk Terikat Kategori',
            'tipe' => 'bahan',
            'satuan' => 'ml',
            'faktor_konversi' => 1.0000,
            'satuan_order_moq' => 10,
            'is_active' => true,
        ]);

        $deleteResponse2 = $this->deleteJson(route('kategori.destroy', $kategori));
        $deleteResponse2->assertStatus(422);
        $this->assertDatabaseHas('kategori', ['id' => $kategori->id]);

        // Once product is removed, kategori can be deleted
        $produk->delete();
        $deleteResponse3 = $this->deleteJson(route('kategori.destroy', $kategori));
        $deleteResponse3->assertOk();
        $this->assertDatabaseMissing('kategori', ['id' => $kategori->id]);
    }

    public function test_manager_can_access_varian_index_and_datatables(): void
    {
        $this->actingAs($this->manager);

        $response = $this->get(route('varian.index'));
        $response->assertOk();
        $response->assertSee('Master Varian Produk');

        $jsonResponse = $this->getJson(route('varian.data'));
        $jsonResponse->assertOk();
        $jsonResponse->assertJsonStructure(['data']);
    }

    public function test_manager_can_create_and_update_varian(): void
    {
        $this->actingAs($this->manager);

        $kategori = Kategori::create(['nama' => 'Aroma Parfum']);

        $createResponse = $this->post(route('varian.store'), [
            'kategori_id' => $kategori->id,
            'nama' => 'Vanilla Mist',
        ]);
        $createResponse->assertRedirect(route('varian.index'));
        $this->assertDatabaseHas('varian', [
            'kategori_id' => $kategori->id,
            'nama' => 'Vanilla Mist',
        ]);

        $varian = Varian::where('kategori_id', $kategori->id)->where('nama', 'Vanilla Mist')->firstOrFail();

        $updateResponse = $this->put(route('varian.update', $varian), [
            'kategori_id' => $kategori->id,
            'nama' => 'Vanilla Mist Deluxe',
        ]);
        $updateResponse->assertRedirect(route('varian.index'));
        $this->assertDatabaseHas('varian', [
            'id' => $varian->id,
            'nama' => 'Vanilla Mist Deluxe',
        ]);
    }

    public function test_varian_name_scoped_unique_per_kategori(): void
    {
        $this->actingAs($this->manager);

        $kategoriA = Kategori::create(['nama' => 'Kategori A']);
        $kategoriB = Kategori::create(['nama' => 'Kategori B']);

        // Create in Kategori A
        Varian::create(['kategori_id' => $kategoriA->id, 'nama' => 'Gold']);

        // Same name in Kategori A must fail
        $failResponse = $this->post(route('varian.store'), [
            'kategori_id' => $kategoriA->id,
            'nama' => 'Gold',
        ]);
        $failResponse->assertSessionHasErrors('nama');

        // Same name in Kategori B must SUCCEED (cross-category allowed)
        $successResponse = $this->post(route('varian.store'), [
            'kategori_id' => $kategoriB->id,
            'nama' => 'Gold',
        ]);
        $successResponse->assertRedirect(route('varian.index'));
        $this->assertDatabaseHas('varian', [
            'kategori_id' => $kategoriB->id,
            'nama' => 'Gold',
        ]);
    }

    public function test_varian_by_kategori_api_returns_scoped_records(): void
    {
        $this->actingAs($this->manager);

        $kategori = Kategori::create(['nama' => 'Eau de Cologne']);
        $v1 = Varian::create(['kategori_id' => $kategori->id, 'nama' => 'Alpha']);
        $v2 = Varian::create(['kategori_id' => $kategori->id, 'nama' => 'Beta']);

        $kategoriOther = Kategori::create(['nama' => 'Other Category']);
        Varian::create(['kategori_id' => $kategoriOther->id, 'nama' => 'Omega']);

        $response = $this->getJson(route('varian.by-kategori', $kategori->id));
        $response->assertOk();
        $data = $response->json();

        $this->assertCount(2, $data);
        $this->assertEquals(['Alpha', 'Beta'], array_column($data, 'nama'));
    }

    public function test_manager_can_create_product_with_kategori_varian_and_faktor_konversi(): void
    {
        $this->actingAs($this->manager);

        $kategori = Kategori::create(['nama' => 'Parfum Extrait']);
        $varian = Varian::create(['kategori_id' => $kategori->id, 'nama' => 'Rouge 540']);

        $response = $this->post(route('produk.store'), [
            'sku' => 'PRD-EXT-001',
            'kategori_id' => $kategori->id,
            'varian_id' => $varian->id,
            'nama_produk' => 'Extrait de Parfum',
            'tipe' => 'produk_jadi',
            'satuan' => 'pcs',
            'faktor_konversi' => 1.0000,
            'satuan_order_moq' => 12,
            'profil_analisa' => null,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('produk.index'));

        $produk = Produk::where('sku', 'PRD-EXT-001')->firstOrFail();
        $this->assertEquals('Extrait de Parfum', $produk->nama_produk);
        $this->assertEquals($kategori->id, $produk->kategori_id);
        $this->assertEquals($varian->id, $produk->varian_id);
        $this->assertEquals(1.0000, (float) $produk->faktor_konversi);

        // Auto-generated full name
        $this->assertEquals('Extrait de Parfum Rouge 540', $produk->nama);
    }

    public function test_product_rejects_varian_from_different_kategori(): void
    {
        $this->actingAs($this->manager);

        $kategoriA = Kategori::create(['nama' => 'Kategori Alpha']);
        $kategoriB = Kategori::create(['nama' => 'Kategori Beta']);
        $varianB = Varian::create(['kategori_id' => $kategoriB->id, 'nama' => 'Varian of B']);

        // Attempt to create product with Kategori A but Varian from Kategori B
        $response = $this->post(route('produk.store'), [
            'sku' => 'PRD-MISMATCH-01',
            'kategori_id' => $kategoriA->id,
            'varian_id' => $varianB->id,
            'nama_produk' => 'Mismatch Product',
            'tipe' => 'produk_jadi',
            'satuan' => 'pcs',
            'faktor_konversi' => 1.0000,
            'satuan_order_moq' => 10,
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('varian_id');
    }

    public function test_product_without_varian_uses_base_nama_produk(): void
    {
        $this->actingAs($this->manager);

        $kategori = Kategori::create(['nama' => 'Pelarut']);

        $response = $this->post(route('produk.store'), [
            'sku' => 'BAH-SOLV-01',
            'kategori_id' => $kategori->id,
            'varian_id' => null,
            'nama_produk' => 'Solvent DPG',
            'tipe' => 'bahan',
            'satuan' => 'ml',
            'faktor_konversi' => 1.0000,
            'satuan_order_moq' => 100,
            'profil_analisa' => 'lokal',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('produk.index'));

        $produk = Produk::where('sku', 'BAH-SOLV-01')->firstOrFail();
        $this->assertEquals('Solvent DPG', $produk->nama_produk);
        $this->assertNull($produk->varian_id);
        $this->assertEquals('Solvent DPG', $produk->nama);
    }
}
