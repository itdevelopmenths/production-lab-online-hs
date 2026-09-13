<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\RequestTransfer;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestTransferSeparationOfDutiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_operasional_staff_with_global_access_cannot_receive_finished_goods_to_fulfillment(): void
    {
        // Setup staff operasional dengan default global warehouse access
        $operasionalGlobal = User::factory()->create([
            'warehouse_access_type' => 'global',
        ]);
        $operasionalGlobal->assignRole('operasional');

        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $produk = Produk::produkJadi()->firstOrFail();

        // Siapkan saldo stok di operasional
        app(StokService::class)->masuk($produk->id, $gudangOp->id, 200, 'mutasi_manual');

        // Operasional membuat transfer kirim_produk_jadi ke fulfillment
        $this->actingAs($operasionalGlobal);
        $this->post(route('rt.store'), [
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 50],
            ],
        ]);

        $rt = RequestTransfer::with('items')->latest()->firstOrFail();
        $rtItem = $rt->items->first();

        // Operasional mengirim barang
        $this->post(route('rt.transition', $rt), [
            'aksi' => 'ship',
            'items' => [
                ['id' => $rtItem->id, 'qty' => 50],
            ],
        ]);

        $rt->refresh();
        $this->assertEquals('dikirim', $rt->status);

        // 1. Verifikasi UI: staff operasional TIDAK mendapatkan tombol/form terima ($canReceive = false)
        $responseShow = $this->get(route('rt.show', $rt));
        $responseShow->assertOk();
        $responseShow->assertViewHas('canReceive', false);
        $responseShow->assertSee('Menunggu Penerimaan');

        // 2. Verifikasi Backend Guard SoD: jika staf operasional memaksa POST receive, wajib DITOLAK HTTP 403
        $responseReceive = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $rtItem->id, 'qty_baik' => 50, 'qty_rusak' => 0],
            ],
        ]);
        $responseReceive->assertForbidden();

        // Status dokumen tetap 'dikirim'
        $rt->refresh();
        $this->assertEquals('dikirim', $rt->status);
    }

    public function test_fulfillment_staff_can_receive_with_good_and_damaged_quantities(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $fulfillment = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();

        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $produk = Produk::produkJadi()->firstOrFail();

        app(StokService::class)->masuk($produk->id, $gudangOp->id, 200, 'mutasi_manual');

        // Kirim 100 unit dari Lab ke Fulfillment
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

        // Login sebagai staf Fulfillment
        $this->actingAs($fulfillment);

        // 1. Verifikasi UI: staff fulfillment berhak menerima
        $responseShow = $this->get(route('rt.show', $rt));
        $responseShow->assertOk();
        $responseShow->assertViewHas('canReceive', true);
        $responseShow->assertSee('Konfirmasi Penerimaan Barang');

        // 2. Eksekusi konfirmasi terima dengan QC: 96 baik, 4 rusak
        $responseReceive = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                [
                    'id' => $rtItem->id,
                    'qty_baik' => 96,
                    'qty_rusak' => 4,
                    'keterangan_rusak' => '4 botol bocor saat transit',
                ],
            ],
        ]);
        $responseReceive->assertSessionHas('success');

        $rt->refresh();
        $rtItem->refresh();

        $this->assertEquals('selesai', $rt->status);
        $this->assertEquals(100, (float) $rtItem->qty_diterima);
        $this->assertEquals(96, (float) $rtItem->qty_baik);
        $this->assertEquals(4, (float) $rtItem->qty_rusak);
        $this->assertEquals('4 botol bocor saat transit', $rtItem->keterangan_rusak);

        // 3. Stok fisik fulfillment HANYA bertambah sebesar Qty Baik (96 unit)
        $saldoFulfillment = app(StokService::class)->saldo($produk->id, $gudangFf->id);
        $this->assertEquals(96, $saldoFulfillment);
    }

    public function test_cannot_receive_quantity_exceeding_shipped(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $fulfillment = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();

        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $produk = Produk::produkJadi()->firstOrFail();

        app(StokService::class)->masuk($produk->id, $gudangOp->id, 100, 'mutasi_manual');

        $this->actingAs($operasional);
        $this->post(route('rt.store'), [
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 50],
            ],
        ]);

        $rt = RequestTransfer::with('items')->latest()->firstOrFail();
        $rtItem = $rt->items->first();

        $this->post(route('rt.transition', $rt), [
            'aksi' => 'ship',
            'items' => [
                ['id' => $rtItem->id, 'qty' => 50],
            ],
        ]);

        // Staf fulfillment mencoba menerima 55 unit (melebihi 50 yang dikirim)
        $this->actingAs($fulfillment);
        $res = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $rtItem->id, 'qty_baik' => 50, 'qty_rusak' => 5], // Total 55 > 50
            ],
        ]);

        $res->assertSessionHas('error');
        $rt->refresh();
        $this->assertEquals('dikirim', $rt->status);
    }

    public function test_manager_has_supervisory_override(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();

        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $produk = Produk::produkJadi()->firstOrFail();

        app(StokService::class)->masuk($produk->id, $gudangOp->id, 100, 'mutasi_manual');

        $this->actingAs($operasional);
        $this->post(route('rt.store'), [
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 30],
            ],
        ]);

        $rt = RequestTransfer::with('items')->latest()->firstOrFail();
        $rtItem = $rt->items->first();

        $this->post(route('rt.transition', $rt), [
            'aksi' => 'ship',
            'items' => [
                ['id' => $rtItem->id, 'qty' => 30],
            ],
        ]);

        // Manager melakukan konfirmasi terima
        $this->actingAs($manager);
        $res = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $rtItem->id, 'qty_baik' => 30, 'qty_rusak' => 0],
            ],
        ]);

        $res->assertSessionHas('success');
        $rt->refresh();
        $this->assertEquals('selesai', $rt->status);
    }

    public function test_operational_warehouse_staff_with_role_gudang_can_receive_at_gudang_operasional(): void
    {
        $gudangPusat = Gudang::where('is_pusat', true)->where('tipe', 'bahan_baku')->firstOrFail();
        $gudangOp = Gudang::where('tipe', 'operasional')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        // 1. Staf Gudang Operasional: Divisi Gudang, Role 'gudang', ditugaskan ke Gudang Operasional
        $stafGudangOps = User::factory()->create([
            'warehouse_access_type' => 'restricted',
        ]);
        $stafGudangOps->assignRole('gudang');
        $stafGudangOps->gudangs()->attach($gudangOp->id, ['is_primary' => true]);

        // Siapkan saldo di Gudang Bahan Baku Pusat
        app(StokService::class)->masuk($bahan->id, $gudangPusat->id, 500, 'mutasi_manual');

        // Buat transfer req_bahan dari Pusat ke Operasional
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);
        $this->post(route('rt.store'), [
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangPusat->id,
            'gudang_tujuan_id' => $gudangOp->id,
            'items' => [
                ['produk_id' => $bahan->id, 'qty_diminta' => 50],
            ],
        ]);

        $rt = RequestTransfer::with('items')->latest()->firstOrFail();
        $rtItem = $rt->items->first();

        // Submit & Approve (karena req_bahan memakai pipeline approval)
        $this->post(route('rt.transition', $rt), ['aksi' => 'submit']);
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);
        $this->post(route('rt.transition', $rt), ['aksi' => 'approve']);

        // Gudang Pusat memproses bahan (stok out dari pusat)
        $gudangPusatUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $this->actingAs($gudangPusatUser);
        $this->post(route('rt.transition', $rt), [
            'aksi' => 'process',
            'items' => [
                ['id' => $rtItem->id, 'qty' => 50],
            ],
        ]);

        $rt->refresh();
        $this->assertEquals('diproses', $rt->status);

        // Login sebagai Staf Gudang Operasional (Role 'gudang')
        $this->actingAs($stafGudangOps);

        // 1. Verifikasi UI: Staf Gudang Operasional berhak melihat tombol konfirmasi terima
        $responseShow = $this->get(route('rt.show', $rt));
        $responseShow->assertOk();
        $responseShow->assertViewHas('canReceive', true);
        $responseShow->assertSee('Konfirmasi Penerimaan Barang');

        // 2. Eksekusi penerimaan
        $res = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $rtItem->id, 'qty_baik' => 50, 'qty_rusak' => 0],
            ],
        ]);

        $res->assertSessionHas('success');
        $rt->refresh();
        $this->assertEquals('selesai', $rt->status);

        // Saldo di gudang operasional bertambah
        $saldoOp = app(StokService::class)->saldo($bahan->id, $gudangOp->id);
        $this->assertEquals(50, $saldoOp);
    }

    public function test_central_warehouse_staff_assigned_to_source_warehouse_cannot_receive_at_gudang_operasional(): void
    {
        $gudangPusat = Gudang::where('is_pusat', true)->where('tipe', 'bahan_baku')->firstOrFail();
        $gudangOp = Gudang::where('tipe', 'operasional')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        // Staf Gudang Pusat: Role 'gudang', ditugaskan ke Gudang Pusat
        $stafPusat = User::factory()->create([
            'warehouse_access_type' => 'restricted',
        ]);
        $stafPusat->assignRole('gudang');
        $stafPusat->gudangs()->attach($gudangPusat->id, ['is_primary' => true]);

        app(StokService::class)->masuk($bahan->id, $gudangPusat->id, 500, 'mutasi_manual');

        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);
        $this->post(route('rt.store'), [
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangPusat->id,
            'gudang_tujuan_id' => $gudangOp->id,
            'items' => [
                ['produk_id' => $bahan->id, 'qty_diminta' => 30],
            ],
        ]);

        $rt = RequestTransfer::with('items')->latest()->firstOrFail();
        $rtItem = $rt->items->first();

        $this->post(route('rt.transition', $rt), ['aksi' => 'submit']);
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);
        $this->post(route('rt.transition', $rt), ['aksi' => 'approve']);

        $this->actingAs($stafPusat);
        $this->post(route('rt.transition', $rt), [
            'aksi' => 'process',
            'items' => [
                ['id' => $rtItem->id, 'qty' => 30],
            ],
        ]);

        // Staf Pusat mencoba menerima barang yang dikirim dari gudangnya sendiri
        $responseShow = $this->get(route('rt.show', $rt));
        $responseShow->assertOk();
        $responseShow->assertViewHas('canReceive', false);
        $responseShow->assertSee('Menunggu Penerimaan');

        $res = $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $rtItem->id, 'qty_baik' => 30, 'qty_rusak' => 0],
            ],
        ]);
        $res->assertForbidden();
    }
}
