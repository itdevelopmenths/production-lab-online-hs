<?php

namespace Tests\Feature;

use App\Models\AnalisaImpor;
use App\Models\AnalisaLokal;
use App\Models\Kategori;
use App\Models\KlasifikasiAbc;
use App\Models\LeadTimeImpor;
use App\Models\LeadTimeLokal;
use App\Models\MasterDataAudit;
use App\Models\Produk;
use App\Models\RekomendasiOrderLokal;
use App\Models\RiwayatAnalisa;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AnalisaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SkuKlasifikasiAbcTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchasing;
    protected User $manager;
    protected User $gudang;
    protected Kategori $kategori;
    protected Supplier $supplierImpor;
    protected Supplier $supplierLokal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Model::preventLazyLoading(true);

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $this->kategori = Kategori::first() ?? Kategori::create(['nama' => 'Kemasan Uji']);
        $this->supplierImpor = Supplier::where('kategori', 'impor')->firstOrFail();
        $this->supplierLokal = Supplier::where('kategori', 'lokal')->firstOrFail();
    }

    public function test_produk_and_klasifikasi_abc_relations_and_migration_column_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('produk', 'klasifikasi_abc_id'));

        $abcA = KlasifikasiAbc::where('kode', 'a')->firstOrFail();

        $this->actingAs($this->purchasing);
        $produk = Produk::create([
            'sku' => 'TEST-REL-ABC',
            'nama_produk' => 'Botol Relasi ABC',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'kemas',
            'satuan' => 'pcs',
            'satuan_order_moq' => 100,
            'profil_analisa' => 'impor',
            'klasifikasi_abc_id' => $abcA->id,
            'supplier_id' => $this->supplierImpor->id,
            'is_active' => true,
        ]);

        $loadedProduk = Produk::with('klasifikasiAbc')->findOrFail($produk->id);
        $this->assertNotNull($loadedProduk->klasifikasiAbc);
        $this->assertEquals('a', $loadedProduk->klasifikasiAbc->kode);

        $loadedAbc = KlasifikasiAbc::with('produks')->findOrFail($abcA->id);
        $this->assertTrue($loadedAbc->produks->contains('id', $produk->id));

        $audit = MasterDataAudit::where('auditable_type', Produk::class)
            ->where('auditable_id', $produk->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertContains('klasifikasi_abc_id', $audit->changed_fields ?? []);
    }

    public function test_generate_impor_includes_produk_with_profil_analisa_impor_and_uses_master_abc(): void
    {
        $abcB = KlasifikasiAbc::where('kode', 'b')->firstOrFail();

        $produk = Produk::create([
            'sku' => 'IMP-NEW-01',
            'nama_produk' => 'Pump Spray Impor Baru',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'kemas',
            'satuan' => 'pcs',
            'satuan_order_moq' => 500,
            'profil_analisa' => 'impor',
            'klasifikasi_abc_id' => $abcB->id,
            'supplier_id' => $this->supplierImpor->id,
            'is_active' => true,
        ]);

        $service = app(AnalisaService::class);
        $service->generateImpor(null, $this->purchasing->id);

        $ai = AnalisaImpor::where('produk_id', $produk->id)->first();
        $this->assertNotNull($ai);
        $this->assertEquals($abcB->id, $ai->klasifikasi_abc_id);
        $this->assertEquals('b', $ai->klasifikasi_abc);
        $this->assertEquals(2, (int) $ai->tambahan_buffer_hari);
    }

    public function test_unassigning_abc_keeps_klasifikasi_abc_id_null_after_regenerate(): void
    {
        $abcA = KlasifikasiAbc::where('kode', 'a')->firstOrFail();

        $produk = Produk::create([
            'sku' => 'IMP-UNASSIGN-01',
            'nama_produk' => 'Botol Unassign Uji',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'kemas',
            'satuan' => 'pcs',
            'satuan_order_moq' => 100,
            'profil_analisa' => 'impor',
            'klasifikasi_abc_id' => $abcA->id,
            'supplier_id' => $this->supplierImpor->id,
            'is_active' => true,
        ]);

        $service = app(AnalisaService::class);
        $service->generateImpor($produk->id, $this->purchasing->id);

        $service->syncProdukKlasifikasiAbc($produk->fresh(), null, [
            'ensure_analisa_row' => true,
            'user_id' => $this->purchasing->id,
        ]);

        // Regenerate again; must NOT auto-attach to Class C record ID
        $service->generateImpor($produk->id, $this->purchasing->id);

        $produk->refresh();
        $ai = AnalisaImpor::where('produk_id', $produk->id)->firstOrFail();

        $this->assertNull($produk->klasifikasi_abc_id);
        $this->assertNull($ai->klasifikasi_abc_id);
        $this->assertEquals('c', $ai->klasifikasi_abc);
        $this->assertEquals(0, (int) $ai->tambahan_buffer_hari);
    }

    public function test_switching_profile_cleans_stale_working_tables_and_isolates_summaries(): void
    {
        $abcA = KlasifikasiAbc::where('kode', 'a')->firstOrFail();

        $produk = Produk::create([
            'sku' => 'SW-PROF-01',
            'nama_produk' => 'Bahan Pindah Profil',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'bahan',
            'satuan' => 'ml',
            'satuan_order_moq' => 1000,
            'profil_analisa' => 'lokal',
            'supplier_id' => $this->supplierLokal->id,
            'is_active' => true,
        ]);

        $service = app(AnalisaService::class);
        $service->generateLokal($produk->id, $this->purchasing->id);
        $this->assertDatabaseHas('analisa_lokal', ['produk_id' => $produk->id]);
        $this->assertDatabaseHas('rekomendasi_order_lokal', ['produk_id' => $produk->id]);

        // Move Lokal -> Impor with ABC Class A
        $service->addSkuToAnalisa($produk->fresh(), 'impor', $abcA->id, ['out' => 1220], $this->purchasing->id);

        $produk->refresh();
        $this->assertEquals('impor', $produk->profil_analisa);
        $this->assertEquals($abcA->id, $produk->klasifikasi_abc_id);
        $this->assertDatabaseMissing('analisa_lokal', ['produk_id' => $produk->id]);
        $this->assertDatabaseMissing('rekomendasi_order_lokal', ['produk_id' => $produk->id]);
        $this->assertDatabaseMissing('lead_time_lokal', ['produk_id' => $produk->id]);
        $this->assertDatabaseHas('analisa_impor', [
            'produk_id' => $produk->id,
            'klasifikasi_abc_id' => $abcA->id,
            'klasifikasi_abc' => 'a',
        ]);

        // Verify finalisasi('bahan_lokal') excludes it and finalisasi('bahan_impor') includes it
        $snapLokal = $service->finalisasi('bahan_lokal', $this->purchasing->id);
        $this->assertDatabaseMissing('riwayat_analisa', [
            'session_id' => $snapLokal['session_id'],
            'item_label' => 'SW-PROF-01',
        ]);
        $snapImpor = $service->finalisasi('bahan_impor', $this->purchasing->id);
        $this->assertDatabaseHas('riwayat_analisa', [
            'session_id' => $snapImpor['session_id'],
            'item_label' => 'SW-PROF-01',
        ]);

        // Move Impor -> Lokal
        $service->addSkuToAnalisa($produk->fresh(), 'lokal', null, ['terjual_rata_rata_4bulan' => 3000], $this->purchasing->id);

        $produk->refresh();
        $this->assertEquals('lokal', $produk->profil_analisa);
        $this->assertNull($produk->klasifikasi_abc_id);
        $this->assertDatabaseMissing('analisa_impor', ['produk_id' => $produk->id]);
        $this->assertDatabaseMissing('lead_time_impor', ['produk_id' => $produk->id]);
        $this->assertDatabaseHas('analisa_lokal', ['produk_id' => $produk->id]);
    }

    public function test_assign_klasifikasi_abc_rejects_produk_jadi(): void
    {
        $abcA = KlasifikasiAbc::where('kode', 'a')->firstOrFail();

        $pj = Produk::create([
            'sku' => 'PJ-REJECT-01',
            'nama_produk' => 'Parfum Jadi Uji',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'produk_jadi',
            'satuan' => 'pcs',
            'satuan_order_moq' => 12,
            'is_active' => true,
        ]);

        $service = app(AnalisaService::class);
        $this->expectException(ValidationException::class);
        $service->syncProdukKlasifikasiAbc($pj, $abcA->id);
    }

    public function test_master_produk_create_and_edit_with_klasifikasi_abc_syncs_analisa_impor(): void
    {
        $abcA = KlasifikasiAbc::where('kode', 'a')->firstOrFail();
        $abcB = KlasifikasiAbc::where('kode', 'b')->firstOrFail();

        $this->actingAs($this->purchasing);

        $createPage = $this->get(route('produk.create'));
        $createPage->assertOk()
            ->assertSee('klasifikasi_abc_id')
            ->assertDontSee('max-w-4xl');

        $storeRes = $this->post(route('produk.store'), [
            'sku' => 'MP-ABC-01',
            'nama_produk' => 'Botol Master Produk ABC',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'kemas',
            'satuan' => 'pcs',
            'satuan_order_moq' => 250,
            'profil_analisa' => 'impor',
            'klasifikasi_abc_id' => $abcA->id,
            'is_active' => 1,
            'supplier_assignments' => [
                ['supplier_id' => $this->supplierImpor->id, 'status' => 'aktif'],
            ],
        ]);
        $storeRes->assertSessionHasNoErrors()->assertRedirect(route('produk.index'));

        $produk = Produk::where('sku', 'MP-ABC-01')->firstOrFail();
        $this->assertEquals('impor', $produk->profil_analisa);
        $this->assertEquals($abcA->id, $produk->klasifikasi_abc_id);
        // Anti-goal: creating in Master Produk must NOT eagerly create empty analisa_impor row
        $this->assertDatabaseMissing('analisa_impor', ['produk_id' => $produk->id]);

        // Generate Analisa Impor picks it up with Class A
        app(AnalisaService::class)->generateImpor(null, $this->purchasing->id);
        $this->assertDatabaseHas('analisa_impor', [
            'produk_id' => $produk->id,
            'klasifikasi_abc_id' => $abcA->id,
            'klasifikasi_abc' => 'a',
            'tambahan_buffer_hari' => 4,
        ]);

        // Update via Master Produk to Class B -> syncs existing AnalisaImpor
        $updateRes = $this->put(route('produk.update', $produk), [
            'sku' => 'MP-ABC-01',
            'nama_produk' => 'Botol Master Produk ABC',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'kemas',
            'satuan' => 'pcs',
            'satuan_order_moq' => 250,
            'profil_analisa' => 'impor',
            'klasifikasi_abc_id' => $abcB->id,
            'is_active' => 1,
            'supplier_assignments' => [
                ['supplier_id' => $this->supplierImpor->id, 'status' => 'aktif'],
            ],
        ]);
        $updateRes->assertSessionHasNoErrors()->assertRedirect(route('produk.index'));

        $this->assertEquals($abcB->id, $produk->fresh()->klasifikasi_abc_id);
        $this->assertDatabaseHas('analisa_impor', [
            'produk_id' => $produk->id,
            'klasifikasi_abc_id' => $abcB->id,
            'klasifikasi_abc' => 'b',
            'tambahan_buffer_hari' => 2,
        ]);

        // Switch profil_analisa to lokal -> clears klasifikasi_abc_id and removes stale AnalisaImpor row
        $switchRes = $this->put(route('produk.update', $produk), [
            'sku' => 'MP-ABC-01',
            'nama_produk' => 'Botol Master Produk ABC',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'kemas',
            'satuan' => 'pcs',
            'satuan_order_moq' => 250,
            'profil_analisa' => 'lokal',
            'klasifikasi_abc_id' => $abcB->id, // should be coerced to null when lokal
            'is_active' => 1,
            'supplier_assignments' => [
                ['supplier_id' => $this->supplierLokal->id, 'status' => 'aktif'],
            ],
        ]);
        $switchRes->assertSessionHasNoErrors()->assertRedirect(route('produk.index'));

        $this->assertNull($produk->fresh()->klasifikasi_abc_id);
        $this->assertDatabaseMissing('analisa_impor', ['produk_id' => $produk->id]);
    }

    public function test_produk_datatable_endpoint_eager_loads_klasifikasi_abc_without_lazy_loading(): void
    {
        $abcA = KlasifikasiAbc::where('kode', 'a')->firstOrFail();
        Produk::create([
            'sku' => 'MP-DT-01',
            'nama_produk' => 'Produk DataTable ABC',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'bahan',
            'satuan' => 'ml',
            'satuan_order_moq' => 100,
            'profil_analisa' => 'impor',
            'klasifikasi_abc_id' => $abcA->id,
            'is_active' => true,
        ]);

        $res = $this->actingAs($this->purchasing)->getJson(route('produk.data'));
        $res->assertOk();
        $res->assertJsonStructure([
            'data' => [
                '*' => ['id', 'sku', 'nama', 'klasifikasi_abc_badge'],
            ],
        ]);
    }

    public function test_klasifikasi_abc_assign_and_unassign_skus_end_to_end(): void
    {
        $abcA = KlasifikasiAbc::where('kode', 'a')->firstOrFail();
        $abcC = KlasifikasiAbc::where('kode', 'c')->firstOrFail();

        $lokalSku = Produk::create([
            'sku' => 'ABC-PROMO-01',
            'nama_produk' => 'Bahan Lokal Dipromosi ke ABC',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'bahan',
            'satuan' => 'ml',
            'satuan_order_moq' => 100,
            'profil_analisa' => 'lokal',
            'supplier_id' => $this->supplierLokal->id,
            'is_active' => true,
        ]);

        $pj = Produk::create([
            'sku' => 'ABC-PJ-EX-01',
            'nama_produk' => 'Produk Jadi Dikecualikan',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'produk_jadi',
            'satuan' => 'pcs',
            'satuan_order_moq' => 12,
            'is_active' => true,
        ]);

        $service = app(AnalisaService::class);
        $service->generateLokal($lokalSku->id, $this->purchasing->id);
        $service->generateImpor(null, $this->purchasing->id); // working table analisa_impor initialized

        $this->actingAs($this->purchasing);

        // 1. available-skus excludes produk_jadi
        $availRes = $this->getJson(route('klasifikasi-abc.available-skus'));
        $availRes->assertOk();
        $availIds = collect($availRes->json('data'))->pluck('id')->all();
        $this->assertContains($lokalSku->id, $availIds);
        $this->assertNotContains($pj->id, $availIds);

        // 2. assignSkus rejects produk_jadi
        $this->postJson(route('klasifikasi-abc.assign-skus', $abcA), [
            'produk_ids' => [$pj->id],
        ])->assertStatus(422);

        // 3. assignSkus assigns lokalSku to Class A, promotes to impor, cleans AnalisaLokal, syncs AnalisaImpor
        $assignRes = $this->postJson(route('klasifikasi-abc.assign-skus', $abcA), [
            'produk_ids' => [$lokalSku->id],
        ]);
        $assignRes->assertOk()->assertJsonPath('success', true);

        $lokalSku->refresh();
        $this->assertEquals('impor', $lokalSku->profil_analisa);
        $this->assertEquals($abcA->id, $lokalSku->klasifikasi_abc_id);
        $this->assertDatabaseMissing('analisa_lokal', ['produk_id' => $lokalSku->id]);
        $this->assertDatabaseHas('analisa_impor', [
            'produk_id' => $lokalSku->id,
            'klasifikasi_abc_id' => $abcA->id,
            'klasifikasi_abc' => 'a',
        ]);

        // 4. skus endpoint lists it under Class A
        $skusARes = $this->getJson(route('klasifikasi-abc.skus', $abcA));
        $skusARes->assertOk();
        $this->assertContains($lokalSku->id, collect($skusARes->json('data'))->pluck('id')->all());

        // 5. destroy Class A blocked while Produk references it
        $this->deleteJson(route('klasifikasi-abc.destroy', $abcA))->assertStatus(422);

        // 6. unassignSku removes it from Class A AND does NOT attach it to Class C
        $unassignRes = $this->deleteJson(route('klasifikasi-abc.unassign-sku', [$abcA, $lokalSku]));
        $unassignRes->assertOk()->assertJsonPath('success', true);

        $lokalSku->refresh();
        $this->assertNull($lokalSku->klasifikasi_abc_id);
        $this->assertDatabaseHas('analisa_impor', [
            'produk_id' => $lokalSku->id,
            'klasifikasi_abc_id' => null,
            'klasifikasi_abc' => 'c',
            'tambahan_buffer_hari' => 0,
        ]);

        $skusAAfter = collect($this->getJson(route('klasifikasi-abc.skus', $abcA))->json('data'))->pluck('id')->all();
        $skusCAfter = collect($this->getJson(route('klasifikasi-abc.skus', $abcC))->json('data'))->pluck('id')->all();
        $this->assertNotContains($lokalSku->id, $skusAAfter);
        $this->assertNotContains($lokalSku->id, $skusCAfter);
    }

    public function test_klasifikasi_abc_rbac_uses_analisa_permissions(): void
    {
        $abcB = KlasifikasiAbc::where('kode', 'b')->firstOrFail();

        $analisaOnlyRole = Role::create([
            'name' => 'analisa_specialist',
            'guard_name' => 'web',
            'display_name' => 'Analisa Specialist',
            'is_system' => false,
        ]);
        $analisaOnlyRole->syncPermissions(['analisa.view', 'analisa.manage']);

        $analisaUser = User::factory()->create();
        $analisaUser->assignRole($analisaOnlyRole);

        $sku = Produk::create([
            'sku' => 'RBAC-ABC-01',
            'nama_produk' => 'SKU Uji RBAC ABC',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'kemas',
            'satuan' => 'pcs',
            'satuan_order_moq' => 50,
            'profil_analisa' => 'impor',
            'is_active' => true,
        ]);

        // User with analisa.view + analisa.manage (NO produk.view) CAN access klasifikasi-abc.*
        $this->actingAs($analisaUser);
        $this->get(route('klasifikasi-abc.index'))->assertOk()->assertSee(route('klasifikasi-abc.index'));
        $this->getJson(route('klasifikasi-abc.options'))->assertOk();
        $this->getJson(route('klasifikasi-abc.available-skus'))->assertOk();
        $this->postJson(route('klasifikasi-abc.assign-skus', $abcB), [
            'produk_ids' => [$sku->id],
        ])->assertOk();

        // User with produk.view WITHOUT analisa.view (gudang) CANNOT access klasifikasi-abc.* or see sidebar link
        $this->actingAs($this->gudang);
        $this->get(route('klasifikasi-abc.index'))->assertForbidden();
        $this->getJson(route('klasifikasi-abc.options'))->assertForbidden();
        $this->get(route('produk.index'))->assertOk()->assertDontSee(route('klasifikasi-abc.index'));
    }

    public function test_analisa_add_sku_allows_choosing_impor_with_abc_or_lokal_and_isolates_tabs(): void
    {
        $abcA = KlasifikasiAbc::where('kode', 'a')->firstOrFail();

        $sku = Produk::create([
            'sku' => 'ANL-PICK-01',
            'nama_produk' => 'Bahan Fleksibel Impor/Lokal',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'bahan',
            'satuan' => 'ml',
            'satuan_order_moq' => 100,
            'profil_analisa' => 'lokal',
            'supplier_id' => $this->supplierLokal->id,
            'is_active' => true,
        ]);

        $pj = Produk::create([
            'sku' => 'ANL-PJ-EX-01',
            'nama_produk' => 'Produk Jadi Analisa Exclude',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'produk_jadi',
            'satuan' => 'pcs',
            'satuan_order_moq' => 12,
            'is_active' => true,
        ]);

        $this->actingAs($this->purchasing);

        // 1. available-skus excludes produk_jadi
        $availRes = $this->getJson(route('analisa.available-skus'));
        $availRes->assertOk();
        $availIds = collect($availRes->json('data'))->pluck('id')->all();
        $this->assertContains($sku->id, $availIds);
        $this->assertNotContains($pj->id, $availIds);

        // 2. Reject produk_jadi on add-sku
        $this->postJson(route('analisa.add-sku'), [
            'produk_id' => $pj->id,
            'target_profil' => 'impor',
            'klasifikasi_abc_id' => $abcA->id,
        ])->assertStatus(422);

        // 3. First seed Lokal data so switching to Impor verifies strict cleanup & tab isolation
        app(AnalisaService::class)->generateLokal(null, $this->purchasing->id);
        $this->assertContains($sku->id, collect($this->getJson(route('analisa.lokal.data'))->json('data'))->pluck('produk_id')->all());

        // 4. Add/move SKU into Analisa Impor with Klasifikasi ABC = A
        $addImporRes = $this->postJson(route('analisa.add-sku'), [
            'produk_id' => $sku->id,
            'target_profil' => 'impor',
            'klasifikasi_abc_id' => $abcA->id,
            'out' => 2440,
            'review_period' => 30,
            'harga_per_satuan' => 2500,
        ]);
        $addImporRes->assertOk()->assertJsonPath('success', true);

        $sku->refresh();
        $this->assertEquals('impor', $sku->profil_analisa);
        $this->assertEquals($abcA->id, $sku->klasifikasi_abc_id);

        $imporIds = collect($this->getJson(route('analisa.impor.data'))->json('data'))->pluck('produk_id')->all();
        $lokalIds = collect($this->getJson(route('analisa.lokal.data'))->json('data'))->pluck('produk_id')->all();
        $this->assertContains($sku->id, $imporIds);
        $this->assertNotContains($sku->id, $lokalIds);

        // 5. Move SKU back into Analisa Lokal
        $addLokalRes = $this->postJson(route('analisa.add-sku'), [
            'produk_id' => $sku->id,
            'target_profil' => 'lokal',
            'terjual_rata_rata_4bulan' => 3000,
            'review_period' => 15,
            'tambahan_buffer_hari' => 2,
        ]);
        $addLokalRes->assertOk()->assertJsonPath('success', true);

        $sku->refresh();
        $this->assertEquals('lokal', $sku->profil_analisa);
        $this->assertNull($sku->klasifikasi_abc_id);

        $imporIdsAfter = collect($this->getJson(route('analisa.impor.data'))->json('data'))->pluck('produk_id')->all();
        $lokalIdsAfter = collect($this->getJson(route('analisa.lokal.data'))->json('data'))->pluck('produk_id')->all();
        $this->assertNotContains($sku->id, $imporIdsAfter);
        $this->assertContains($sku->id, $lokalIdsAfter);
    }

    public function test_update_manual_impor_partial_abc_change_preserves_metrics_and_syncs_produk(): void
    {
        $abcA = KlasifikasiAbc::where('kode', 'a')->firstOrFail();
        $abcB = KlasifikasiAbc::where('kode', 'b')->firstOrFail();

        $sku = Produk::create([
            'sku' => 'ANL-QUICK-01',
            'nama_produk' => 'Botol Quick ABC',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'kemas',
            'satuan' => 'pcs',
            'satuan_order_moq' => 100,
            'profil_analisa' => 'impor',
            'klasifikasi_abc_id' => $abcA->id,
            'supplier_id' => $this->supplierImpor->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->purchasing);

        $this->postJson(route('analisa.update-manual-impor'), [
            'produk_id' => $sku->id,
            'lead_time_average' => 30,
            'lead_time_max' => 40,
            'out' => 5000,
            'review_period' => 30,
            'klasifikasi_abc_id' => $abcA->id,
            'stok_saat_ini' => 1200,
            'inbound_before_eta' => 300,
            'harga_per_satuan' => 1500,
        ])->assertOk();

        // Partial update changing only klasifikasi_abc_id to B -> preserves out/stok/harga & syncs Produk
        $this->postJson(route('analisa.update-manual-impor'), [
            'produk_id' => $sku->id,
            'klasifikasi_abc_id' => $abcB->id,
        ])->assertOk();

        $sku->refresh();
        $ai = AnalisaImpor::where('produk_id', $sku->id)->firstOrFail();
        $this->assertEquals($abcB->id, $sku->klasifikasi_abc_id);
        $this->assertEquals($abcB->id, $ai->klasifikasi_abc_id);
        $this->assertEquals('b', $ai->klasifikasi_abc);
        $this->assertEquals(5000, (float) $ai->out);
        $this->assertEquals(1200, (float) $ai->stok_saat_ini);
        $this->assertEquals(1500, (float) $ai->harga_per_satuan);

        // Explicit null clears ABC on both AnalisaImpor and Produk without re-attaching to Class C
        $this->postJson(route('analisa.update-manual-impor'), [
            'produk_id' => $sku->id,
            'klasifikasi_abc_id' => null,
        ])->assertOk();

        $sku->refresh();
        $ai->refresh();
        $this->assertNull($sku->klasifikasi_abc_id);
        $this->assertNull($ai->klasifikasi_abc_id);
        $this->assertEquals('c', $ai->klasifikasi_abc);
        $this->assertEquals(0, (int) $ai->tambahan_buffer_hari);
    }

    public function test_add_sku_requires_analisa_manage_permission(): void
    {
        $abcA = KlasifikasiAbc::where('kode', 'a')->firstOrFail();

        $viewOnlyRole = Role::create([
            'name' => 'analisa_viewer',
            'guard_name' => 'web',
            'display_name' => 'Analisa Viewer',
            'is_system' => false,
        ]);
        $viewOnlyRole->syncPermissions(['analisa.view']);
        $viewer = User::factory()->create();
        $viewer->assignRole($viewOnlyRole);

        $sku = Produk::where('sku', 'BTL-P50')->firstOrFail();

        $this->actingAs($viewer)
            ->postJson(route('analisa.add-sku'), [
                'produk_id' => $sku->id,
                'target_profil' => 'impor',
                'klasifikasi_abc_id' => $abcA->id,
            ])
            ->assertForbidden();
    }

    public function test_migration_backfill_preserves_null_klasifikasi_abc_id_when_legacy_string_is_default_c(): void
    {
        $abcA = KlasifikasiAbc::where('kode', 'a')->firstOrFail();

        $assignedProduk = Produk::create([
            'sku' => 'MIG-BACKFILL-A',
            'nama_produk' => 'Backfill A',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'kemas',
            'satuan' => 'pcs',
            'satuan_order_moq' => 100,
            'profil_analisa' => 'impor',
            'klasifikasi_abc_id' => null,
            'is_active' => true,
        ]);

        $unassignedProduk = Produk::create([
            'sku' => 'MIG-UNASSIGNED-C',
            'nama_produk' => 'Unassigned Default C',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'kemas',
            'satuan' => 'pcs',
            'satuan_order_moq' => 100,
            'profil_analisa' => 'impor',
            'klasifikasi_abc_id' => null,
            'is_active' => true,
        ]);

        AnalisaImpor::create([
            'produk_id' => $assignedProduk->id,
            'klasifikasi_abc_id' => $abcA->id,
            'klasifikasi_abc' => 'a',
        ]);

        AnalisaImpor::create([
            'produk_id' => $unassignedProduk->id,
            'klasifikasi_abc_id' => null,
            'klasifikasi_abc' => 'c',
        ]);

        $migration = require base_path('database/migrations/2026_11_11_000001_add_klasifikasi_abc_id_to_produk_table.php');
        $migration->up();

        $this->assertEquals($abcA->id, $assignedProduk->fresh()->klasifikasi_abc_id);
        $this->assertNull($unassignedProduk->fresh()->klasifikasi_abc_id);
    }

    public function test_klasifikasi_abc_store_and_update_return_422_json_on_validation_error(): void
    {
        $abcB = KlasifikasiAbc::where('kode', 'b')->firstOrFail();

        $this->actingAs($this->purchasing);

        $this->postJson(route('klasifikasi-abc.store'), [
            'kode' => 'a',
            'nama' => 'Duplikat A',
            'tambahan_buffer_hari' => 4,
            'warna_badge' => 'amber',
        ])->assertStatus(422)->assertJsonStructure(['success', 'message', 'errors']);

        $this->putJson(route('klasifikasi-abc.update', $abcB), [
            'kode' => 'a',
            'nama' => 'Duplikat B jadi A',
            'tambahan_buffer_hari' => 2,
            'warna_badge' => 'blue',
        ])->assertStatus(422)->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_update_manual_impor_rejects_inactive_sku_with_422(): void
    {
        $abcA = KlasifikasiAbc::where('kode', 'a')->firstOrFail();

        $inactive = Produk::create([
            'sku' => 'INACTIVE-IMP-01',
            'nama_produk' => 'Bahan Nonaktif',
            'kategori_id' => $this->kategori->id,
            'tipe' => 'bahan',
            'satuan' => 'ml',
            'satuan_order_moq' => 100,
            'profil_analisa' => 'impor',
            'is_active' => false,
        ]);

        $this->actingAs($this->purchasing)
            ->postJson(route('analisa.update-manual-impor'), [
                'produk_id' => $inactive->id,
                'klasifikasi_abc_id' => $abcA->id,
            ])
            ->assertStatus(422);
    }

    public function test_klasifikasi_abc_data_returns_rows_even_before_produk_klasifikasi_migration_runs(): void
    {
        $migration = require base_path('database/migrations/2026_11_11_000001_add_klasifikasi_abc_id_to_produk_table.php');
        $migration->down();

        $this->assertFalse(Schema::hasColumn('produk', 'klasifikasi_abc_id'));

        try {
            $res = $this->actingAs($this->purchasing)
                ->getJson(route('klasifikasi-abc.data'))
                ->assertOk();

            $this->assertSame(4, $res->json('recordsTotal'));
            $this->assertCount(4, $res->json('data'));
        } finally {
            $migration->up();
        }
    }

    public function test_klasifikasi_abc_store_and_update_normalize_kode_before_unique_check(): void
    {
        $abcB = KlasifikasiAbc::where('kode', 'b')->firstOrFail();

        $this->actingAs($this->purchasing);

        $this->postJson(route('klasifikasi-abc.store'), [
            'kode' => 'A',
            'nama' => 'A',
            'tambahan_buffer_hari' => 0,
            'warna_badge' => 'gray',
        ])->assertStatus(422)->assertJsonStructure(['success', 'message', 'errors']);

        $this->postJson(route('klasifikasi-abc.store'), [
            'kode' => ' WAJIB_A ',
            'nama' => 'Duplikat Spasi Huruf Besar',
            'tambahan_buffer_hari' => 4,
            'warna_badge' => 'red',
        ])->assertStatus(422)->assertJsonStructure(['success', 'message', 'errors']);

        $this->putJson(route('klasifikasi-abc.update', $abcB), [
            'kode' => 'A',
            'nama' => 'B ke A',
            'tambahan_buffer_hari' => 2,
            'warna_badge' => 'blue',
        ])->assertStatus(422)->assertJsonStructure(['success', 'message', 'errors']);

        $this->postJson(route('klasifikasi-abc.store'), [
            'kode' => ' D ',
            'nama' => 'D - Baru',
            'tambahan_buffer_hari' => 0,
            'warna_badge' => 'gray',
        ])->assertOk()->assertJsonPath('data.kode', 'd');
    }
}
