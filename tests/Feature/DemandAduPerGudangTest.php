<?php

namespace Tests\Feature;

use App\Models\AnalisaFulfillmentInput;
use App\Models\Bom;
use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Produk;
use App\Models\Stok;
use App\Models\User;
use App\Services\AnalisaService;
use App\Services\DemandAduService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemandRequests;
use Tests\TestCase;

/**
 * ADU berantai (4 bulan terakhir):
 * - Produk jadi: ADU penjualan dari Analisa Fulfillment (Σ terjual/30 seluruh gudang fulfillment).
 * - Gudang Produksi: memakai ADU produk jadi tsb untuk rekomendasi produksi.
 * - Gudang Pusat (bahan): pemakaian bahan di batch produksi, fallback proyeksi BOM.
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

    public function test_pusat_adu_comes_from_material_consumed_in_production_batches(): void
    {
        $this->seedPusatDemand($this->bahan, 50);

        $adu = $this->demand->aduBahanPusat([$this->bahan->id]);

        $this->assertEqualsWithDelta(50.0, $adu[$this->bahan->id]['adu'], 0.001);
        $this->assertSame(DemandAduService::SUMBER_PEMAKAIAN, $adu[$this->bahan->id]['sumber']);
    }

    public function test_only_batch_issue_out_entries_are_counted(): void
    {
        $days = $this->demand->window()['days'];
        foreach ([['in', 'batch_produksi'], ['out', 'mutasi_manual'], ['out', 'request_transfer']] as [$tipe, $ref]) {
            KartuStok::create([
                'tanggal' => now()->toDateString(), 'produk_id' => $this->bahan->id, 'gudang_id' => $this->produksi->id,
                'tipe' => $tipe, 'qty' => 10 * $days, 'saldo_setelah' => 0, 'referensi_tipe' => $ref,
            ]);
        }
        $this->assertArrayNotHasKey($this->bahan->id, $this->demand->aduBahanPusat([$this->bahan->id]));

        $this->seedPusatDemand($this->bahan, 30);
        $this->assertEqualsWithDelta(30.0, $this->demand->aduBahanPusat([$this->bahan->id])[$this->bahan->id]['adu'], 0.001);
    }

    public function test_consumption_outside_four_month_window_is_ignored(): void
    {
        $this->seedPusatDemand($this->bahan, 40, now()->subMonths(5));

        $this->assertArrayNotHasKey($this->bahan->id, $this->demand->aduBahanPusat([$this->bahan->id]));
    }

    public function test_material_without_history_uses_bom_projection_from_fulfillment_adu(): void
    {
        // GOH-P50: ADU penjualan 100/hari; BOM bahan uji 2 ml per unit -> proyeksi 200 ml/hari.
        $this->seedProduksiDemand($this->produkJadi, 100);
        Bom::create(['produk_jadi_id' => $this->produkJadi->id, 'bahan_id' => $this->bahan->id, 'qty_per_unit' => 2]);

        $adu = $this->demand->aduBahanPusat([$this->bahan->id]);
        $this->assertEqualsWithDelta(200.0, $adu[$this->bahan->id]['adu'], 0.001);
        $this->assertSame(DemandAduService::SUMBER_BOM, $adu[$this->bahan->id]['sumber']);

        // Begitu ada pemakaian riil, pemakaian yang dipakai.
        $this->seedPusatDemand($this->bahan, 75);
        $this->assertEqualsWithDelta(75.0, $this->demand->aduBahanPusat([$this->bahan->id])[$this->bahan->id]['adu'], 0.001);
    }

    public function test_produk_jadi_adu_is_sum_of_fulfillment_sales_adu(): void
    {
        // Seeder: GOH terjual 60000/90000/45000 per bulan -> ADU 2000 + 3000 + 1500.
        $adu = $this->demand->aduProdukJadi([$this->produkJadi->id]);

        $this->assertEqualsWithDelta(6500.0, $adu[$this->produkJadi->id]['adu'], 0.001);
        $this->assertSame(3, $adu[$this->produkJadi->id]['jumlah_gudang']);
    }

    public function test_produksi_analysis_uses_fulfillment_adu_batch_lead_time_and_target(): void
    {
        $this->seedProduksiDemand($this->produkJadi, 100);

        $user = User::query()->orderBy('id')->firstOrFail();
        // Dua batch selesai: 2 hari dan 4 hari -> avg 3.0, max 4.0 -> safety 1.0
        foreach ([2, 4] as $i => $hari) {
            \Illuminate\Support\Facades\DB::table('batch_produksi')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
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
        // Stok produk jadi di Gudang Produksi: di atas Batas Min tapi di bawah Target -> tetap ORDER (ikut Excel).
        Stok::updateOrCreate(['produk_id' => $this->produkJadi->id, 'gudang_id' => $this->produksi->id], ['qty_saat_ini' => 500]);

        $rows = collect(app(AnalisaService::class)->produksi())->where('produk_id', $this->produkJadi->id)->values();
        $this->assertCount(1, $rows);
        $row = $rows->first();

        $this->assertSame($this->produksi->id, $row['gudang_id']);
        $this->assertEqualsWithDelta(100.0, $row['adu'], 0.001);
        $this->assertEqualsWithDelta(3.0, $row['lead_time_average'], 0.01);
        $this->assertEqualsWithDelta(4.0, $row['lead_time_max'], 0.01);
        $this->assertEqualsWithDelta(1.0, $row['safety_stock_hari'], 0.01);
        $this->assertEquals(400.0, $row['batas_minimum']);      // 100 * (3 + 1)
        $this->assertEquals(1900.0, $row['target_stock']);      // 100 * (3 + 1 + 15)
        $this->assertSame('order', $row['status']);              // 500 <= 1900
        $this->assertEquals(-1400.0, $row['selisih']);
        $this->assertEquals(1404.0, $row['qty_produksi']);       // 1400 dibulatkan ke kelipatan MOQ 12
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
        $this->assertSame(1, AnalisaFulfillmentInput::where('produk_id', $this->produkJadi->id)->count());
    }
}
