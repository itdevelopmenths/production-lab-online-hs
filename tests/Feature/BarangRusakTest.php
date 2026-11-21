<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangRusakTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected Gudang $asal;

    protected Gudang $tujuan;

    protected Gudang $lain;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudang = Gudang::orderBy('id')->take(3)->get();
        [$this->asal, $this->tujuan, $this->lain] = [$gudang[0], $gudang[1], $gudang[2]];
        $this->produk = Produk::where('sku', 'ALK-01')->firstOrFail();
    }

    private function transfer(string $no, Gudang $tujuan, float $rusak, string $diterima, string $status = 'selesai'): Transfer
    {
        $t = Transfer::create([
            'no_transaksi' => $no,
            'jenis' => 'pemenuhan_request_bahan',
            'gudang_asal_id' => $this->asal->id,
            'gudang_tujuan_id' => $tujuan->id,
            'status' => $status,
            'waktu_dikirim' => $diterima,
            'waktu_diterima' => $diterima,
            'created_by' => $this->manager->id,
        ]);
        $t->items()->create([
            'produk_id' => $this->produk->id,
            'qty_dikirim' => 100,
            'qty_baik' => 100 - $rusak,
            'qty_rusak' => $rusak,
            'keterangan_rusak' => $rusak > 0 ? "Bocor {$no}" : null,
        ]);

        return $t;
    }

    public function test_barang_rusak_diakumulasi_per_barang_per_gudang_dengan_rincian_transfer(): void
    {
        $t1 = $this->transfer('TRF-RSK-01', $this->tujuan, 3, '2026-11-05 10:00:00');
        $t2 = $this->transfer('TRF-RSK-02', $this->tujuan, 2, '2026-11-10 10:00:00');
        $this->transfer('TRF-RSK-03', $this->lain, 7, '2026-11-12 10:00:00');
        $this->transfer('TRF-RSK-LUAR', $this->tujuan, 9, '2026-10-01 10:00:00'); // di luar periode
        $this->transfer('TRF-RSK-DIKIRIM', $this->tujuan, 4, '2026-11-11 10:00:00', 'dikirim'); // belum selesai

        $response = $this->actingAs($this->manager)->get(route('barang-rusak.index', [
            'gudang_id' => $this->tujuan->id,
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-30',
        ]));

        $response->assertOk()
            ->assertSee('TRF-RSK-01')
            ->assertSee('TRF-RSK-02')
            ->assertSee(route('transfers.show', $t1), false)
            ->assertSee(route('transfers.show', $t2), false)
            ->assertDontSee('TRF-RSK-03')
            ->assertDontSee('TRF-RSK-LUAR')
            ->assertDontSee('TRF-RSK-DIKIRIM');

        $items = $response->viewData('items');
        $this->assertCount(1, $items);
        $this->assertEquals(5.0, $items[0]->total_rusak);
        $this->assertSame(2, $items[0]->jumlah_transfer);
    }

    public function test_tanpa_filter_gudang_menampilkan_semua_lokasi_terpisah(): void
    {
        $this->transfer('TRF-RSK-A', $this->tujuan, 3, '2026-11-05 10:00:00');
        $this->transfer('TRF-RSK-B', $this->lain, 7, '2026-11-06 10:00:00');

        $items = $this->actingAs($this->manager)->get(route('barang-rusak.index', [
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-30',
        ]))->assertOk()->viewData('items');

        $this->assertCount(2, $items);
        $this->assertEquals([7.0, 3.0], $items->pluck('total_rusak')->all());
    }

    public function test_menu_barang_rusak_tampil_di_sidebar(): void
    {
        $this->actingAs($this->manager)->get(route('transfers.index'))
            ->assertOk()
            ->assertSee(route('barang-rusak.index'), false);
    }
}
