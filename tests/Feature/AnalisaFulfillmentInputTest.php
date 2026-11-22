<?php

namespace Tests\Feature;

use App\Models\AnalisaFulfillmentInput;
use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AnalisaService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Analisa Produk Jadi Fulfillment: stok & akan datang live, serta input parameter per gudang.
 */
class AnalisaFulfillmentInputTest extends TestCase
{
    use RefreshDatabase;

    private Produk $goh;

    private Gudang $ffPusat;

    private Gudang $ffSby;

    private Gudang $ffSolo;

    private User $fulfillment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->goh = Produk::where('sku', 'GOH-P50')->firstOrFail();
        $this->ffPusat = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $this->ffSby = Gudang::where('kode', 'FF-SBY')->firstOrFail();
        $this->ffSolo = Gudang::where('kode', 'FF-SOLO')->firstOrFail();
        $this->fulfillment = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();
    }

    public function test_lead_time_distribusi_terukur_dari_request_sampai_transfer_dikirim(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();

        // Dua request produk jadi ke FF-SBY: 2 hari dan 4 hari sampai transfer dikirim -> rata-rata 3, max 4.
        foreach ([['REQ-LT-1', 'TRF-LT-1', 2], ['REQ-LT-2', 'TRF-LT-2', 4]] as [$noReq, $noTrf, $hari]) {
            $req = \App\Models\StockRequest::create([
                'no_request' => $noReq,
                'jenis' => 'request_produk_jadi',
                'gudang_asal_id' => $this->ffSby->id,
                'gudang_tujuan_id' => $this->ffPusat->id,
                'status' => 'selesai',
                'created_by' => $manager->id,
            ]);
            $req->forceFill(['created_at' => now()->subDays($hari + 1)])->saveQuietly();

            $t = Transfer::create([
                'no_transaksi' => $noTrf,
                'jenis' => 'kirim_produk_jadi',
                'gudang_asal_id' => $this->ffPusat->id,
                'gudang_tujuan_id' => $this->ffSby->id,
                'request_id' => $req->id,
                'status' => 'selesai',
                'waktu_dikirim' => now()->subDay(),
                'created_by' => $manager->id,
            ]);
            $t->items()->create(['produk_id' => $this->goh->id, 'qty_dikirim' => 10, 'qty_rusak' => 0]);
        }

        $row = app(AnalisaService::class)->fulfillmentRows()[0];
        $sby = collect($row['per_gudang'])->keyBy('gudang_id')[$this->ffSby->id];

        $this->assertEquals(3.0, $sby['lead_time_distribusi']);
        $this->assertEquals(4.0, $sby['lead_time_distribusi_max']);
        $this->assertSame('transfer', $sby['lead_time_distribusi_sumber']);
        $this->assertSame(2, $sby['jumlah_transfer_selesai']);
        // Batas minimum = ADU * (LT terukur + buffer 1) = 3000 * 4.
        $this->assertEquals(3000 * 4, $sby['batas_minimum']);

        // Gudang tanpa riwayat transfer tetap memakai nilai manual.
        $solo = collect($row['per_gudang'])->keyBy('gudang_id')[$this->ffSolo->id];
        $this->assertSame('manual', $solo['lead_time_distribusi_sumber']);
    }

    public function test_stok_dan_akan_datang_diambil_live_bukan_dari_kolom_tersimpan(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();

        // Stok fisik di FF-SBY.
        app(StokService::class)->masuk($this->goh->id, $this->ffSby->id, 4000, 'mutasi_manual', null, 'Saldo awal');

        // Transfer in-transit ke FF-SOLO (dihitung), dan draft ke FF-SOLO (tidak dihitung).
        foreach ([['TRF-FF-001', 'dikirim', 500], ['TRF-FF-002', 'draft', 999]] as [$no, $status, $qty]) {
            $t = Transfer::create([
                'no_transaksi' => $no,
                'jenis' => 'antar_fulfillment',
                'gudang_asal_id' => $this->ffPusat->id,
                'gudang_tujuan_id' => $this->ffSolo->id,
                'status' => $status,
                'created_by' => $manager->id,
            ]);
            $t->items()->create(['produk_id' => $this->goh->id, 'qty_dikirim' => $qty, 'qty_rusak' => 0]);
        }

        // Rencana batch aktif dengan tujuan FF-PUSAT.
        BatchProduksi::create([
            'no_batch' => 'B-FF-001',
            'produk_id' => $this->goh->id,
            'qty_rencana' => 1200,
            'gudang_tujuan_rencana_id' => $this->ffPusat->id,
            'status' => 'rencana',
            'tanggal' => now()->toDateString(),
            'created_by' => $manager->id,
        ]);

        // Nilai statis yang tersimpan di seeder tidak boleh lagi dipakai.
        AnalisaFulfillmentInput::where('produk_id', $this->goh->id)->update(['stok_saat_ini' => 99999, 'akan_datang' => 99999]);

        $rows = app(AnalisaService::class)->fulfillmentRows();
        $this->assertCount(1, $rows);
        $row = $rows[0];

        // Seeder: terjual 60000/90000/45000 -> ADU 2000/3000/1500; LT 2 + buffer 1 + review 15.
        $this->assertEquals(6500 * 3, $row['batas_minimum_total']);
        $this->assertEquals(6500 * 18, $row['target_stock_total']);
        $this->assertEquals(4000, $row['stok_total']);
        $this->assertEquals(500 + 1200, $row['akan_datang']);
        $this->assertEquals(5700, $row['stok_all']);
        $this->assertSame('order', $row['status']);
        // Selisih 117000 - 5700 = 111300, dibulatkan ke kelipatan MOQ 12.
        $this->assertEquals(111300, $row['qty_order']);

        $perGudang = collect($row['per_gudang'])->keyBy('gudang_id');
        $this->assertEquals(4000, $perGudang[$this->ffSby->id]['stok_saat_ini']);
        $this->assertEquals(500, $perGudang[$this->ffSolo->id]['akan_datang']);
        $this->assertEquals(1200, $perGudang[$this->ffPusat->id]['akan_datang']);

        $this->actingAs($this->fulfillment)
            ->getJson(route('analisa.fulfillment.data'))
            ->assertOk()
            ->assertJsonFragment(['sku' => 'GOH-P50', 'stok_all' => 5700.0]);
    }

    public function test_simpan_parameter_menambah_produk_baru_dan_menghapus_gudang_yang_tidak_dipilih(): void
    {
        $this->actingAs($this->fulfillment);

        $produkBaru = Produk::create([
            'sku' => 'SW-P50',
            'nama' => 'Parfum Secret Whispers 50ml',
            'tipe' => 'produk_jadi',
            'satuan' => 'pcs',
            'satuan_order_moq' => 108,
        ]);

        $this->getJson(route('analisa.fulfillment.available-produk', ['q' => 'whispers']))
            ->assertOk()
            ->assertJsonFragment(['sku' => 'SW-P50', 'di_analisa' => false]);

        $payload = fn (array $gudangIds) => [
            'produk_id' => $produkBaru->id,
            'gudang' => array_map(fn ($id) => [
                'gudang_id' => $id,
                'terjual_rata_rata_4bulan' => 3000,
                'lead_time_distribusi' => 3,
                'buffer_distribusi' => 2,
                'review_period' => 15,
            ], $gudangIds),
        ];

        $this->postJson(route('analisa.fulfillment.save'), $payload([$this->ffPusat->id, $this->ffSby->id]))
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame(2, AnalisaFulfillmentInput::where('produk_id', $produkBaru->id)->count());

        // Simpan ulang hanya FF-SBY -> baris FF-PUSAT terhapus.
        $this->postJson(route('analisa.fulfillment.save'), $payload([$this->ffSby->id]))->assertOk();
        $this->assertEquals(
            [$this->ffSby->id],
            AnalisaFulfillmentInput::where('produk_id', $produkBaru->id)->pluck('gudang_id')->all()
        );

        // ADU 100 x (3 + 2 + 15) = 2000 target; stok 0 -> order 2000 dibulatkan ke 108 = 2052.
        $row = collect(app(AnalisaService::class)->fulfillmentRows())->firstWhere('produk_id', $produkBaru->id);
        $this->assertEquals(2000, $row['target_stock_total']);
        $this->assertEquals(2052, $row['qty_order']);

        $this->getJson(route('analisa.fulfillment.detail', $produkBaru))
            ->assertOk()
            ->assertJsonFragment(['gudang_id' => $this->ffSby->id, 'aktif' => true, 'lead_time_distribusi' => 3])
            ->assertJsonFragment(['gudang_id' => $this->ffPusat->id, 'aktif' => false]);

        $this->deleteJson(route('analisa.fulfillment.destroy', $produkBaru))->assertOk();
        $this->assertSame(0, AnalisaFulfillmentInput::where('produk_id', $produkBaru->id)->count());
    }

    public function test_simpan_ditolak_untuk_gudang_non_fulfillment_atau_produk_bukan_produk_jadi(): void
    {
        $gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $bahan = Produk::where('sku', 'ALK-01')->firstOrFail();
        $row = ['terjual_rata_rata_4bulan' => 10, 'lead_time_distribusi' => 1, 'buffer_distribusi' => 1, 'review_period' => 15];

        $this->actingAs($this->fulfillment);

        $this->postJson(route('analisa.fulfillment.save'), [
            'produk_id' => $this->goh->id,
            'gudang' => [['gudang_id' => $gudangPusat->id] + $row],
        ])->assertStatus(422);

        $this->postJson(route('analisa.fulfillment.save'), [
            'produk_id' => $bahan->id,
            'gudang' => [['gudang_id' => $this->ffSby->id] + $row],
        ])->assertStatus(422);

        $this->assertSame(0, AnalisaFulfillmentInput::where('produk_id', $bahan->id)->count());
    }

    public function test_user_tanpa_analisa_manage_tidak_bisa_mengubah(): void
    {
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        if ($gudangUser->can('analisa.manage')) {
            $this->markTestSkipped('Role gudang memiliki analisa.manage di seeder.');
        }

        $this->actingAs($gudangUser)
            ->postJson(route('analisa.fulfillment.save'), ['produk_id' => $this->goh->id, 'gudang' => []])
            ->assertForbidden();
        $this->deleteJson(route('analisa.fulfillment.destroy', $this->goh))->assertForbidden();
        $this->assertSame(3, AnalisaFulfillmentInput::where('produk_id', $this->goh->id)->count());
    }
}
