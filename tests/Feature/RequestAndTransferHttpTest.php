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
}
