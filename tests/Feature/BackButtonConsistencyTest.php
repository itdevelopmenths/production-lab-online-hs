<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\Divisi;
use App\Models\Gudang;
use App\Models\Kategori;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockRequest;
use App\Models\Supplier;
use App\Models\Transfer;
use App\Models\Uom;
use App\Models\User;
use App\Models\Varian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackButtonConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed();
    }

    public function test_every_detail_edit_create_and_child_page_has_exactly_one_back_button(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $supplier = Supplier::firstOrFail();
        $produk = Produk::produkJadi()->first() ?? Produk::firstOrFail();
        $bahan = Produk::bahan()->first() ?? $produk;
        $gudangAsal = Gudang::where('kode', 'GD-PUSAT')->first() ?? Gudang::firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->first() ?? $gudangAsal;
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->first() ?? $gudangAsal;
        $uom = Uom::first() ?? Uom::create(['kode' => 'pcs-back', 'nama' => 'Pieces Back']);
        $divisi = Divisi::first() ?? Divisi::create([
            'kode' => 'qa-back',
            'nama' => 'QA Back Test',
            'color' => 'slate',
            'is_active' => true,
        ]);
        $kategori = Kategori::first() ?? Kategori::create(['nama' => 'Kategori Back Test']);
        $varian = Varian::first() ?? Varian::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Varian Back Test',
        ]);
        $role = Role::firstOrFail();

        $po = PurchaseOrder::create([
            'no_po' => 'PO-BACK-001',
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudangAsal->id,
            'tanggal' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $manager->id,
        ]);
        $po->items()->create([
            'produk_id' => $bahan->id,
            'qty' => 10,
            'harga_total' => 50000,
        ]);

        $batch = BatchProduksi::create([
            'no_batch' => 'B-BACK-001',
            'produk_id' => $produk->id,
            'qty_rencana' => 10,
            'gudang_operasional_id' => $gudangOp->id,
            'gudang_tujuan_rencana_id' => $gudangFf->id,
            'status' => 'rencana',
            'tanggal' => now()->toDateString(),
            'created_by' => $manager->id,
        ]);

        $stockRequest = StockRequest::create([
            'no_request' => 'REQ-BACK-001',
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangAsal->id,
            'status' => 'draft',
            'created_by' => $manager->id,
        ]);
        $stockRequest->items()->create([
            'produk_id' => $bahan->id,
            'qty_diminta' => 5,
        ]);

        $transfer = Transfer::create([
            'no_transaksi' => 'TRF-BACK-001',
            'jenis' => 'pemenuhan_request_bahan',
            'gudang_asal_id' => $gudangAsal->id,
            'gudang_tujuan_id' => $gudangOp->id,
            'request_id' => $stockRequest->id,
            'status' => 'draft',
            'created_by' => $manager->id,
        ]);
        $transfer->items()->create([
            'produk_id' => $bahan->id,
            'qty_dikirim' => 5,
            'qty_rusak' => 0,
        ]);

        $routes = [
            'analisa.create-po' => [route('analisa.create-po'), route('analisa.index')],
            'batches.create' => [route('batches.create'), route('batches.index')],
            'batches.show' => [route('batches.show', $batch), route('batches.index')],
            'bom.edit' => [route('bom.edit', $produk), route('bom.index')],
            'bom.import-page' => [route('bom.import-page'), route('bom.index')],
            'divisi.create' => [route('divisi.create'), route('divisi.index')],
            'divisi.edit' => [route('divisi.edit', $divisi), route('divisi.index')],
            'gudang.create' => [route('gudang.create'), route('gudang.index')],
            'gudang.edit' => [route('gudang.edit', $gudangAsal), route('gudang.index')],
            'kategori.create' => [route('kategori.create'), route('kategori.index')],
            'kategori.edit' => [route('kategori.edit', $kategori), route('kategori.index')],
            'produk.create' => [route('produk.create'), route('produk.index')],
            'produk.edit' => [route('produk.edit', $produk), route('produk.index')],
            'purchasing.create' => [route('purchasing.create'), route('purchasing.index')],
            'purchasing.edit' => [route('purchasing.edit', $po), route('purchasing.show', $po)],
            'purchasing.show' => [route('purchasing.show', $po), route('purchasing.index')],
            'reports.defect' => [route('reports.defect'), route('reports.index')],
            'reports.fulfillment' => [route('reports.fulfillment'), route('reports.index')],
            'reports.lead-time' => [route('reports.lead-time'), route('reports.index')],
            'reports.low-stock' => [route('reports.low-stock'), route('reports.index')],
            'reports.material' => [route('reports.material'), route('reports.index')],
            'reports.production' => [route('reports.production'), route('reports.index')],
            'reports.purchasing' => [route('reports.purchasing'), route('reports.index')],
            'requests.create' => [route('requests.create'), route('requests.index')],
            'requests.edit' => [route('requests.edit', $stockRequest), route('requests.show', $stockRequest)],
            'requests.show' => [route('requests.show', $stockRequest), route('requests.index')],
            'roles.create' => [route('roles.create'), route('roles.index')],
            'roles.edit' => [route('roles.edit', $role), route('roles.index')],
            'stok.ledger' => [route('stok.ledger', $produk), route('stok.index')],
            'stok.opname' => [route('stok.opname'), route('stok.index')],
            'stok.log-pergerakan' => [route('stok.log-pergerakan'), route('stok.index')],
            'supplier.create' => [route('supplier.create'), route('supplier.index')],
            'supplier.edit' => [route('supplier.edit', $supplier), route('supplier.index')],
            'transfers.create' => [route('transfers.create'), route('transfers.index')],
            'transfers.show' => [route('transfers.show', $transfer), route('transfers.index')],
            'uom.create' => [route('uom.create'), route('uom.index')],
            'uom.edit' => [route('uom.edit', $uom), route('uom.index')],
            'users.create' => [route('users.create'), route('users.index')],
            'users.edit' => [route('users.edit', $manager), route('users.index')],
            'varian.create' => [route('varian.create'), route('varian.index')],
            'varian.edit' => [route('varian.edit', $varian), route('varian.index')],
        ];

        $this->assertCount(41, $routes);

        foreach ($routes as $name => [$url, $expectedBackHref]) {
            $response = $this->get($url);
            $response->assertOk();

            $html = $response->getContent();
            $this->assertSame(
                0,
                substr_count($html, 'aria-label="Kembali"'),
                "[{$name}] Tidak boleh merender tombol back redundan pada navbar layout"
            );

            $backButtonMatches = [];
            preg_match_all('/<a\b[^>]*\bdata-back-button\b[^>]*>.*?<\/a>/s', $html, $backButtonMatches);
            $this->assertCount(
                1,
                $backButtonMatches[0],
                "[{$name}] Harus merender tepat 1 tombol kembali utama"
            );

            // Pastikan hanya 1 link navigasi <a> yang berteks "Kembali" di halaman (tanpa link kembali ganda)
            $backLinksMatches = [];
            preg_match_all('/<a\b[^>]*>[^<]*(?:<[^>]+>[^<]*)*?Kembali[^<]*(?:<[^>]+>[^<]*)*?<\/a>/s', $html, $backLinksMatches);
            $this->assertCount(
                1,
                $backLinksMatches[0],
                "[{$name}] Hanya boleh ada tepat 1 link navigasi Kembali di seluruh halaman"
            );

            $quotedHref = preg_quote($expectedBackHref, '/');
            $this->assertMatchesRegularExpression(
                '/<a\b[^>]*href="' . $quotedHref . '"[^>]*data-back-button="true"[^>]*>.*?Kembali.*?<\/a>/s',
                $html,
                "[{$name}] Tombol kembali harus mengarah ke {$expectedBackHref}"
            );
        }

        // Pastikan halaman daftar (index) utama tidak menampilkan tombol Kembali
        foreach ([
            'analisa.index',
            'batches.index',
            'bom.index',
            'divisi.index',
            'purchasing.index',
            'reports.index',
            'requests.index',
            'stok.index',
            'transfers.index',
        ] as $indexRoute) {
            $indexHtml = $this->get(route($indexRoute))->assertOk()->getContent();
            $this->assertSame(0, substr_count($indexHtml, 'aria-label="Kembali"'), "[{$indexRoute}] Index tidak boleh punya back navbar");
            $this->assertSame(0, substr_count($indexHtml, 'data-back-button="true"'), "[{$indexRoute}] Index tidak boleh punya tombol kembali");
        }

        // Pastikan footer form create memakai tepat 1 tombol Tambah Baris (menggantikan Batal & tombol header lama)
        foreach (['requests.create', 'transfers.create'] as $createName) {
            [$createUrl] = $routes[$createName];
            $createHtml = $this->get($createUrl)->getContent();
            $this->assertStringNotContainsString('Kembali ke Daftar', $createHtml, "[{$createName}] Label footer tidak boleh menduplikasi tombol Kembali");
            $this->assertStringNotContainsString('>Batal<', $createHtml, "[{$createName}] Tombol Batal footer harus sudah dihapus");
            $this->assertSame(1, substr_count($createHtml, 'Tambah Baris'), "[{$createName}] Harus memiliki tepat 1 tombol Tambah Baris di footer tabel");
        }

        // Pastikan halaman detail permintaan & transfer tidak menduplikasi tag <h1> di body utama
        foreach (['requests.show', 'transfers.show'] as $showName) {
            [$showUrl] = $routes[$showName];
            $showHtml = $this->get($showUrl)->getContent();
            $mainPos = strpos($showHtml, '<main');
            $this->assertNotFalse($mainPos);
            $mainHtml = substr($showHtml, $mainPos);
            $this->assertSame(1, substr_count($mainHtml, '<h1 '), "[{$showName}] Main landmark hanya boleh memiliki 1 heading <h1>");
        }
    }
}
