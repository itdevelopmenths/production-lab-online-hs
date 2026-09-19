<?php

namespace Tests\Feature;

use App\Models\Bom;
use App\Models\Divisi;
use App\Models\Gudang;
use App\Models\MasterDataAudit;
use App\Models\Produk;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MasterDataAuditAndBomImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
        $this->seed();
    }

    public function test_audit_records_creation_update_and_deletion_on_master_data(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        // 1. Test Produk Audit
        $produk = Produk::create([
            'sku' => 'TEST-AUDIT-PRD',
            'nama' => 'Parfum Lavender Test',
            'tipe' => 'produk_jadi',
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $createAudit = MasterDataAudit::where('auditable_type', Produk::class)
            ->where('auditable_id', $produk->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($createAudit);
        $this->assertEquals($manager->id, $createAudit->user_id);
        $this->assertStringContainsString('Parfum Lavender Test', $createAudit->item_name);

        // Update
        $produk->update(['nama' => 'Parfum Lavender Test Edit']);
        $updateAudit = MasterDataAudit::where('auditable_type', Produk::class)
            ->where('auditable_id', $produk->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($updateAudit);
        $this->assertContains('nama', $updateAudit->changed_fields);
        $this->assertEquals('Parfum Lavender Test', $updateAudit->old_values['nama']);
        $this->assertEquals('Parfum Lavender Test Edit', $updateAudit->new_values['nama']);

        // Delete
        $produkId = $produk->id;
        $produk->delete();
        $deleteAudit = MasterDataAudit::where('auditable_type', Produk::class)
            ->where('auditable_id', $produkId)
            ->where('event', 'deleted')
            ->first();

        $this->assertNotNull($deleteAudit);

        // 2. Test Gudang Audit
        $gudang = Gudang::create([
            'kode' => 'GDG-TEST-AUDIT',
            'nama' => 'Gudang Ekstra Audit',
            'tipe' => 'operasional',
            'is_pusat' => false,
            'status' => 'aktif',
        ]);

        $gudangAudit = MasterDataAudit::where('auditable_type', Gudang::class)
            ->where('auditable_id', $gudang->id)
            ->where('event', 'created')
            ->first();
        $this->assertNotNull($gudangAudit);
        $this->assertStringContainsString('Gudang Ekstra Audit', $gudangAudit->item_name);

        // 3. Test Supplier Audit
        $supplier = Supplier::create([
            'nama' => 'PT Supplier Audit Test',
            'kategori' => 'bahan_baku',
            'termin_default' => 30,
            'is_active' => true,
        ]);
        $supplierAudit = MasterDataAudit::where('auditable_type', Supplier::class)
            ->where('auditable_id', $supplier->id)
            ->where('event', 'created')
            ->first();
        $this->assertNotNull($supplierAudit);

        // 4. Test UOM Audit
        $uom = Uom::create([
            'kode' => 'tst',
            'nama' => 'Test Unit',
            'kategori' => 'lainnya',
            'is_active' => true,
        ]);
        $uomAudit = MasterDataAudit::where('auditable_type', Uom::class)
            ->where('auditable_id', $uom->id)
            ->where('event', 'created')
            ->first();
        $this->assertNotNull($uomAudit);

        // 5. Test Divisi Audit
        $divisi = Divisi::create([
            'kode' => 'div_audit_test',
            'nama' => 'Divisi Audit Test',
            'color' => 'indigo',
            'is_active' => true,
        ]);
        $divisiAudit = MasterDataAudit::where('auditable_type', Divisi::class)
            ->where('auditable_id', $divisi->id)
            ->where('event', 'created')
            ->first();
        $this->assertNotNull($divisiAudit);
    }



    public function test_bom_import_page_renders_and_template_downloads(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        // 1. Visit Import Page
        $res = $this->get(route('bom.import-page'));
        $res->assertOk();
        $res->assertSee('Impor Formula Resep BOM');
        $res->assertSee('Template Excel (.xlsx)');
        $res->assertSee('Template CSV (.csv)');
        $res->assertViewHas('produkJadiList');
        $res->assertViewHas('bahanList');

        // 2. Download Template
        $templateRes = $this->get(route('bom.template'));
        $templateRes->assertOk();
        $templateRes->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $templateRes->streamedContent();
        $this->assertStringContainsString('sku_produk_jadi', $content);
        $this->assertStringContainsString('sku_bahan', $content);
        $this->assertStringContainsString('qty_per_unit', $content);
    }

    public function test_bom_import_bulk_saves_grid_items_and_logs_audit(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $jadi = Produk::create([
            'sku' => 'JADI-GRID-01',
            'nama' => 'Parfum Vanilla Rose 100ml',
            'tipe' => 'produk_jadi',
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $bahan = Produk::create([
            'sku' => 'BAHAN-GRID-01',
            'nama' => 'Konsentrat Vanilla Essential Oil',
            'tipe' => 'bahan',
            'satuan' => 'gr',
            'is_active' => true,
        ]);

        $payload = [
            'items' => [
                [
                    'sku_produk_jadi' => 'JADI-GRID-01',
                    'sku_bahan' => 'BAHAN-GRID-01',
                    'qty_per_unit' => 24.75,
                ],
            ],
        ];

        $response = $this->postJson(route('bom.import-bulk'), $payload);
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'imported_count' => 1,
        ]);

        // Verifikasi tersimpan di database
        $this->assertDatabaseHas('bom', [
            'produk_jadi_id' => $jadi->id,
            'bahan_id' => $bahan->id,
            'qty_per_unit' => 24.7500,
        ]);

        // Verifikasi audit log
        $audit = MasterDataAudit::where('auditable_type', Bom::class)
            ->where('event', 'imported')
            ->where('item_name', 'like', 'Grid Impor Resep BOM%')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($manager->id, $audit->user_id);
        $this->assertEquals(1, $audit->new_values['imported_count']);
    }

    public function test_bom_import_bulk_validates_mismatched_product_types(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $bahanSebagaiProdukJadi = Produk::create([
            'sku' => 'WRONG-TYPE-01',
            'nama' => 'Bahan Baku Yang Salah Jadi Produk Jadi',
            'tipe' => 'bahan',
            'satuan' => 'gr',
            'is_active' => true,
        ]);

        $bahanValid = Produk::create([
            'sku' => 'VALID-BAHAN-01',
            'nama' => 'Bahan Baku Valid',
            'tipe' => 'bahan',
            'satuan' => 'gr',
            'is_active' => true,
        ]);

        $payload = [
            'items' => [
                [
                    'sku_produk_jadi' => 'WRONG-TYPE-01', // harusnya produk_jadi
                    'sku_bahan' => 'VALID-BAHAN-01',
                    'qty_per_unit' => 10,
                ],
            ],
        ];

        $response = $this->postJson(route('bom.import-bulk'), $payload);
        $response->assertOk();
        $response->assertJson([
            'success' => false,
            'imported_count' => 0,
        ]);
        $this->assertNotEmpty($response->json('errors'));
    }

    public function test_bom_import_creates_bom_and_audit_log(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $jadi = Produk::create([
            'sku' => 'JADI-TEST-01',
            'nama' => 'Parfum Melati 50ml',
            'tipe' => 'produk_jadi',
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $bahan1 = Produk::create([
            'sku' => 'BAHAN-TEST-01',
            'nama' => 'Konsentrat Melati',
            'tipe' => 'bahan',
            'satuan' => 'gr',
            'is_active' => true,
        ]);

        $bahan2 = Produk::create([
            'sku' => 'BAHAN-TEST-02',
            'nama' => 'Alkohol 96%',
            'tipe' => 'bahan',
            'satuan' => 'gr',
            'is_active' => true,
        ]);

        $csvContent = "sku_produk_jadi;sku_bahan;qty_per_unit\n";
        $csvContent .= "JADI-TEST-01;BAHAN-TEST-01;12.5000\n";
        $csvContent .= "JADI-TEST-01;BAHAN-TEST-02;37.5000\n";

        $file = UploadedFile::fake()->createWithContent('resep.csv', $csvContent);

        $response = $this->post(route('bom.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('bom.index'));
        $response->assertSessionHas('success');

        // Verifikasi BOM tersimpan di database
        $this->assertDatabaseHas('bom', [
            'produk_jadi_id' => $jadi->id,
            'bahan_id' => $bahan1->id,
            'qty_per_unit' => 12.5000,
        ]);

        $this->assertDatabaseHas('bom', [
            'produk_jadi_id' => $jadi->id,
            'bahan_id' => $bahan2->id,
            'qty_per_unit' => 37.5000,
        ]);

        // Verifikasi event audit 'imported' tercatat
        $importAudit = MasterDataAudit::where('auditable_type', Bom::class)
            ->where('event', 'imported')
            ->latest('id')
            ->first();

        $this->assertNotNull($importAudit);
        $this->assertEquals($manager->id, $importAudit->user_id);
        $this->assertStringContainsString('Batch Impor', $importAudit->item_name);
    }
}
