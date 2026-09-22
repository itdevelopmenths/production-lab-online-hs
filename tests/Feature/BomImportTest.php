<?php

namespace Tests\Feature;

use App\Models\Bom;
use App\Models\Produk;
use App\Models\User;
use App\Services\StampsBomImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BomImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
    }

    public function test_stamps_csv_importer_parses_recipes_with_forward_fill(): void
    {
        $importer = new StampsBomImporter();

        $csvContent = implode("\n", [
            'Item Name;Variant Name;Ingredient Name;Ingredient Quantity;Ingredient Unit;Ingredient Stock Alert',
            'Luxury Fragrance 50ml;Scandalous;Oil Scn;18.00;millilitre (ml);',
            ';;ALKOHOL;32.00;millilitre (ml);',
            ';;BOTOL MERAH;1.00;pieces (pcs);',
            ';;TUTUP MERAH GOLD;1.00;pieces (pcs);',
            ';;Ring Spray Semipress;1.00;pieces (pcs);',
            'Niche Signature;Ani X;Oil Ani;50.00;millilitre (ml);',
            ';;ALKOHOL;50.00;millilitre (ml);',
            ';;BOTOL NICHE BENING;1.00;pieces (pcs);',
        ]);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $csvContent);
        rewind($stream);

        $result = $importer->importFromHandle($stream);

        $this->assertEquals(2, $result['products_created']);
        $this->assertGreaterThanOrEqual(6, $result['materials_created']);
        $this->assertEquals(8, $result['boms_imported']);

        // Verifikasi produk jadi Luxury Fragrance 50ml / Scandalous
        $luxury = Produk::where('nama', 'Luxury Fragrance 50ml / Scandalous')->first();
        $this->assertNotNull($luxury);
        $this->assertEquals('produk_jadi', $luxury->tipe);

        // Verifikasi resep BOM untuk Luxury Fragrance
        $boms = $luxury->bom()->with('bahan')->get();
        $this->assertCount(5, $boms);

        $oilScn = $boms->firstWhere('bahan.nama', 'Oil Scn');
        $this->assertNotNull($oilScn);
        $this->assertEquals(18.0, (float) $oilScn->qty_per_unit);
        $this->assertEquals('ml', $oilScn->bahan->satuan);
    }

    public function test_bom_import_web_endpoint_handles_stamps_file(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $csvContent = implode("\n", [
            'Item Name;Variant Name;Ingredient Name;Ingredient Quantity;Ingredient Unit;Ingredient Stock Alert',
            'Travel Perfume;Scandalous;Oil Scn;5.00;millilitre (ml);',
            ';;ALKOHOL;5.00;millilitre (ml);',
            ';;Atomizer;1.00;pieces (pcs);',
        ]);

        $file = UploadedFile::fake()->createWithContent('stamps_export.csv', $csvContent);

        $response = $this->post(route('bom.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('bom.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('produk', [
            'nama' => 'Travel Perfume / Scandalous',
            'tipe' => 'produk_jadi',
        ]);
    }

    public function test_artisan_command_imports_stamps_bom(): void
    {
        $this->artisan('bom:import-stamps')
            ->assertExitCode(0);

        // Verifikasi resep salah satu varian dari file riil
        $this->assertDatabaseHas('produk', [
            'nama' => 'Gift Atomizer Marketing / Baccarat Rouge 540',
            'tipe' => 'produk_jadi',
        ]);

        // Verifikasi total resep terdaftar
        $this->assertGreaterThanOrEqual(369, Produk::where('tipe', 'produk_jadi')->count());
        $this->assertGreaterThanOrEqual(1665, Bom::count());
    }

    public function test_product_varian_bom_seeder_executes_successfully(): void
    {
        $this->seed(\Database\Seeders\ProductVarianBomSeeder::class);

        $this->assertDatabaseHas('produk', [
            'nama' => 'Gift Atomizer Marketing / Baccarat Rouge 540',
            'tipe' => 'produk_jadi',
            'is_active' => true,
        ]);

        $this->assertGreaterThanOrEqual(369, Produk::where('tipe', 'produk_jadi')->count());
        $this->assertGreaterThanOrEqual(1665, Bom::count());
    }

    public function test_stamps_csv_importer_validates_and_skips_duplicate_recipe_lines(): void
    {
        $importer = new StampsBomImporter();

        $csvContent = implode("\n", [
            'Item Name;Variant Name;Ingredient Name;Ingredient Quantity;Ingredient Unit;Ingredient Stock Alert',
            'Dup Product;Original;Oil Dup;10.00;millilitre (ml);',
            ';;ALKOHOL;40.00;millilitre (ml);',
            ';;Oil Dup;25.00;millilitre (ml);', // DUPLIKAT baris 2!
        ]);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $csvContent);
        rewind($stream);

        $result = $importer->importFromHandle($stream);

        $this->assertEquals(1, $result['products_created']);
        $this->assertEquals(2, $result['boms_imported']); // 2 unique recipes: Oil Dup & ALKOHOL
        $this->assertEquals(1, $result['duplicates_skipped']); // 1 duplicate skipped
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Data duplikat di dalam berkas', $result['errors'][0]);

        $product = Produk::where('nama', 'Dup Product / Original')->firstOrFail();
        $boms = $product->bom()->with('bahan')->get();
        $this->assertCount(2, $boms);

        $oil = $boms->firstWhere('bahan.nama', 'Oil Dup');
        $this->assertNotNull($oil);
        // Quantity dari baris pertama (10.00) dipertahankan, baris duplikat (25.00) dilewati
        $this->assertEquals(10.00, (float) $oil->qty_per_unit);
    }

    public function test_standard_csv_import_validates_and_skips_duplicate_lines(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $produk = Produk::create([
            'sku' => 'PRD-DUP-01',
            'nama_produk' => 'Parfum Test Dup',
            'nama' => 'Parfum Test Dup',
            'tipe' => 'produk_jadi',
            'satuan' => 'pcs',
            'faktor_konversi' => 1,
            'satuan_order_moq' => 1,
            'is_active' => true,
        ]);

        $bahan = Produk::create([
            'sku' => 'BAH-DUP-01',
            'nama_produk' => 'Pelarut Dup',
            'nama' => 'Pelarut Dup',
            'tipe' => 'bahan',
            'satuan' => 'ml',
            'faktor_konversi' => 1,
            'satuan_order_moq' => 10,
            'is_active' => true,
        ]);

        $csvContent = implode("\n", [
            'sku_produk_jadi;sku_bahan;qty_per_unit',
            'PRD-DUP-01;BAH-DUP-01;15.00',
            'PRD-DUP-01;BAH-DUP-01;30.00', // DUPLIKAT!
        ]);

        $file = UploadedFile::fake()->createWithContent('standard_bom.csv', $csvContent);

        $response = $this->post(route('bom.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('bom.index'));
        $response->assertSessionHas('success');

        // Resep terbuat 1 record dengan kuantitas baris pertama (15.00)
        $bom = Bom::where('produk_jadi_id', $produk->id)->where('bahan_id', $bahan->id)->first();
        $this->assertNotNull($bom);
        $this->assertEquals(15.00, (float) $bom->qty_per_unit);
    }

    public function test_bulk_import_validates_and_skips_duplicate_items(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $produk = Produk::create([
            'sku' => 'PRD-GRID-01',
            'nama_produk' => 'Grid Test Product',
            'nama' => 'Grid Test Product',
            'tipe' => 'produk_jadi',
            'satuan' => 'pcs',
            'faktor_konversi' => 1,
            'satuan_order_moq' => 1,
            'is_active' => true,
        ]);

        $bahan = Produk::create([
            'sku' => 'BAH-GRID-01',
            'nama_produk' => 'Grid Material 1',
            'nama' => 'Grid Material 1',
            'tipe' => 'bahan',
            'satuan' => 'ml',
            'faktor_konversi' => 1,
            'satuan_order_moq' => 10,
            'is_active' => true,
        ]);

        $response = $this->postJson(route('bom.import-bulk'), [
            'items' => [
                ['sku_produk_jadi' => 'PRD-GRID-01', 'sku_bahan' => 'BAH-GRID-01', 'qty_per_unit' => 12.5],
                ['sku_produk_jadi' => 'PRD-GRID-01', 'sku_bahan' => 'BAH-GRID-01', 'qty_per_unit' => 25.0], // DUPLIKAT!
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'imported_count' => 1,
            'duplicates_count' => 1,
        ]);

        $bom = Bom::where('produk_jadi_id', $produk->id)->where('bahan_id', $bahan->id)->first();
        $this->assertNotNull($bom);
        $this->assertEquals(12.50, (float) $bom->qty_per_unit);
    }
}
