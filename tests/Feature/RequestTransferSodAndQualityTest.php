<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\RequestTransfer;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestTransferSodAndQualityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_separation_of_duties_prevents_sender_from_receiving_transfer(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $fulfillment = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();

        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $produk = Produk::produkJadi()->firstOrFail();

        // Siapkan stok di gudang operasional
        $stokService = app(StokService::class);
        $stokService->masuk($produk->id, $gudangOp->id, 500, 'mutasi_manual');

        // Operasional membuat transfer dan mengirim barang
        $this->actingAs($operasional);
        $this->post(route('rt.store'), [
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 100],
            ],
        ]);

        $rt = RequestTransfer::with('items')->latest()->firstOrFail();
        $rtItem = $rt->items->first();

        $this->post(route('rt.transition', $rt), [
            'aksi' => 'ship',
            'items' => [
                ['id' => $rtItem->id, 'qty' => 100],
            ],
        ]);

        $rt->refresh();
        $this->assertEquals('dikirim', $rt->status);

        // Uji SoD: Operasional (pengirim) mencoba klik konfirmasi terima -> DITOLAK 403
        $response = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $rtItem->id, 'qty_baik' => 100, 'qty_rusak' => 0],
            ],
        ]);
        $response->assertForbidden();

        // Dokumen tetap berstatus 'dikirim' (belum diterima)
        $rt->refresh();
        $this->assertEquals('dikirim', $rt->status);

        // Uji SoD: Petugas Fulfillment tujuan melakukan konfirmasi terima -> BERHASIL
        $this->actingAs($fulfillment);
        $receiveRes = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                [
                    'id' => $rtItem->id,
                    'qty_baik' => 97,
                    'qty_rusak' => 3,
                    'keterangan_rusak' => '3 botol pecah akibat guncangan ekspedisi',
                ],
            ],
        ]);
        $receiveRes->assertSessionHas('success');

        $rt->refresh();
        $rtItem->refresh();

        $this->assertEquals('selesai', $rt->status);
        $this->assertEquals(100, (float) $rtItem->qty_diterima);
        $this->assertEquals(97, (float) $rtItem->qty_baik);
        $this->assertEquals(3, (float) $rtItem->qty_rusak);
        $this->assertEquals('3 botol pecah akibat guncangan ekspedisi', $rtItem->keterangan_rusak);

        // Stok aktif fulfillment HANYA bertambah sebesar qty_baik (97), bukan 100!
        $this->assertEquals(97, $stokService->saldo($produk->id, $gudangFf->id));
    }

    public function test_location_based_scoping_on_request_transfers(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $ffSby = Gudang::where('kode', 'FF-SBY')->firstOrFail();
        $ffSolo = Gudang::where('kode', 'FF-SOLO')->firstOrFail();
        $produk = Produk::produkJadi()->firstOrFail();

        // Buat 1 transfer ke SBY dan 1 transfer ke SOLO
        RequestTransfer::create([
            'no_transaksi' => 'TR-TEST-SBY',
            'jenis' => 'antar_fulfillment',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $ffSby->id,
            'status' => 'draft',
            'created_by' => $manager->id,
        ]);

        RequestTransfer::create([
            'no_transaksi' => 'TR-TEST-SOLO',
            'jenis' => 'antar_fulfillment',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $ffSolo->id,
            'status' => 'draft',
            'created_by' => $manager->id,
        ]);

        // Buat user khusus Fulfillment Surabaya
        $userSby = User::factory()->create(['name' => 'Staf SBY']);
        $userSby->assignRole('fulfillment');
        $userSby->gudangs()->attach($ffSby->id, ['is_primary' => true]);

        // Akses data sebagai user SBY: hanya melihat transfer yang melibatkan SBY
        $this->actingAs($userSby);
        $resSby = $this->getJson(route('rt.data'));
        $resSby->assertOk();
        $dataSby = $resSby->json('data');
        $transaksiSby = collect($dataSby)->pluck('no_transaksi')->all();
        $this->assertContains('TR-TEST-SBY', $transaksiSby);
        $this->assertNotContains('TR-TEST-SOLO', $transaksiSby);

        // Akses data sebagai Manager: melihat SEMUA transaksi
        $this->actingAs($manager);
        $resMgr = $this->getJson(route('rt.data'));
        $resMgr->assertOk();
        $dataMgr = $resMgr->json('data');
        $transaksiMgr = collect($dataMgr)->pluck('no_transaksi')->all();
        $this->assertContains('TR-TEST-SBY', $transaksiMgr);
        $this->assertContains('TR-TEST-SOLO', $transaksiMgr);
    }

    public function test_receive_item_uom_step_rendering_and_validation(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $fulfillment = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();

        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();

        $produkJadi = Produk::where('satuan', 'pcs')->firstOrFail();
        $produkBahan = Produk::where('satuan', 'ml')->firstOrFail();

        $stokService = app(StokService::class);
        $stokService->masuk($produkJadi->id, $gudangOp->id, 100, 'mutasi_manual');
        $stokService->masuk($produkBahan->id, $gudangOp->id, 500, 'mutasi_manual');

        $this->actingAs($operasional);
        $this->post(route('rt.store'), [
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'items' => [
                ['produk_id' => $produkJadi->id, 'qty_diminta' => 10],
                ['produk_id' => $produkBahan->id, 'qty_diminta' => 50.5],
            ],
        ]);

        $rt = RequestTransfer::with('items.produk')->latest()->firstOrFail();
        $itemPcs = $rt->items->firstWhere('produk_id', $produkJadi->id);
        $itemMl = $rt->items->firstWhere('produk_id', $produkBahan->id);

        $this->post(route('rt.transition', $rt), [
            'aksi' => 'ship',
            'items' => [
                ['id' => $itemPcs->id, 'qty' => 10],
                ['id' => $itemMl->id, 'qty' => 50.5],
            ],
        ]);

        $rt->refresh();
        $this->assertEquals('dikirim', $rt->status);

        // 1. Cek rendering Blade: PCS memiliki step="1", ML memiliki step="0.01"
        $this->actingAs($fulfillment);
        $viewRes = $this->get(route('rt.show', $rt));
        $viewRes->assertOk();
        $viewRes->assertSee('step="1"', false);
        $viewRes->assertSee('step="0.01"', false);
        $viewRes->assertSee("Qty Baik ({$itemPcs->produk->satuan})", false);
        $viewRes->assertSee("Qty Baik ({$itemMl->produk->satuan})", false);
        $viewRes->assertSee("event.preventDefault()", false);

        // 2. Cek validasi backend: Submisi desimal untuk PCS ditolak
        $invalidRes = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $itemPcs->id, 'qty_baik' => 8.5, 'qty_rusak' => 1.5],
                ['id' => $itemMl->id, 'qty_baik' => 50.5, 'qty_rusak' => 0],
            ],
        ]);
        $invalidRes->assertSessionHas('error');
        $this->assertStringContainsString('harus berupa bilangan bulat', session('error'));

        // Dokumen tetap 'dikirim' karena transaksi di-rollback
        $rt->refresh();
        $this->assertEquals('dikirim', $rt->status);

        // 3. Submisi valid: integer untuk PCS dan desimal untuk ML berhasil diterima
        $validRes = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $itemPcs->id, 'qty_baik' => 9, 'qty_rusak' => 1],
                ['id' => $itemMl->id, 'qty_baik' => 45.25, 'qty_rusak' => 5.25],
            ],
        ]);
        $validRes->assertSessionHas('success');

        $rt->refresh();
        $this->assertEquals('selesai', $rt->status);
        $itemPcs->refresh();
        $itemMl->refresh();

        $this->assertEquals(9, (float) $itemPcs->qty_baik);
        $this->assertEquals(1, (float) $itemPcs->qty_rusak);
        $this->assertEquals(45.25, (float) $itemMl->qty_baik);
        $this->assertEquals(5.25, (float) $itemMl->qty_rusak);
    }
}
