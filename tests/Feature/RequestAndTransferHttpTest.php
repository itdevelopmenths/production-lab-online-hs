<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\Role;
use App\Models\StockRequest;
use App\Models\Transfer;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestAndTransferHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $operasional;
    private User $fulfillment;
    private User $gudang;
    private Gudang $gudangPusat;
    private Gudang $gudangLab;
    private Gudang $gudangToko;
    private Produk $bahanBaku;
    private Produk $produkJadi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->manager = User::factory()->create(['name' => 'Manager Admin']);
        $this->manager->assignRole('manager');

        $this->operasional = User::factory()->create(['name' => 'Staff Operasional Lab']);
        $this->operasional->assignRole('operasional');

        $this->fulfillment = User::factory()->create(['name' => 'Staff Fulfillment']);
        $this->fulfillment->assignRole('fulfillment');

        $this->gudang = User::factory()->create(['name' => 'Staff Gudang']);
        $this->gudang->assignRole('gudang');

        $this->gudangPusat = Gudang::create(['nama' => 'Gudang Pusat Bahan Baku', 'tipe' => 'bahan_baku', 'kode' => 'GD-BB', 'is_active' => true]);
        $this->gudangLab = Gudang::create(['nama' => 'Lab Operasional', 'tipe' => 'operasional', 'kode' => 'GD-LAB', 'is_active' => true]);
        $this->gudangToko = Gudang::create(['nama' => 'Gudang Fulfillment Toko', 'tipe' => 'toko', 'kode' => 'GD-TK', 'is_active' => true]);

        $this->bahanBaku = Produk::create(['nama' => 'Daun Teh Grade A', 'sku' => 'BB-TEH', 'tipe' => 'bahan_baku', 'satuan' => 'Kg', 'is_active' => true]);
        $this->produkJadi = Produk::create(['nama' => 'Artisan Tea Box', 'sku' => 'PJ-TEABOX', 'tipe' => 'produk_jadi', 'satuan' => 'Box', 'is_active' => true]);

        // Set initial stock in gudang pusat
        app(StokService::class)->masuk(
            $this->bahanBaku->id,
            $this->gudangPusat->id,
            500,
            'Saldo Awal Tes',
            null,
            $this->manager->id
        );
    }

    public function test_request_lifecycle_http(): void
    {
        // 1. Operasional creates draft request
        $response = $this->actingAs($this->operasional)->post(route('requests.store'), [
            'jenis' => 'request_bahan',
            'gudang_pemohon_id' => $this->gudangLab->id,
            'gudang_tujuan_id' => $this->gudangPusat->id,
            'action' => 'save_draft',
            'catatan' => 'Kebutuhan produksi teh mawar',
            'items' => [
                ['produk_id' => $this->bahanBaku->id, 'qty_diminta' => 50, 'keterangan' => 'Batch 01']
            ]
        ]);
        if (session('error')) {
            dump('Session Error: ' . session('error'));
        }
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $stockRequest = StockRequest::first();
        $this->assertNotNull($stockRequest);
        $this->assertEquals('draft', $stockRequest->status);
        $this->assertEquals('request_bahan', $stockRequest->jenis);

        // 2. Operasional submits request
        $this->actingAs($this->operasional)->post(route('requests.submit', $stockRequest))
            ->assertRedirect();
        $this->assertEquals('diajukan', $stockRequest->fresh()->status);

        // 3. Manager approves request
        $this->actingAs($this->manager)->post(route('requests.approve', $stockRequest), [
            'catatan' => 'Disetujui untuk diproses gudang'
        ])->assertRedirect();
        $this->assertEquals('disetujui', $stockRequest->fresh()->status);

        // 4. Test FR-REQ-08: Edit post-approval reverts to 'diajukan'
        $this->actingAs($this->operasional)->put(route('requests.update', $stockRequest), [
            'gudang_pemohon_id' => $this->gudangLab->id,
            'gudang_tujuan_id' => $this->gudangPusat->id,
            'catatan' => 'Revisi tambahan kuantitas',
            'items' => [
                ['produk_id' => $this->bahanBaku->id, 'qty_diminta' => 60, 'keterangan' => 'Revisi 60 Kg']
            ]
        ])->assertRedirect();

        $this->assertEquals('diajukan', $stockRequest->fresh()->status);
        $this->assertEquals(60.0, (float) $stockRequest->fresh()->items->first()->qty_diminta);

        // Re-approve by manager
        $this->actingAs($this->manager)->post(route('requests.approve', $stockRequest))->assertRedirect();
        $this->assertEquals('disetujui', $stockRequest->fresh()->status);
    }

    public function test_transfer_lifecycle_and_strict_anti_fraud_receiving_http(): void
    {
        // 1. Create a transfer draft
        $response = $this->actingAs($this->gudang)->post(route('transfers.store'), [
            'jenis' => 'distribusi',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangLab->id,
            'action' => 'save_draft',
            'catatan' => 'Pengiriman reguler armada box 01',
            'items' => [
                ['produk_id' => $this->bahanBaku->id, 'qty_dikirim' => 100, 'keterangan' => 'Karung 1 & 2']
            ]
        ]);
        $response->assertRedirect();

        $transfer = Transfer::first();
        $this->assertNotNull($transfer);
        $this->assertEquals('draft', $transfer->status);

        // 2. Ship transfer -> In Transit, stock in origin deducted
        $this->actingAs($this->gudang)->post(route('transfers.ship', $transfer))->assertRedirect();
        $this->assertEquals('dikirim', $transfer->fresh()->status);
        $this->assertEquals(400, app(StokService::class)->saldo($this->bahanBaku->id, $this->gudangPusat->id));

        $item = $transfer->items->first();

        // 3. Receive with fraud/invalid invariant (qty_baik 90 + qty_rusak 5 != 100) -> Must fail
        $responseFail = $this->actingAs($this->operasional)->post(route('transfers.receive', $transfer), [
            'items' => [
                [
                    'id' => $item->id,
                    'qty_baik' => 90,
                    'qty_rusak' => 5,
                    'keterangan_rusak' => '5 rusak tapi 5 hilang'
                ]
            ]
        ]);
        $responseFail->assertSessionHas('error');
        $this->assertEquals('dikirim', $transfer->fresh()->status);

        // 4. Receive with valid invariant (qty_baik 90 + qty_rusak 10 = 100) and defect note
        $responseSuccess = $this->actingAs($this->operasional)->post(route('transfers.receive', $transfer), [
            'items' => [
                [
                    'id' => $item->id,
                    'qty_baik' => 90,
                    'qty_rusak' => 10,
                    'keterangan_rusak' => '10 kg basah terkena hujan di perjalanan'
                ]
            ]
        ]);
        $responseSuccess->assertRedirect();

        $transferFresh = $transfer->fresh();
        $this->assertEquals('selesai', $transferFresh->status);
        $this->assertEquals(90.0, (float) $transferFresh->items->first()->qty_baik);
        $this->assertEquals(10.0, (float) $transferFresh->items->first()->qty_rusak);

        // Destination received 90 good stock
        $this->assertEquals(90, app(StokService::class)->saldo($this->bahanBaku->id, $this->gudangLab->id));

        // 5. Verify defect dashboard index renders and includes the defect metrics
        $indexResponse = $this->actingAs($this->manager)->get(route('transfers.index', ['year' => date('Y')]));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Dashboard Barang Rusak');
        $indexResponse->assertSee('Total Barang Rusak');
        $indexResponse->assertSee('10.00'); // 10.00 rusak displayed
    }

    public function test_restricted_operasional_user_can_view_requests_datatable(): void
    {
        $this->operasional->update(['warehouse_access_type' => 'restricted']);
        $this->fulfillment->update(['warehouse_access_type' => 'restricted']);

        // Request operasional (melibatkan gudangLab milik operasional)
        $opReq = StockRequest::create([
            'no_request' => 'REQ-OP-001',
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $this->gudangLab->id,
            'gudang_tujuan_id' => $this->gudangPusat->id,
            'status' => 'diajukan',
            'created_by' => $this->operasional->id,
        ]);

        // Request tidak terkait gudangLab (toko <-> pusat oleh fulfillment)
        $fulfillmentReq = StockRequest::create([
            'no_request' => 'REQ-FUL-001',
            'jenis' => 'request_produk_jadi',
            'gudang_asal_id' => $this->gudangToko->id,
            'gudang_tujuan_id' => $this->gudangPusat->id,
            'status' => 'diajukan',
            'created_by' => $this->fulfillment->id,
        ]);

        $response = $this->actingAs($this->operasional)->getJson(route('requests.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $response->assertOk()
            ->assertJsonMissingPath('error')
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonFragment(['no_request' => $opReq->no_request])
            ->assertJsonMissing(['no_request' => $fulfillmentReq->no_request]);
    }

    public function test_transfer_create_from_request_preserves_item_metadata_on_insufficient_stock_redirect(): void
    {
        $stockRequest = StockRequest::create([
            'no_request' => 'REQ-STOK-KURANG-01',
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $this->gudangLab->id,
            'gudang_tujuan_id' => $this->gudangPusat->id,
            'status' => 'disetujui',
            'created_by' => $this->operasional->id,
        ]);

        $reqItem = $stockRequest->items()->create([
            'produk_id' => $this->bahanBaku->id,
            'qty_diminta' => 999,
        ]);

        $createUrl = route('transfers.create', ['request_id' => $stockRequest->id]);

        // gudangPusat hanya punya 500 stok; kirim 999 harus gagal & kembali dengan old input
        $response = $this->actingAs($this->gudang)
            ->from($createUrl)
            ->followingRedirects()
            ->post(route('transfers.store'), [
                'request_id' => $stockRequest->id,
                'jenis' => 'pemenuhan_request_bahan',
                'gudang_asal_id' => $this->gudangPusat->id,
                'gudang_tujuan_id' => $this->gudangLab->id,
                'action' => 'ship',
                'items' => [
                    [
                        'request_item_id' => $reqItem->id,
                        'produk_id' => $this->bahanBaku->id,
                        'qty_dikirim' => 999,
                        'keterangan' => 'Kirim full request',
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertSee($stockRequest->no_request);
        $response->assertSee('Qty Diminta');
        $response->assertDontSee('Tambah Baris');
        $response->assertSee($this->bahanBaku->nama);
        $response->assertSee($this->bahanBaku->sku);

        $html = $response->getContent();
        $this->assertSame(1, preg_match("/items:\s*JSON\.parse\('([^']+)'\)/", $html, $matches));
        $decodedItems = json_decode(json_decode('"' . $matches[1] . '"'), true);

        $this->assertIsArray($decodedItems);
        $this->assertCount(1, $decodedItems);
        $this->assertNotEmpty($decodedItems[0]['request_item_id']);
        $this->assertEquals($this->bahanBaku->id, $decodedItems[0]['produk_id']);
        $this->assertSame($this->bahanBaku->nama, $decodedItems[0]['nama']);
        $this->assertSame($this->bahanBaku->sku, $decodedItems[0]['sku']);
        $this->assertSame($this->bahanBaku->satuan, $decodedItems[0]['satuan']);
        $this->assertEquals(999, $decodedItems[0]['qty_diminta']);
        $this->assertEquals(500, $decodedItems[0]['stok_tersedia']);
        $this->assertEquals(999, $decodedItems[0]['qty_dikirim']);
        $this->assertSame('Kirim full request', $decodedItems[0]['keterangan']);
    }

    public function test_request_create_exposes_batch_material_prefills_and_uid_reactive_list(): void
    {
        $kemasan = Produk::create([
            'nama' => 'Botol Kaca 50ml',
            'sku' => 'KMS-50',
            'tipe' => 'kemas',
            'satuan' => 'Pcs',
            'is_active' => true,
        ]);

        $batch = \App\Models\BatchProduksi::create([
            'no_batch' => 'BATCH-AUTO-01',
            'produk_id' => $this->produkJadi->id,
            'qty_rencana' => 100,
            'gudang_operasional_id' => $this->gudangLab->id,
            'status' => 'rencana',
            'tanggal' => now()->toDateString(),
            'created_by' => $this->operasional->id,
        ]);
        $batch->alokasi()->create(['bahan_id' => $this->bahanBaku->id, 'qty_dialokasikan' => 50, 'status' => 'aktif']);
        $batch->alokasi()->create(['bahan_id' => $kemasan->id, 'qty_dialokasikan' => 100, 'status' => 'aktif']);

        // Buka /requests/create tanpa query batch_id (user akan memilih batch langsung di dropdown)
        $response = $this->actingAs($this->operasional)->get(route('requests.create'));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('@change="onBatchChange()"', $html);
        $this->assertStringContainsString(':key="item._uid"', $html);
        $this->assertDoesNotMatchRegularExpression('/<template\s+x-for="\(item,\s*index\)\s+in\s+items"\s*:key="index"/', $html);

        $this->assertSame(
            1,
            preg_match("/batchPrefills:\s*(?:JSON\.parse\('([^']+)'\)|(\{.*?\})),\n/s", $html, $matches)
        );
        $rawJson = !empty($matches[1]) ? json_decode('"' . $matches[1] . '"') : $matches[2];
        $batchPrefills = json_decode($rawJson, true);

        $this->assertIsArray($batchPrefills);
        $key = (string) $batch->id;
        $this->assertArrayHasKey($key, $batchPrefills);
        $this->assertSame('BATCH-AUTO-01', $batchPrefills[$key]['no_batch']);
        $this->assertSame((string) $this->gudangLab->id, $batchPrefills[$key]['gudang_pemohon_id']);
        $this->assertSame((string) $this->gudangPusat->id, $batchPrefills[$key]['gudang_tujuan_id']);

        $items = collect($batchPrefills[$key]['items']);
        $this->assertCount(2, $items);

        $teh = $items->firstWhere('produk_id', $this->bahanBaku->id);
        $this->assertNotNull($teh);
        $this->assertEquals(50, $teh['qty_diminta']);
        $this->assertSame('Kg', $teh['satuan']);
        $this->assertSame('BB-TEH', $teh['selectedItem']['sku']);
        $this->assertSame('Daun Teh Grade A', $teh['selectedItem']['nama']);

        $btl = $items->firstWhere('produk_id', $kemasan->id);
        $this->assertNotNull($btl);
        $this->assertEquals(100, $btl['qty_diminta']);
        $this->assertSame('KMS-50', $btl['selectedItem']['sku']);
    }

    public function test_request_create_with_batch_id_query_prefills_items_and_saves_request(): void
    {
        $batch = \App\Models\BatchProduksi::create([
            'no_batch' => 'BATCH-QUERY-02',
            'produk_id' => $this->produkJadi->id,
            'qty_rencana' => 40,
            'gudang_operasional_id' => $this->gudangLab->id,
            'status' => 'release',
            'tanggal' => now()->toDateString(),
            'created_by' => $this->operasional->id,
        ]);
        $batch->alokasi()->create(['bahan_id' => $this->bahanBaku->id, 'qty_dialokasikan' => 25, 'status' => 'aktif']);

        $response = $this->actingAs($this->operasional)->get(route('requests.create', ['batch_id' => $batch->id]));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertSame(1, preg_match("/const\s+initialRows\s*=\s*JSON\.parse\('([^']+)'\);/", $html, $matches));
        $initialItems = json_decode(json_decode('"' . $matches[1] . '"'), true);
        $this->assertCount(1, $initialItems);
        $this->assertEquals($this->bahanBaku->id, $initialItems[0]['produk_id']);
        $this->assertEquals(25, $initialItems[0]['qty_diminta']);
        $this->assertSame('BB-TEH', $initialItems[0]['selectedItem']['sku']);
        $this->assertTrue($initialItems[0]['fromBatch']);

        $storeRes = $this->actingAs($this->operasional)->post(route('requests.store'), [
            'jenis' => 'request_bahan',
            'gudang_pemohon_id' => $this->gudangLab->id,
            'gudang_tujuan_id' => $this->gudangPusat->id,
            'batch_id' => $batch->id,
            'action' => 'submit',
            'items' => [
                [
                    'produk_id' => $this->bahanBaku->id,
                    'qty_diminta' => 25,
                    'keterangan' => 'Alokasi batch BATCH-QUERY-02',
                ],
            ],
        ]);
        $storeRes->assertSessionHasNoErrors();
        $storeRes->assertRedirect();

        $created = StockRequest::latest('id')->firstOrFail();
        $this->assertSame($batch->id, $created->referensi_batch_id);
        $this->assertEquals(25.0, (float) $created->items()->firstOrFail()->qty_diminta);
    }

    public function test_transfer_create_renders_searchable_product_dropdown_unit_and_stock_validation(): void
    {
        $manager = \App\Models\User::where('email', 'manager@heavenscent.id')->first();
        if (! $manager) {
            $this->seed();
            $manager = \App\Models\User::where('email', 'manager@heavenscent.id')->firstOrFail();
        }

        $res = $this->actingAs($manager)->get(route('transfers.create'));
        $res->assertOk();

        $html = $res->getContent();
        $this->assertSame(0, preg_match('/<select\b[^>]*produk_id/', $html));
        $this->assertStringContainsString('productSearchSelect(', $html);
        $this->assertStringContainsString(route('produk.select-data', [], false), $html);
        $this->assertStringContainsString('overflow-visible', $html);
        $this->assertStringContainsString(route('stok.current-stock', [], false), $html);
        $this->assertStringContainsString('data.qty ?? data.stok_tersedia', $html);
        $this->assertStringContainsString('displaySatuan(item)', $html);
        $this->assertStringContainsString('isExceedingStock(item)', $html);
        $this->assertStringContainsString('Melebihi stok tersedia!', $html);
        $this->assertStringContainsString('@submit="onSubmit($event)"', $html);
    }

    public function test_transfer_create_from_batch_prefills_unit_satuan_and_selected_item(): void
    {
        $manager = \App\Models\User::where('email', 'manager@heavenscent.id')->first();
        if (! $manager) {
            $this->seed();
            $manager = \App\Models\User::where('email', 'manager@heavenscent.id')->firstOrFail();
        }

        $gudangOps = \App\Models\Gudang::where('kode', 'GD-OPS')->first()
            ?? \App\Models\Gudang::where('tipe', 'operasional')->firstOrFail();
        $gudangFf = \App\Models\Gudang::where('kode', 'FF-PUSAT')->first()
            ?? \App\Models\Gudang::where('id', '!=', $gudangOps->id)->firstOrFail();

        $produk = \App\Models\Produk::create([
            'sku' => 'SKU-TRF-UNIT-01',
            'nama' => 'Serum Batch Prefill',
            'tipe' => 'produk_jadi',
            'satuan' => 'Botol-Test',
            'is_active' => true,
            'harga_hpp' => 15000,
        ]);

        \App\Models\Stok::updateOrCreate(
            ['produk_id' => $produk->id, 'gudang_id' => $gudangOps->id],
            ['qty_saat_ini' => 45]
        );

        $batch = \App\Models\BatchProduksi::create([
            'no_batch' => 'BATCH-TRF-UNIT-01',
            'produk_id' => $produk->id,
            'qty_rencana' => 50,
            'qty_baik' => 45,
            'gudang_operasional_id' => $gudangOps->id,
            'gudang_tujuan_rencana_id' => $gudangFf->id,
            'status' => 'selesai',
            'tanggal' => now()->toDateString(),
            'created_by' => $manager->id,
        ]);

        \App\Models\BatchProduksiOutput::create([
            'batch_id' => $batch->id,
            'produk_id' => $produk->id,
            'qty_rencana' => 50,
            'qty_baik' => 45,
            'gudang_tujuan_rencana_id' => $gudangFf->id,
        ]);

        $res = $this->actingAs($manager)->get(route('transfers.create', ['batch_id' => $batch->id]));
        $res->assertOk();

        $html = $res->getContent();
        $this->assertSame(1, preg_match("/items:\s*JSON\.parse\('([^']+)'\)/", $html, $matches));

        $decoded = json_decode((string) json_decode('"' . $matches[1] . '"', true), true);
        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);
        $this->assertSame($produk->id, $decoded[0]['produk_id']);
        $this->assertSame('Botol-Test', $decoded[0]['satuan']);
        $this->assertSame('SKU-TRF-UNIT-01', $decoded[0]['selectedItem']['sku']);
        $this->assertSame('Botol-Test', $decoded[0]['selectedItem']['satuan']);
    }
}
