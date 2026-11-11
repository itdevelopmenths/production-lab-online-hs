<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Produk;
use App\Models\Stok;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StokEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_stok_data_search_by_sku_and_nama_and_action_link(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $gOps = Gudang::where('tipe', 'operasional')->firstOrFail();
        $bahan = Produk::where('sku', 'ALK-01')->firstOrFail();

        $stokService = app(StokService::class);
        $stokService->setAbsolut($bahan->id, $gOps->id, 500, 'Init stock');

        // Global search with DataTables format
        $res = $this->getJson(route('stok.data', [
            'search' => ['value' => 'ALK-01'],
        ]));

        $res->assertOk();
        $data = collect($res->json('data'));
        $this->assertTrue($data->contains(fn ($row) => $row['sku'] === 'ALK-01'));

        // Check action link contains both produk and gudang_id
        $alkRow = $data->firstWhere('sku', 'ALK-01');
        $this->assertNotNull($alkRow);
        $this->assertMatchesRegularExpression('/gudang_id=\d+/', $alkRow['action']);

        // Scoped search with DataTables format for gOps
        $resScoped = $this->getJson(route('stok.data', [
            'gudang_id' => $gOps->id,
            'search' => ['value' => 'ALK-01'],
        ]));
        $resScoped->assertOk();
        $dataScoped = collect($resScoped->json('data'));
        $alkScopedRow = $dataScoped->firstWhere('sku', 'ALK-01');
        $this->assertNotNull($alkScopedRow);
        $this->assertStringContainsString('gudang_id=' . $gOps->id, $alkScopedRow['action']);

        // Search by Product Name (case-insensitive lowercase)
        $resName = $this->getJson(route('stok.data', [
            'search' => ['value' => strtolower(substr($bahan->nama, 0, 4))],
        ]));
        $resName->assertOk();
        $dataName = collect($resName->json('data'));
        $this->assertTrue($dataName->contains(fn ($row) => $row['sku'] === 'ALK-01'));

        // Search by Warehouse Name (case-insensitive lowercase)
        $resGudang = $this->getJson(route('stok.data', [
            'search' => ['value' => strtolower($gOps->nama)],
        ]));
        $resGudang->assertOk();
        $dataGudang = collect($resGudang->json('data'));
        $this->assertTrue($dataGudang->every(fn ($row) => str_contains(strtolower($row['gudang_nama']), strtolower($gOps->nama))));

        // Multi-word search (Product Name + Warehouse Name)
        $resMulti = $this->getJson(route('stok.data', [
            'search' => ['value' => 'alkohol ' . strtolower($gOps->nama)],
        ]));
        $resMulti->assertOk();
        $dataMulti = collect($resMulti->json('data'));
        $this->assertTrue($dataMulti->contains(fn ($row) => $row['sku'] === 'ALK-01' && str_contains(strtolower($row['gudang_nama']), strtolower($gOps->nama))));
    }

    public function test_kartu_stok_ledger_scoped_by_gudang_id(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $gOps = Gudang::where('tipe', 'operasional')->firstOrFail();
        $gBahan = Gudang::where('tipe', 'bahan_baku')->firstOrFail();
        $bahan = Produk::where('sku', 'ALK-01')->firstOrFail();

        $stokService = app(StokService::class);
        // Mutate in gOps
        $stokService->masuk($bahan->id, $gOps->id, 100, 'po', 1, 'Inflow ops');
        // Mutate in gBahan
        $stokService->masuk($bahan->id, $gBahan->id, 200, 'po', 2, 'Inflow bahan');

        // Request ledger data for gOps only
        $resOps = $this->getJson(route('stok.ledger.data', [
            'produk' => $bahan->id,
            'gudang_id' => $gOps->id,
        ]));

        $resOps->assertOk();
        $dataOps = collect($resOps->json('data'));
        $this->assertGreaterThanOrEqual(1, $dataOps->count());
        foreach ($dataOps as $entry) {
            $this->assertEquals($gOps->nama, $entry['gudang_nama']);
        }

        // Request ledger data for gBahan only
        $resBahan = $this->getJson(route('stok.ledger.data', [
            'produk' => $bahan->id,
            'gudang_id' => $gBahan->id,
        ]));
        $resBahan->assertOk();
        $dataBahan = collect($resBahan->json('data'));
        $this->assertGreaterThanOrEqual(1, $dataBahan->count());
        foreach ($dataBahan as $entry) {
            $this->assertEquals($gBahan->nama, $entry['gudang_nama']);
        }

        // Without gudang_id filter, both should be present
        $resAll = $this->getJson(route('stok.ledger.data', [
            'produk' => $bahan->id,
        ]));
        $resAll->assertOk();
        $dataAll = collect($resAll->json('data'));
        $this->assertTrue($dataAll->pluck('gudang_nama')->contains($gOps->nama));
        $this->assertTrue($dataAll->pluck('gudang_nama')->contains($gBahan->nama));
    }

    public function test_current_stock_endpoint_returns_realtime_quantity(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $gOps = Gudang::where('tipe', 'operasional')->firstOrFail();
        $bahan = Produk::where('sku', 'ALK-01')->firstOrFail();

        $stokService = app(StokService::class);
        $stokService->setAbsolut($bahan->id, $gOps->id, 350.5, 'Opname set');

        // Fetch current stock
        $res = $this->getJson(route('stok.current-stock', [
            'produk_id' => $bahan->id,
            'gudang_id' => $gOps->id,
        ]));

        $res->assertOk();
        $res->assertJson([
            'produk_id' => $bahan->id,
            'gudang_id' => $gOps->id,
            'qty' => 350.5,
        ]);

        // When parameters are missing, returns 0
        $resEmpty = $this->getJson(route('stok.current-stock'));
        $resEmpty->assertOk();
        $resEmpty->assertJson(['qty' => 0]);
    }

    public function test_views_render_successfully_with_new_features(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $gOps = Gudang::where('tipe', 'operasional')->firstOrFail();
        $bahan = Produk::where('sku', 'ALK-01')->firstOrFail();

        // 1. Stok Index
        $resIndex = $this->get(route('stok.index'));
        $resIndex->assertSee('Lokasi Gudang');
        $resIndex->assertSee('Kategori Barang');
        $resIndex->assertDontSee('id="filter-search"', false);

        // 2. Stok Ledger with gudang_id
        $resLedger = $this->get(route('stok.ledger', [
            'produk' => $bahan->id,
            'gudang_id' => $gOps->id,
        ]));
        $resLedger->assertOk();
        $resLedger->assertSee('Lokasi Gudang');
        $resLedger->assertSee('Lokasi Terpilih');

        // 3. Stok Opname with pre-selected query params
        $resOpname = $this->get(route('stok.opname', [
            'produk_id' => $bahan->id,
            'gudang_id' => $gOps->id,
        ]));
        $resOpname->assertOk();
        $resOpname->assertSee('Monitoring Saldo Sistem');
        $resOpname->assertSee('Stok di Sistem');
        $resOpname->assertSee('Estimasi Deviasi');
    }

    public function test_stok_index_defaults_lokasi_gudang_and_mutasi_modal_to_pusat_for_global_user(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gBahanPusat = Gudang::bahanBakuPusat()->firstOrFail();

        $res = $this->actingAs($manager)->get(route('stok.index'));
        $res->assertOk();

        $html = $res->getContent();

        // Filter Lokasi Gudang tetap memuat opsi "Semua Gudang", namun default terpilih adalah GD-PUSAT
        $this->assertMatchesRegularExpression(
            '/<select[^>]*id="filter-gudang"[^>]*>(?:(?!<\/select>).)*?<option value="">— Semua Gudang —<\/option>(?:(?!<\/select>).)*?<option value="' . $gBahanPusat->id . '"\s+selected>/s',
            $html
        );

        // Modal Mutasi Manual juga men-default-kan gudangId & selected option ke GD-PUSAT
        $this->assertStringContainsString("stokMutasiForm('{$gBahanPusat->id}'", $html);
        $this->assertMatchesRegularExpression(
            '/<select[^>]*name="gudang_id"[^>]*x-model="gudangId"[^>]*>(?:(?!<\/select>).)*?<option value="' . $gBahanPusat->id . '"\s+selected>/s',
            $html
        );
    }

    public function test_stok_index_defaults_lokasi_gudang_to_accessible_pusat_for_restricted_user(): void
    {
        $ffPusat = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $ffSby = Gudang::where('kode', 'FF-SBY')->firstOrFail();
        $gOps = Gudang::where('kode', 'GD-OPS')->firstOrFail();

        // Buat gudang non-pusat yang namanya muncul sebelum "Fulfillment Pusat" secara alfabet
        $ffCabangA = Gudang::create([
            'kode' => 'FF-AAA',
            'nama' => 'AAA Cabang Fulfillment',
            'tipe' => 'fulfillment',
            'is_pusat' => false,
            'status' => 'aktif',
        ]);

        $ffUser = User::factory()->create([
            'warehouse_access_type' => 'restricted',
        ]);
        $ffUser->givePermissionTo(['stok.view', 'stok.mutasi']);
        $ffUser->gudangs()->sync([$ffCabangA->id, $ffSby->id, $ffPusat->id]);

        $resFf = $this->actingAs($ffUser)->get(route('stok.index'));
        $resFf->assertOk();
        $resFf->assertDontSee('— Semua Gudang —');

        $htmlFf = $resFf->getContent();
        $this->assertMatchesRegularExpression(
            '/<select[^>]*id="filter-gudang"[^>]*>(?:(?!<\/select>).)*?<option value="' . $ffPusat->id . '"\s+selected>/s',
            $htmlFf
        );
        $this->assertStringContainsString("stokMutasiForm('{$ffPusat->id}'", $htmlFf);

        // Jika user restricted tidak memiliki akses gudang pusat sama sekali, fallback ke gudang pertama miliknya
        $opsUser = User::factory()->create([
            'warehouse_access_type' => 'restricted',
        ]);
        $opsUser->givePermissionTo(['stok.view', 'stok.mutasi']);
        $opsUser->gudangs()->sync([$gOps->id]);

        $resOps = $this->actingAs($opsUser)->get(route('stok.index'));
        $resOps->assertOk();
        $this->assertMatchesRegularExpression(
            '/<select[^>]*id="filter-gudang"[^>]*>(?:(?!<\/select>).)*?<option value="' . $gOps->id . '"\s+selected>/s',
            $resOps->getContent()
        );
        $this->assertStringContainsString("stokMutasiForm('{$gOps->id}'", $resOps->getContent());
    }
}
