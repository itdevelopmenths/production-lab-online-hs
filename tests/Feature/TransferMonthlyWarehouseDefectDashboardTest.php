<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\Transfer;
use App\Models\User;
use App\Services\StokService;
use App\Services\TransferService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferMonthlyWarehouseDefectDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $operasional;
    protected User $gudangUser;
    protected Gudang $gudangPusat;
    protected Gudang $gudangOps;
    protected Gudang $gudangFfSolo;
    protected Produk $bahan1;
    protected Produk $bahan2;
    protected TransferService $trfService;
    protected StokService $stokService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $this->gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $this->gudangOps = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $this->gudangFfSolo = Gudang::where('kode', 'FF-SOLO')->firstOrFail();

        $produks = Produk::bahan()->active()->take(2)->get();
        $this->bahan1 = $produks[0];
        $this->bahan2 = $produks[1];

        $this->trfService = app(TransferService::class);
        $this->stokService = app(StokService::class);
    }

    /**
     * TC-42: Dashboard & Rekapitulasi Bulanan Barang Rusak per Gudang (User Override).
     */
    public function test_tc42_monthly_warehouse_defect_analytics_calculation(): void
    {
        $year = (int) date('Y');
        $month = 3; // Bulan Maret

        // 1. Buat Transfer 1 menuju Gudang Ops pada bulan Maret (Rusak 5)
        $trf1 = Transfer::create([
            'no_transaksi' => 'TRF-TEST-MAR-01',
            'jenis' => 'retur_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'status' => 'selesai',
            'waktu_dikirim' => Carbon::create($year, $month, 5, 10, 0),
            'waktu_diterima' => Carbon::create($year, $month, 6, 14, 0),
            'diterima_oleh' => $this->gudangUser->id,
            'created_by' => $this->operasional->id,
        ]);
        $trf1->items()->create([
            'produk_id' => $this->bahan1->id,
            'qty_dikirim' => 50,
            'qty_diterima' => 45,
            'qty_baik' => 45,
            'qty_rusak' => 5,
            'keterangan_rusak' => 'Botol pecah di perjalanan',
        ]);

        // 2. Buat Transfer 2 menuju Gudang Ops pada bulan Maret (Rusak 3 pada bahan2)
        $trf2 = Transfer::create([
            'no_transaksi' => 'TRF-TEST-MAR-02',
            'jenis' => 'retur_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'status' => 'selesai',
            'waktu_dikirim' => Carbon::create($year, $month, 10, 10, 0),
            'waktu_diterima' => Carbon::create($year, $month, 11, 15, 0),
            'diterima_oleh' => $this->gudangUser->id,
            'created_by' => $this->operasional->id,
        ]);
        $trf2->items()->create([
            'produk_id' => $this->bahan2->id,
            'qty_dikirim' => 30,
            'qty_diterima' => 27,
            'qty_baik' => 27,
            'qty_rusak' => 3,
            'keterangan_rusak' => 'Bocor',
        ]);

        // 3. Buat Transfer 3 menuju Gudang FF Solo pada bulan Maret (Rusak 12)
        $trf3 = Transfer::create([
            'no_transaksi' => 'TRF-TEST-MAR-03',
            'jenis' => 'retur_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangFfSolo->id,
            'status' => 'selesai',
            'waktu_dikirim' => Carbon::create($year, $month, 15, 9, 0),
            'waktu_diterima' => Carbon::create($year, $month, 16, 12, 0),
            'diterima_oleh' => $this->gudangUser->id,
            'created_by' => $this->operasional->id,
        ]);
        $trf3->items()->create([
            'produk_id' => $this->bahan1->id,
            'qty_dikirim' => 100,
            'qty_diterima' => 88,
            'qty_baik' => 88,
            'qty_rusak' => 12,
            'keterangan_rusak' => 'Kardus penyok kehujanan',
        ]);

        // 4. Buat Transfer 4 menuju Gudang Ops pada bulan April (Bulan berikutnya, Rusak 8)
        $trf4 = Transfer::create([
            'no_transaksi' => 'TRF-TEST-APR-01',
            'jenis' => 'retur_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'status' => 'selesai',
            'waktu_dikirim' => Carbon::create($year, 4, 2, 9, 0),
            'waktu_diterima' => Carbon::create($year, 4, 3, 11, 0),
            'diterima_oleh' => $this->gudangUser->id,
            'created_by' => $this->operasional->id,
        ]);
        $trf4->items()->create([
            'produk_id' => $this->bahan1->id,
            'qty_dikirim' => 40,
            'qty_diterima' => 32,
            'qty_baik' => 32,
            'qty_rusak' => 8,
            'keterangan_rusak' => 'Rusak transit',
        ]);

        // 5. Eksekusi kalkulasi analytics untuk bulan Maret
        $analytics = $this->trfService->getMonthlyWarehouseDefectAnalytics($year, $month);

        $this->assertEquals($year, $analytics['year']);
        $this->assertEquals($month, $analytics['month']);

        // Verifikasi KPI Summary Bulan Maret:
        // Total Rusak: 5 (TRF1) + 3 (TRF2) + 12 (TRF3) = 20
        $this->assertEquals(20, (float) $analytics['summary']['total_rusak_bulan_ini']);
        // Total Dikirim: 50 + 30 + 100 = 180
        $this->assertEquals(180, (float) $analytics['summary']['total_dikirim_bulan_ini']);
        // Defect rate: (20 / 180) * 100 = 11.11%
        $this->assertEquals(11.11, (float) $analytics['summary']['defect_rate_percent']);
        $this->assertEquals(3, $analytics['summary']['total_transfer_count']);
        $this->assertEquals(3, $analytics['summary']['transfer_terdampak_count']);
        // Gudang paling terdampak di bulan Maret: FF Solo dengan 12 unit rusak
        $this->assertEquals($this->gudangFfSolo->nama, $analytics['summary']['gudang_tertinggi_nama']);
        $this->assertEquals(12, (float) $analytics['summary']['gudang_tertinggi_qty']);

        // Verifikasi Matriks Bulanan per Gudang:
        $matrix = collect($analytics['monthly_by_warehouse']);

        // Gudang Ops: Maret = 8 (5 + 3), April = 8, Total Tahunan = 16
        $opsRow = $matrix->firstWhere('gudang_id', $this->gudangOps->id);
        $this->assertNotNull($opsRow);
        $this->assertEquals(8, (float) $opsRow['months'][3]); // Maret
        $this->assertEquals(8, (float) $opsRow['months'][4]); // April
        $this->assertEquals(16, (float) $opsRow['total_tahunan']);

        // Gudang FF Solo: Maret = 12, Total Tahunan = 12
        $soloRow = $matrix->firstWhere('gudang_id', $this->gudangFfSolo->id);
        $this->assertNotNull($soloRow);
        $this->assertEquals(12, (float) $soloRow['months'][3]); // Maret
        $this->assertEquals(12, (float) $soloRow['total_tahunan']);
    }
}

