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
}
