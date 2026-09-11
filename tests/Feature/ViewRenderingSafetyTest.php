<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\RequestTransfer;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ISO/IEC 25010 Quality Assurance:
 * Performance Efficiency, Reliability & Fault Tolerance.
 * Menjamin 100% seluruh halaman (GET routes) bebas dari LazyLoadingViolationException
 * dengan Model::preventLazyLoading(true) aktif.
 */
class ViewRenderingSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed();

        // Pastikan strict prevent lazy loading selalu aktif selama test berlangsung
        Model::preventLazyLoading(true);
    }

    public function test_all_dashboard_views_render_without_lazy_loading_violation_for_all_roles(): void
    {
        $roles = ['manager', 'purchasing', 'gudang', 'operasional', 'fulfillment'];

        foreach ($roles as $role) {
            $user = User::where('email', "{$role}@heavenscent.id")->firstOrFail();
            $this->actingAs($user);

            $response = $this->get(route('dashboard'));
            $response->assertOk();
            $response->assertSee('Heaven Scent');
        }
    }

    public function test_purchasing_views_render_safely(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        // Index
        $this->get(route('purchasing.index'))->assertOk();

        // Create
        $this->get(route('purchasing.create'))->assertOk();

        // Show: Create PO with items, partial receipt, and payments
        $supplier = Supplier::firstOrFail();
        $item = Produk::bahan()->firstOrFail();
        $gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();

        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'status' => 'dikirim_ke_gudang',
            'created_by' => $manager->id,
        ]);
        $poItem = $po->items()->create([
            'produk_id' => $item->id,
            'qty' => 50,
            'harga_total' => 250000,
        ]);

        // Partial goods receipt
        $bardat = $po->barangDatang()->create([
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'created_by' => $manager->id,
        ]);
        $bardat->items()->create([
            'po_item_id' => $poItem->id,
            'qty_diterima' => 20,
        ]);

        // Payment
        $po->payments()->create([
            'skema' => 'termin',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 100000,
        ]);

        // Render Show View - must NOT throw LazyLoadingViolationException
        $response = $this->get(route('purchasing.show', $po));
        $response->assertOk();
        $response->assertSee('PO-TEST-001');
    }

    public function test_batch_production_views_render_safely(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        // Index
        $this->get(route('batches.index'))->assertOk();

        // Create
        $this->get(route('batches.create'))->assertOk();

        // Show: Batch with BOM allocations and Opname
        $produk = Produk::produkJadi()->with('bom')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();

        $batch = BatchProduksi::create([
            'no_batch' => 'B-TEST-001',
            'produk_id' => $produk->id,
            'qty_rencana' => 100,
            'gudang_operasional_id' => $gudangOp->id,
            'gudang_tujuan_rencana_id' => $gudangFf->id,
            'status' => 'release',
            'tanggal' => now()->toDateString(),
            'created_by' => $operasional->id,
        ]);

        foreach ($produk->bom as $b) {
            $batch->alokasi()->create([
                'bahan_id' => $b->bahan_id,
                'qty_dialokasikan' => 100 * (float) $b->qty_per_unit,
                'status' => 'dilepas',
            ]);
        }

        $batch->opname()->create([
            'bahan_id' => $produk->bom->first()->bahan_id,
            'pemakaian_teoritis' => 4000,
            'pemakaian_aktual' => 4050,
            'keterangan' => 'Uji opname',
        ]);

        $response = $this->get(route('batches.show', $batch));
        $response->assertOk();
        $response->assertSee('B-TEST-001');
    }

    public function test_request_transfer_views_render_safely(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        // Index
        $this->get(route('rt.index'))->assertOk();

        // Create
        $this->get(route('rt.create'))->assertOk();

        // Show: Request Transfer with items and warehouses
        $gudangAsal = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $gudangTujuan = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        $rt = RequestTransfer::create([
            'no_transaksi' => 'TR-TEST-001',
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangAsal->id,
            'gudang_tujuan_id' => $gudangTujuan->id,
            'status' => 'diproses',
            'catatan' => 'Uji rendering detail',
            'created_by' => $operasional->id,
        ]);
        $rt->items()->create([
            'produk_id' => $bahan->id,
            'qty_diminta' => 100,
            'qty_dikirim' => 100,
        ]);

        $response = $this->get(route('rt.show', $rt));
        $response->assertOk();
        $response->assertSee('TR-TEST-001');
    }

    public function test_stok_and_ledger_views_render_safely(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        // Stok Index
        $this->get(route('stok.index'))->assertOk();

        // Stok Opname Form
        $this->get(route('stok.opname'))->assertOk();

        // Stok Ledger (Kartu Stok)
        $produk = Produk::firstOrFail();
        $gudang = Gudang::firstOrFail();

        app(StokService::class)->masuk(
            $produk->id,
            $gudang->id,
            10,
            'mutasi_manual',
            null,
            'Uji ledger render'
        );

        $response = $this->get(route('stok.ledger', $produk));
        $response->assertOk();
        $response->assertSee($produk->nama);
    }

    public function test_analisa_stok_views_and_endpoints_render_safely(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->actingAs($purchasing);

        // Web View
        $this->get(route('analisa.index'))->assertOk();

        // JSON Endpoints
        $this->getJson(route('analisa.lokal.data'))->assertOk();
        $this->getJson(route('analisa.impor.data'))->assertOk();
        $this->getJson(route('analisa.fulfillment.data'))->assertOk();
        $this->getJson(route('analisa.riwayat.data'))->assertOk();
    }

    public function test_all_reports_views_render_safely(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $reportRoutes = [
            'reports.index',
            'reports.production',
            'reports.material',
            'reports.defect',
            'reports.low-stock',
            'reports.purchasing',
            'reports.fulfillment',
        ];

        foreach ($reportRoutes as $rName) {
            $response = $this->get(route($rName));
            $response->assertOk();
        }
    }

    public function test_master_data_views_render_safely(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $produk = Produk::firstOrFail();
        $gudang = Gudang::firstOrFail();
        $supplier = Supplier::firstOrFail();

        // Produk
        $this->get(route('produk.index'))->assertOk();
        $this->get(route('produk.create'))->assertOk();
        $this->get(route('produk.edit', $produk))->assertOk();

        // Gudang
        $this->get(route('gudang.index'))->assertOk();
        $this->get(route('gudang.create'))->assertOk();
        $this->get(route('gudang.edit', $gudang))->assertOk();

        // Supplier
        $this->get(route('supplier.index'))->assertOk();
        $this->get(route('supplier.create'))->assertOk();
        $this->get(route('supplier.edit', $supplier))->assertOk();

        // UOM
        $uom = Uom::first() ?? Uom::create(['kode' => 'pcs', 'nama' => 'Pieces']);
        $this->get(route('uom.index'))->assertOk();
        $this->get(route('uom.create'))->assertOk();
        $this->get(route('uom.edit', $uom))->assertOk();

        // BOM
        $this->get(route('bom.index'))->assertOk();
        $this->get(route('bom.edit', $produk))->assertOk();

        // Users
        $this->get(route('users.index'))->assertOk();
        $this->get(route('users.create'))->assertOk();
        $this->get(route('users.edit', $manager))->assertOk();
    }

    public function test_roles_views_render_safely_without_lazy_loading_violation(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $this->get(route('roles.index'))->assertOk();
        $this->get(route('roles.create'))->assertOk();

        $role = \App\Models\Role::first();
        if ($role) {
            $this->get(route('roles.edit', $role))->assertOk();
        }
    }

    public function test_produk_select_data_endpoint_supports_10_items_pagination_and_filtering(): void
    {
        $user = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($user);

        // 1. Basic pagination check (default 10 items)
        $response = $this->getJson(route('produk.select-data'));
        $response->assertOk()
            ->assertJsonStructure([
                'items' => [
                    '*' => ['id', 'sku', 'nama', 'satuan', 'tipe']
                ],
                'current_page',
                'has_more',
                'total'
            ]);

        $this->assertLessThanOrEqual(10, count($response->json('items')));
        $this->assertEquals(1, $response->json('current_page'));

        // 2. Filter tipe check
        $resBahan = $this->getJson(route('produk.select-data', ['tipe' => ['bahan', 'kemas']]));
        $resBahan->assertOk();
        foreach ($resBahan->json('items') as $item) {
            $this->assertContains($item['tipe'], ['bahan', 'kemas']);
        }

        // 3. Search query check
        $sample = Produk::active()->first();
        if ($sample) {
            $resSearch = $this->getJson(route('produk.select-data', ['q' => $sample->sku]));
            $resSearch->assertOk();
            $this->assertTrue(collect($resSearch->json('items'))->contains('id', $sample->id));
        }
    }
}

