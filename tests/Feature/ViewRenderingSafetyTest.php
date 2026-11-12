<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\StockRequest;
use App\Models\Supplier;
use App\Models\Transfer;
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
            $response->assertDontSee('max-w-7xl');
            $response->assertDontSee('👋');
            $response->assertDontSee('bg-gradient-to-r');
        }
    }

    public function test_dashboard_renders_safely_for_all_five_roles(): void
    {
        $roles = [
            'manager@heavenscent.id' => 'manager',
            'purchasing@heavenscent.id' => 'purchasing',
            'gudang@heavenscent.id' => 'gudang',
            'operasional@heavenscent.id' => 'operasional',
            'fulfillment@heavenscent.id' => 'fulfillment',
        ];

        foreach ($roles as $email => $roleName) {
            $user = User::where('email', $email)->firstOrFail();
            $response = $this->actingAs($user)->get(route('dashboard'));
            $response->assertOk();
            $response->assertViewHas('role', $roleName);
            $response->assertViewHas('kpis');
            $response->assertViewHas('actionQueue');
            $response->assertViewHas('streamLeft');
            $response->assertViewHas('streamRight');
        }

        $expectedTitles = [
            'manager@heavenscent.id' => 'PO Menunggu Approval',
            'purchasing@heavenscent.id' => 'Item Perlu Order (Lokal)',
            'gudang@heavenscent.id' => 'Kedatangan Siap Unloading',
            'operasional@heavenscent.id' => 'Kesiapan Bahan BOM',
            'fulfillment@heavenscent.id' => 'Total Saldo Produk Jadi',
        ];
        foreach ($expectedTitles as $email => $title) {
            $user = User::where('email', $email)->firstOrFail();
            $this->actingAs($user)->get(route('dashboard'))->assertSee($title);
        }

        $expectedActionQueueTitles = [
            'manager@heavenscent.id' => 'Antrean Otorisasi & Persetujuan Tertunda',
            'purchasing@heavenscent.id' => 'Antrean Tindak Lanjut Pengadaan',
            'gudang@heavenscent.id' => 'Antrean Bongkar Muat & Dispatch Fisik',
            'operasional@heavenscent.id' => 'Antrean Kesiapan Batch & Alokasi Formula',
            'fulfillment@heavenscent.id' => 'Antrean Penerimaan Produk Jadi & Distribusi Toko',
        ];
        foreach ($expectedActionQueueTitles as $email => $title) {
            $user = User::where('email', $email)->firstOrFail();
            $this->actingAs($user)->get(route('dashboard'))->assertSee($title, false);
        }

        $expectedStreamTitles = [
            'manager@heavenscent.id' => ['Komitmen Pembayaran PO Terbit', 'Peringatan Stok di Bawah Minimum'],
            'purchasing@heavenscent.id' => ['Monitoring Pengiriman Ekspedisi Vendor', 'Daftar PO Aktif Berjalan'],
            'gudang@heavenscent.id' => ['Pengiriman Logistik Keluar (Draft)', 'Pencatatan Defect & Barang Rusak'],
            'operasional@heavenscent.id' => ['Batch Produksi Sedang Berjalan', 'Permintaan Bahan ke Gudang Pusat'],
            'fulfillment@heavenscent.id' => ['Ketersediaan Produk Jadi Utama', 'Pasokan Selesai dari Laboratorium'],
        ];
        foreach ($expectedStreamTitles as $email => $titles) {
            $user = User::where('email', $email)->firstOrFail();
            $resp = $this->actingAs($user)->get(route('dashboard'));
            foreach ($titles as $stTitle) {
                $resp->assertSee($stTitle, false);
            }
        }
    }

    public function test_purchasing_views_render_safely(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        // Index
        $this->get(route('purchasing.index'))->assertOk();

        // Create
        $this->get(route('purchasing.create'))
            ->assertOk()
            ->assertDontSee('max-w-6xl');

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
        $this->get(route('batches.create'))
            ->assertOk()
            ->assertDontSee('max-w-4xl');

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

    public function test_request_and_transfer_views_render_safely(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $gudangAsal = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $gudangTujuan = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        // Requests: Index, Create, Show
        $this->get(route('requests.index'))->assertOk();
        $this->get(route('requests.create'))->assertOk();

        $stockRequest = StockRequest::create([
            'no_request' => 'REQ-TEST-001',
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $gudangTujuan->id,
            'gudang_tujuan_id' => $gudangAsal->id,
            'status' => 'diajukan',
            'catatan' => 'Uji rendering detail request',
            'created_by' => $operasional->id,
        ]);
        $stockRequest->items()->create([
            'produk_id' => $bahan->id,
            'qty_diminta' => 100,
        ]);

        $this->get(route('requests.show', $stockRequest))
            ->assertOk()
            ->assertSee('REQ-TEST-001');

        // Transfers: Index, Create, Show, Surat Jalan
        $this->get(route('transfers.index'))->assertOk();
        $this->get(route('transfers.create'))->assertOk();

        $transfer = Transfer::create([
            'no_transaksi' => 'TRF-TEST-001',
            'jenis' => 'pemenuhan_request_bahan',
            'gudang_asal_id' => $gudangAsal->id,
            'gudang_tujuan_id' => $gudangTujuan->id,
            'request_id' => $stockRequest->id,
            'status' => 'dikirim',
            'catatan' => 'Uji rendering detail transfer',
            'created_by' => $operasional->id,
        ]);
        $transfer->items()->create([
            'produk_id' => $bahan->id,
            'qty_dikirim' => 100,
            'qty_rusak' => 0,
        ]);

        $this->get(route('transfers.show', $transfer))
            ->assertOk()
            ->assertSee('TRF-TEST-001');
        $this->get(route('transfers.surat-jalan', $transfer))
            ->assertOk()
            ->assertSee('TRF-TEST-001');
    }

    public function test_stok_and_ledger_views_render_safely(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        // Stok Index
        $this->get(route('stok.index'))->assertOk();

        // Stok Opname Form
        $this->get(route('stok.opname'))
            ->assertOk()
            ->assertDontSee('max-w-4xl');

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
        $this->get(route('produk.create'))->assertOk()->assertDontSee('max-w-4xl');
        $this->get(route('produk.edit', $produk))->assertOk();

        // Gudang
        $this->get(route('gudang.index'))->assertOk();
        $this->get(route('gudang.create'))->assertOk()->assertDontSee('max-w-3xl');
        $this->get(route('gudang.edit', $gudang))->assertOk();

        // Supplier
        $this->get(route('supplier.index'))->assertOk();
        $this->get(route('supplier.create'))->assertOk()->assertDontSee('max-w-3xl');
        $this->get(route('supplier.edit', $supplier))->assertOk();

        // UOM
        $uom = Uom::first() ?? Uom::create(['kode' => 'pcs', 'nama' => 'Pieces']);
        $this->get(route('uom.index'))->assertOk();
        $this->get(route('uom.create'))->assertOk()->assertDontSee('max-w-3xl');
        $this->get(route('uom.edit', $uom))->assertOk();

        // BOM
        $this->get(route('bom.index'))->assertOk();
        $this->get(route('bom.edit', $produk))->assertOk();

        // Users
        $this->get(route('users.index'))->assertOk();
        $this->get(route('users.create'))->assertOk()->assertDontSee('max-w-5xl');
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
            // Lowercase search test
            $resSearch = $this->getJson(route('produk.select-data', ['q' => strtolower($sample->sku)]));
            $resSearch->assertOk();
            $this->assertTrue(collect($resSearch->json('items'))->contains('id', $sample->id));

            // Multi-keyword partial search test
            $words = array_filter(explode(' ', strtolower($sample->nama)));
            if (!empty($words)) {
                $resMulti = $this->getJson(route('produk.select-data', ['q' => implode(' ', array_slice($words, 0, 2))]));
                $resMulti->assertOk();
                $this->assertTrue(collect($resMulti->json('items'))->contains('id', $sample->id));
            }
        }
    }

    public function test_all_blade_views_compile_without_syntax_errors(): void
    {
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        $exitCode = \Illuminate\Support\Facades\Artisan::call('view:cache');
        $this->assertSame(0, $exitCode, 'Artisan view:cache failed: ' . \Illuminate\Support\Facades\Artisan::output());
        \Illuminate\Support\Facades\Artisan::call('view:clear');
    }

    public function test_no_risky_blade_directives_or_raw_comparison_operators_in_component_attributes(): void
    {
        $viewsPath = resource_path('views');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsPath));

        $violations = [];

        foreach ($files as $file) {
            if ($file->isDir() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $relPath = str_replace($viewsPath . DIRECTORY_SEPARATOR, '', $file->getPathname());

            // 1. Periksa pemanggilan @json( dengan literal array/bracket rentan parse error
            if (preg_match('/@json\s*\([^)]*\[/i', $content, $matches)) {
                $violations[] = "[{$relPath}] Ditemukan pemanggilan @json dengan literal array/bracket: {$matches[0]} -> gunakan @js(...)";
            }

            // 2. Periksa tag komponen <x- dengan atribut x-bind/:: memuat operator perbandingan mentah < atau >
            if (preg_match('/<x-[^>]*?\s(?:x-bind:[\w\-]+|::[a-zA-Z][\w\-]*)\s*=\s*(["\'])(?:(?!\1).)*?[<>].*?\1/s', $content, $matches)) {
                $violations[] = "[{$relPath}] Ditemukan operator mentah (< atau >) pada atribut komponen: {$matches[0]} -> ekstrak ke helper boolean";
            }
        }

        $this->assertEmpty($violations, "Pelanggaran sintaks Blade terdeteksi:\n" . implode("\n", $violations));
    }

    public function test_proportional_fluid_scaling_shell_and_css_rules(): void
    {
        // Guest login harus tetap pada skala 100% tanpa class hs-app-shell
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('hs-app-shell');

        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $dashboardRes = $this->get(route('dashboard'));
        $dashboardRes->assertOk();
        $dashboardRes->assertSee('hs-app-shell');
        $dashboardRes->assertSee('lg:w-56');
        $dashboardRes->assertDontSee('lg:w-64');

        // Dokumen cetak surat jalan tetap terisolasi tanpa hs-app-shell
        $gudangAsal = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $gudangTujuan = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        $transfer = Transfer::create([
            'no_transaksi' => 'TRF-SCALE-01',
            'jenis' => 'pemenuhan_request_bahan',
            'gudang_asal_id' => $gudangAsal->id,
            'gudang_tujuan_id' => $gudangTujuan->id,
            'status' => 'dikirim',
            'created_by' => $manager->id,
        ]);
        $transfer->items()->create([
            'produk_id' => $bahan->id,
            'qty_dikirim' => 10,
            'qty_rusak' => 0,
        ]);

        $this->get(route('transfers.surat-jalan', $transfer))
            ->assertOk()
            ->assertDontSee('hs-app-shell');

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);
        $this->assertStringContainsString('html.hs-app-shell', $css);
        $this->assertStringContainsString('clamp(0.8125rem, calc(0.5rem + 0.4167vw), 1rem)', $css);
        $this->assertStringContainsString('@media print', $css);
        $this->assertStringNotContainsString('sm:min-w-[240px]', $css);
    }

    public function test_shared_layout_components_prevent_cramping_on_laptop_viewports(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $dashboard = $this->get(route('dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('min-w-0 flex-1', false);
        $dashboard->assertSee('sm:justify-end gap-x-3 gap-y-2', false);
        $dashboard->assertSee('flex items-center gap-2.5 min-w-0 flex-1', false);
        $dashboard->assertSee('flex-1 min-w-0', false);
        $dashboard->assertSee('break-words', false);

        $supplier = Supplier::firstOrFail();
        $po = PurchaseOrder::create([
            'no_po' => 'PO-CMP-SCALE-01',
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'status' => 'berjalan',
            'stage_aktif' => \App\Models\PoStage::STAGE_1,
            'created_by' => $manager->id,
        ]);
        $po->stages()->create([
            'stage' => \App\Models\PoStage::STAGE_1,
            'urutan' => 1,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subHour(),
            'pic_user_id' => $manager->id,
        ]);

        $poShow = $this->get(route('purchasing.show', $po));
        $poShow->assertOk();
        $poShow->assertSee('tabindex="0" role="region" aria-label="Scroll Tahapan Purchase Order"', false);
        $poShow->assertSee('min-w-[44rem] lg:min-w-0', false);
        $poShow->assertDontSee('sm:min-w-0', false);
        $poShow->assertDontSee('max-w-[85px]', false);
        $poShow->assertSee('flex-1 min-w-0 px-1 relative z-10', false);
        $poShow->assertSee('aria-label="Tahapan Purchase Order"', false);
        $poShow->assertSee('flex flex-wrap items-center gap-2 shrink-0', false);
    }

    public function test_operational_views_use_fluid_filters_and_wrapping_toolbars(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $dashboard = $this->get(route('dashboard'));
        $dashboard->assertOk()
            ->assertSee('lg:grid-cols-5', false)
            ->assertSee('flex flex-wrap items-center justify-end gap-2', false);

        $rigidFilterClasses = [
            'min-w-[120px]',
            'min-w-[150px]',
            'min-w-[160px]',
            'min-w-[170px]',
            'min-w-[180px]',
            'min-w-[190px]',
            'min-w-[200px]',
            'min-w-[220px]',
            'min-w-[280px]',
        ];

        $viewRoutes = [
            'purchasing.index',
            'stok.index',
            'stok.log-pergerakan',
            'analisa.index',
            'users.index',
            'reports.defect',
            'reports.lead-time',
        ];

        foreach ($viewRoutes as $routeName) {
            $res = $this->get(route($routeName));
            $res->assertOk();
            foreach ($rigidFilterClasses as $rigidClass) {
                $res->assertDontSee($rigidClass, false);
            }
            if ($routeName === 'reports.defect') {
                $res->assertSee('lg:grid-cols-6', false);
            }
            if ($routeName === 'analisa.index') {
                $res->assertSee('sm:grid-cols-4', false);
            }
        }

        $produk = Produk::produkJadi()->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $batch = BatchProduksi::create([
            'no_batch' => 'B-SCALE-001',
            'produk_id' => $produk->id,
            'qty_rencana' => 50,
            'gudang_operasional_id' => $gudangOp->id,
            'gudang_tujuan_rencana_id' => $gudangFf->id,
            'status' => 'release',
            'tanggal' => now()->toDateString(),
            'created_by' => $manager->id,
        ]);

        $this->get(route('batches.show', $batch))
            ->assertOk()
            ->assertSee('lg:grid-cols-3 gap-4', false)
            ->assertSee('lg:col-span-2 space-y-5 min-w-0', false);

        $this->get(route('purchasing.create'))
            ->assertOk()
            ->assertSee('min-w-64 sm:min-w-80', false)
            ->assertDontSee('min-w-[320px]', false)
            ->assertDontSee('min-width: 320px', false);
    }

    public function test_defect_dashboard_bom_import_headers_and_users_table_prevent_laptop_cramping(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('flex items-center gap-3 min-w-0 flex-1', false)
            ->assertSee('flex flex-wrap items-center lg:justify-end gap-2 shrink-0 max-w-full', false);

        $this->get(route('bom.import-page'))
            ->assertOk()
            ->assertSee('<div class="min-w-0 flex-1">', false)
            ->assertSee('flex flex-wrap items-center lg:justify-end gap-2 shrink-0 max-w-full', false)
            ->assertSee('inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap', false);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('usersTable" class="w-full text-xs [&_thead_th]:!px-2.5 [&_tbody_td]:!px-2.5"', false);

        $usersData = $this->getJson(route('users.data'))->assertOk()->json('data');
        $this->assertNotEmpty($usersData);
        $this->assertStringContainsString('<div class="flex flex-wrap items-center gap-1">', $usersData[0]['roles_label']);
        $this->assertStringContainsString('inline-flex items-center whitespace-nowrap', $usersData[0]['roles_label']);
    }
}

