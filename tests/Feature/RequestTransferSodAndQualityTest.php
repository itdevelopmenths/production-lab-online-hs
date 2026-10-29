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

    public function test_cannot_receive_under_reported_quantity_mismatch_between_good_and_damaged(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $fulfillment = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();

        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $produk = Produk::produkJadi()->firstOrFail();

        app(StokService::class)->masuk($produk->id, $gudangOp->id, 2000, 'mutasi_manual');

        $this->actingAs($operasional);
        $this->post(route('rt.store'), [
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 1000],
            ],
        ]);

        $rt = RequestTransfer::with('items')->latest()->firstOrFail();
        $rtItem = $rt->items->first();

        $this->post(route('rt.transition', $rt), [
            'aksi' => 'ship',
            'items' => [
                ['id' => $rtItem->id, 'qty' => 1000],
            ],
        ]);

        // Kasus kecurangan: dikirim 1000, diinput bagus 800 dan rusak 0 (total 800 != 1000)
        $this->actingAs($fulfillment);
        $res = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $rtItem->id, 'qty_baik' => 800, 'qty_rusak' => 0],
            ],
        ]);

        $res->assertSessionHas('error');
        $this->assertStringContainsString('harus sama persis dengan jumlah dikirim', session('error'));

        $rt->refresh();
        $this->assertEquals('dikirim', $rt->status);
        $this->assertEquals(0, app(StokService::class)->saldo($produk->id, $gudangFf->id));
    }

    public function test_cannot_bypass_receive_validation_by_omitting_item_or_sending_partial_legacy_qty(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $fulfillment = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();

        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $produk = Produk::produkJadi()->firstOrFail();

        app(StokService::class)->masuk($produk->id, $gudangOp->id, 1500, 'mutasi_manual');

        $this->actingAs($operasional);
        $this->post(route('rt.store'), [
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 1000],
            ],
        ]);

        $rt = RequestTransfer::with('items')->latest()->firstOrFail();
        $rtItem = $rt->items->first();

        $this->post(route('rt.transition', $rt), [
            'aksi' => 'ship',
            'items' => [
                ['id' => $rtItem->id, 'qty' => 1000],
            ],
        ]);

        $this->actingAs($fulfillment);

        // 1. Submit tanpa array items yang cocok
        $resEmpty = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [],
        ]);
        $resEmpty->assertSessionHas('error');

        // 2. Submit legacy qty yang parsial (800 < 1000) tanpa rincian rusak
        $resPartial = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $rtItem->id, 'qty' => 800],
            ],
        ]);
        $resPartial->assertSessionHas('error');
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
                ['produk_id' => $produkBahan->id, 'qty_diminta' => 50],
            ],
        ]);

        $rt = RequestTransfer::with('items.produk')->latest()->firstOrFail();
        $itemPcs = $rt->items->firstWhere('produk_id', $produkJadi->id);
        $itemMl = $rt->items->firstWhere('produk_id', $produkBahan->id);

        $this->post(route('rt.transition', $rt), [
            'aksi' => 'ship',
            'items' => [
                ['id' => $itemPcs->id, 'qty' => 10],
                ['id' => $itemMl->id, 'qty' => 50],
            ],
        ]);

        $rt->refresh();
        $this->assertEquals('dikirim', $rt->status);

        // 1. Cek rendering Blade: Seluruh satuan (pcs & ml) hanya memiliki step="1" (tanpa step="0.01")
        $this->actingAs($fulfillment);
        $viewRes = $this->get(route('rt.show', $rt));
        $viewRes->assertOk();
        $viewRes->assertSee('step="1"', false);
        $viewRes->assertDontSee('step="0.01"', false);
        $viewRes->assertSee("Qty Baik ({$itemPcs->produk->satuan})", false);
        $viewRes->assertSee("Qty Baik ({$itemMl->produk->satuan})", false);
        $viewRes->assertSee("event.preventDefault()", false);

        // 2. Cek validasi backend: Submisi desimal untuk pcs maupun ml ditolak
        $invalidResPcs = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $itemPcs->id, 'qty_baik' => 8.5, 'qty_rusak' => 1.5],
                ['id' => $itemMl->id, 'qty_baik' => 50, 'qty_rusak' => 0],
            ],
        ]);
        $invalidResPcs->assertSessionHas('error');
        $this->assertStringContainsString('harus berupa bilangan bulat', session('error'));

        $invalidResMl = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $itemPcs->id, 'qty_baik' => 10, 'qty_rusak' => 0],
                ['id' => $itemMl->id, 'qty_baik' => 45.25, 'qty_rusak' => 4.75],
            ],
        ]);
        $invalidResMl->assertSessionHas('error');
        $this->assertStringContainsString('harus berupa bilangan bulat', session('error'));

        // Dokumen tetap 'dikirim' karena transaksi di-rollback
        $rt->refresh();
        $this->assertEquals('dikirim', $rt->status);

        // 3. Submisi valid: integer murni tanpa desimal dan jumlah pas diterima
        $validRes = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $itemPcs->id, 'qty_baik' => 9, 'qty_rusak' => 1],
                ['id' => $itemMl->id, 'qty_baik' => 45, 'qty_rusak' => 5],
            ],
        ]);
        $validRes->assertSessionHas('success');

        $rt->refresh();
        $this->assertEquals('selesai', $rt->status);
        $itemPcs->refresh();
        $itemMl->refresh();

        $this->assertEquals(9, (float) $itemPcs->qty_baik);
        $this->assertEquals(1, (float) $itemPcs->qty_rusak);
        $this->assertEquals(45, (float) $itemMl->qty_baik);
        $this->assertEquals(5, (float) $itemMl->qty_rusak);
    }

    public function test_gudang_staff_cannot_create_antar_fulfillment_or_out_of_scope_transfer(): void
    {
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $ffPusat = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $ffSby = Gudang::where('kode', 'FF-SBY')->firstOrFail();
        $produk = Produk::where('satuan', 'pcs')->firstOrFail();

        $this->actingAs($gudangUser);

        // 1. Pada form create, user gudang (bahan baku) hanya memiliki opsi jenis yang sesuai wewenangnya
        $createRes = $this->get(route('rt.create'));
        $createRes->assertOk();
        $allowedJenis = $createRes->viewData('allowedJenis');
        $this->assertContains('req_bahan', $allowedJenis);
        $this->assertNotContains('antar_fulfillment', $allowedJenis);

        // 2. Bypass submission mencoba membuat transfer antar fulfillment langsung ditolak 403 Forbidden
        $storeRes = $this->post(route('rt.store'), [
            'jenis' => 'antar_fulfillment',
            'gudang_asal_id' => $ffPusat->id,
            'gudang_tujuan_id' => $ffSby->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 15],
            ],
        ]);
        $storeRes->assertForbidden();
    }

    public function test_restricted_user_cannot_view_or_edit_or_transition_transfer_outside_their_lbac_scope(): void
    {
        $fulfillmentUser = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $managerUser = User::where('email', 'manager@heavenscent.id')->firstOrFail();

        $ffPusat = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $ffSby = Gudang::where('kode', 'FF-SBY')->firstOrFail();
        $produk = Produk::where('satuan', 'pcs')->firstOrFail();

        // 1. Buat transfer antar fulfillment oleh staff fulfillment
        $this->actingAs($fulfillmentUser);
        $this->post(route('rt.store'), [
            'jenis' => 'antar_fulfillment',
            'gudang_asal_id' => $ffPusat->id,
            'gudang_tujuan_id' => $ffSby->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 20],
            ],
        ]);

        $rt = RequestTransfer::latest()->firstOrFail();
        $this->assertEquals('antar_fulfillment', $rt->jenis);

        // 2. Staff Gudang (hanya berwewenang bahan_baku) tidak boleh mengakses dokumen ini
        $this->actingAs($gudangUser);
        $this->get(route('rt.show', $rt))->assertForbidden();
        $this->get(route('rt.edit', $rt))->assertForbidden();
        $this->put(route('rt.update', $rt), [
            'jenis' => 'antar_fulfillment',
            'gudang_asal_id' => $ffPusat->id,
            'gudang_tujuan_id' => $ffSby->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 25],
            ],
        ])->assertForbidden();
        $this->get(route('rt.surat-jalan', $rt))->assertForbidden();
        $this->post(route('rt.transition', $rt), [
            'aksi' => 'ship',
        ])->assertForbidden();

        // 3. Manager (Global LBAC) tetap dapat melihat dokumen
        $this->actingAs($managerUser);
        $this->get(route('rt.show', $rt))->assertOk();
    }

    public function test_cancellation_modal_and_form_rendering_on_index_and_show(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $produk = Produk::where('satuan', 'pcs')->firstOrFail();

        $this->actingAs($operasional);

        // 1. Verifikasi index.blade.php memiliki modal pembatalan dan textarea alasan
        $indexRes = $this->get(route('rt.index'));
        $indexRes->assertOk();
        $indexRes->assertSee('openCancelModal', false);
        $indexRes->assertSee('name="alasan"', false);
        $indexRes->assertSee('minlength="5"', false);
        $indexRes->assertSee('btn-cancel-rt', false);

        // 2. Buat transfer baru
        $this->post(route('rt.store'), [
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 30],
            ],
        ]);
        $rt = RequestTransfer::latest()->firstOrFail();

        // 3. Verifikasi show.blade.php memiliki modal dan TIDAK menggunakan window.confirm pop-up
        $showRes = $this->get(route('rt.show', $rt));
        $showRes->assertOk();
        $showRes->assertSee('openCancelModal', false);
        $showRes->assertSee('name="alasan"', false);
        $showRes->assertSee('minlength="5"', false);
        $showRes->assertDontSee("onsubmit=\"return confirm(", false);

        // 4. Batalkan transfer
        $alasan = 'Revisi alokasi produksi mendadak';
        $this->post(route('rt.transition', $rt), [
            'aksi' => 'cancel',
            'alasan' => $alasan,
        ])->assertSessionHas('success');

        // 5. Verifikasi tampilan show.blade.php setelah dibatalkan menampilkan alert alasan pembatalan
        $showCancelledRes = $this->get(route('rt.show', $rt));
        $showCancelledRes->assertOk();
        $showCancelledRes->assertSee('Dokumen Telah Dibatalkan', false);
        $showCancelledRes->assertSee($alasan, false);
    }

    public function test_cancellation_requires_valid_reason_and_records_audit(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $produk = Produk::where('satuan', 'pcs')->firstOrFail();

        $this->actingAs($operasional);
        $this->post(route('rt.store'), [
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 50],
            ],
        ]);

        $rt = RequestTransfer::latest()->firstOrFail();

        // 1. Gagal jika alasan pembatalan tidak diisi
        $emptyRes = $this->post(route('rt.transition', $rt), [
            'aksi' => 'cancel',
        ]);
        $emptyRes->assertSessionHasErrors(['alasan']);

        // 2. Gagal jika alasan pembatalan kurang dari 5 karakter
        $shortRes = $this->post(route('rt.transition', $rt), [
            'aksi' => 'cancel',
            'alasan' => 'fail',
        ]);
        $shortRes->assertSessionHasErrors(['alasan']);

        // 3. Berhasil membatalkan dengan alasan yang valid
        $alasan = 'Salah menentukan gudang tujuan mutasi';
        $validRes = $this->post(route('rt.transition', $rt), [
            'aksi' => 'cancel',
            'alasan' => $alasan,
        ]);
        $validRes->assertSessionHas('success');

        $rt->refresh();
        $this->assertEquals('dibatalkan', $rt->status);
        $this->assertEquals($alasan, $rt->alasan_batal);

        // Pastikan terekam di riwayat audit
        $latestAudit = \App\Models\OperationalAudit::where('auditable_type', RequestTransfer::class)
            ->where('auditable_id', (string) $rt->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($latestAudit);
        $this->assertEquals('cancelled', $latestAudit->event);
        $this->assertEquals($alasan, $latestAudit->metadata['alasan_pembatalan'] ?? null);
    }
}
