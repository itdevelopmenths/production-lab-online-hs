<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\Stok;
use App\Models\User;
use App\Services\AnalisaService;
use App\Services\DemandAduService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemandRequests;
use Tests\TestCase;

/**
 * ADU per gudang dari permintaan barang (4 bulan terakhir):
 * - Gudang Pusat: permintaan bahan baku dari Gudang Produksi.
 * - Gudang Produksi: permintaan produk jadi dari Gudang Fulfillment.
 */
class DemandAduPerGudangTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemandRequests;

    protected Produk $bahan;
    protected Produk $produkJadi;
    protected Gudang $pusat;
    protected Gudang $produksi;
    protected DemandAduService $demand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->bahan = Produk::create([
            'sku' => 'TST-BHN', 'nama' => 'Bahan Uji ADU', 'tipe' => 'bahan', 'satuan' => 'ml',
            'satuan_order_moq' => 100, 'profil_analisa' => 'lokal',
        ]);
        $this->produkJadi = Produk::where('sku', 'GOH-P50')->firstOrFail();
        $this->pusat = Gudang::where('tipe', 'bahan_baku')->firstOrFail();
        $this->produksi = Gudang::where('tipe', 'operasional')->firstOrFail();
        $this->demand = app(DemandAduService::class);
    }

    public function test_window_is_four_months_inclusive_days(): void
    {
        $w = $this->demand->window(now()->setDate(2026, 11, 20));

        $this->assertSame('2026-07-20', $w['start']->toDateString());
        $this->assertSame('2026-11-20', $w['end']->toDateString());
        $this->assertSame(124, $w['days']); // 11 + 31 + 30 + 31 + 20 + 1
    }

    public function test_pusat_adu_comes_from_approved_material_requests_from_produksi(): void
    {
        $this->seedPusatDemand($this->bahan, 50);

        $adu = $this->demand->aduBahanPusat([$this->bahan->id]);

        $this->assertEqualsWithDelta(50.0, $adu[$this->bahan->id]['adu'], 0.001);
    }

    public function test_only_disetujui_diproses_selesai_requests_are_counted(): void
    {
        foreach (['draft', 'diajukan', 'ditolak', 'dibatalkan'] as $status) {
            $this->seedPusatDemand($this->bahan, 10, $status);
        }
        $this->assertArrayNotHasKey($this->bahan->id, $this->demand->aduBahanPusat([$this->bahan->id]));

        foreach (['disetujui', 'diproses', 'selesai'] as $status) {
            $this->seedPusatDemand($this->bahan, 10, $status);
        }
        $this->assertEqualsWithDelta(30.0, $this->demand->aduBahanPusat([$this->bahan->id])[$this->bahan->id]['adu'], 0.001);
    }

    public function test_requests_outside_four_month_window_are_ignored(): void
    {
        $req = $this->seedPusatDemand($this->bahan, 40);
        $req->update(['disetujui_pada' => now()->subMonths(5)]);

        $this->assertArrayNotHasKey($this->bahan->id, $this->demand->aduBahanPusat([$this->bahan->id]));
    }

    public function test_pusat_adu_ignores_finished_goods_requests(): void
    {
        $this->seedProduksiDemand($this->produkJadi, 20);

        $this->assertArrayNotHasKey($this->produkJadi->id, $this->demand->aduBahanPusat([$this->produkJadi->id]));
    }

    public function test_produksi_adu_comes_from_finished_goods_requests_from_fulfillment(): void
    {
        $this->seedProduksiDemand($this->produkJadi, 20);

        $adu = $this->demand->aduProdukJadiProduksi([$this->produkJadi->id]);
        $key = $this->produkJadi->id . '_' . $this->produksi->id;

        $this->assertArrayHasKey($key, $adu);
        $this->assertEqualsWithDelta(20.0, $adu[$key]['adu'], 0.001);
    }

    public function test_produksi_analysis_uses_batch_lead_time_and_flags_order(): void
    {
        $this->seedProduksiDemand($this->produkJadi, 100);

        $user = User::query()->orderBy('id')->firstOrFail();
        // Dua batch selesai: 2 hari dan 4 hari -> avg 3.0, max 4.0 -> safety 1.0
        foreach ([2, 4] as $i => $hari) {
            $id = (string) \Illuminate\Support\Str::uuid();
            \Illuminate\Support\Facades\DB::table('batch_produksi')->insert([
                'id' => $id,
                'no_batch' => 'BATCH-LT-' . $i,
                'produk_id' => $this->produkJadi->id,
                'qty_rencana' => 100,
                'gudang_operasional_id' => $this->produksi->id,
                'status' => 'selesai',
                'tanggal' => now()->toDateString(),
                'created_by' => $user->id,
                'created_at' => now()->subDays($hari),
                'updated_at' => now(),
                'waktu_selesai' => now(),
            ]);
        }

        $rows = collect(app(AnalisaService::class)->produksi())->where('produk_id', $this->produkJadi->id)->values();
        $this->assertCount(1, $rows);
        $row = $rows->first();

        $this->assertEqualsWithDelta(100.0, $row['adu'], 0.001);
        $this->assertEqualsWithDelta(3.0, $row['lead_time_average'], 0.01);
        $this->assertEqualsWithDelta(4.0, $row['lead_time_max'], 0.01);
        $this->assertEqualsWithDelta(1.0, $row['safety_stock_hari'], 0.01);
        $this->assertEquals(400.0, $row['batas_minimum']);      // 100 * (3 + 1)
        $this->assertEquals(1900.0, $row['target_stock']);      // 100 * (3 + 1 + 15)
        $this->assertSame('order', $row['status']);              // stok 0 <= 400
        $this->assertGreaterThan(0, $row['qty_produksi']);
        $this->assertSame(0.0, fmod($row['qty_produksi'], 12.0)); // kelipatan MOQ GOH-P50
    }

    public function test_stok_minimum_is_per_gudang_and_not_leaked_to_produksi_for_materials(): void
    {
        $analisa = app(AnalisaService::class);
        $alk = Produk::where('sku', 'ALK-01')->firstOrFail();
        Stok::firstOrCreate(['produk_id' => $alk->id, 'gudang_id' => $this->produksi->id], ['qty_saat_ini' => 10]);

        $map = $analisa->getBatasMinimumMap();

        $this->assertGreaterThan(0, $analisa->getBatasMinimumForStok($alk->id, $this->pusat->id, $map));
        $this->assertSame(0.0, $analisa->getBatasMinimumForStok($alk->id, $this->produksi->id, $map));
    }

    public function test_stok_minimum_for_finished_goods_at_produksi_uses_produksi_adu(): void
    {
        $this->seedProduksiDemand($this->produkJadi, 100);
        $analisa = app(AnalisaService::class);

        $map = $analisa->getBatasMinimumMap();
        $min = $analisa->getBatasMinimumForStok($this->produkJadi->id, $this->produksi->id, $map);

        // Tanpa batch selesai LT = 0 -> batas minimum 0; target tetap ADU * review period
        $row = collect($analisa->produksi())->firstWhere('produk_id', $this->produkJadi->id);
        $this->assertSame((float) $row['batas_minimum'], $min);
        $this->assertEquals(1500.0, $row['target_stock']);
    }
}
