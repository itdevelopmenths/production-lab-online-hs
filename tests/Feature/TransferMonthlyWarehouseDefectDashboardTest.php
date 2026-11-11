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

        // Verifikasi Struktur Monthly Matrix (Untuk Blade Index Table PRD Bab 4)
        $this->assertArrayHasKey('monthly_matrix', $analytics);
        $this->assertCount(12, $analytics['monthly_matrix']);
        $maretMatrix = collect($analytics['monthly_matrix'])->firstWhere('month', 3);
        $this->assertNotNull($maretMatrix);
        $this->assertEquals('Maret', $maretMatrix['month_name']);
        $this->assertEquals(3, $maretMatrix['transfers_count']);
        $this->assertEquals(180, (float) $maretMatrix['qty_dikirim']);
        $this->assertEquals(160, (float) $maretMatrix['qty_baik']);
        $this->assertEquals(20, (float) $maretMatrix['qty_rusak']);
        $this->assertEquals(11.11, (float) $maretMatrix['defect_rate_percent']);
        $this->assertNotEmpty($maretMatrix['warehouses']);

        // 6. Verifikasi rendering view transfers.index (HTTP GET)
        $response = $this->actingAs($this->operasional)->get(route('transfers.index', ['year' => $year, 'month' => $month]));
        $response->assertOk();
        $response->assertSee('Dashboard Barang Rusak');
        $response->assertSee('11.11%');
        $response->assertSee('Maret');
    }

    public function test_completed_transfer_without_waktu_diterima_falls_back_to_timestamps_in_analytics(): void
    {
        $year = (int) date('Y');
        $month = (int) date('n');

        $trf = Transfer::create([
            'no_transaksi' => 'TRF-NULL-WAKTU-01',
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'status' => 'selesai',
            'waktu_diterima' => null,
            'diterima_oleh' => $this->operasional->id,
            'created_by' => $this->gudangUser->id,
        ]);
        $trf->items()->create([
            'produk_id' => $this->bahan1->id,
            'qty_dikirim' => 25,
            'qty_diterima' => 20,
            'qty_baik' => 20,
            'qty_rusak' => 5,
            'keterangan_rusak' => 'Pecah tanpa timestamp terima',
        ]);

        $analytics = $this->trfService->getMonthlyWarehouseDefectAnalytics($year, $month);
        $this->assertEquals(5.0, (float) $analytics['summary']['total_qty_rusak']);

        $monthRow = collect($analytics['monthly_matrix'])->firstWhere('month', $month);
        $this->assertNotNull($monthRow);
        $this->assertEquals(5.0, (float) $monthRow['qty_rusak']);
    }

    public function test_combined_analytics_aggregates_transfer_purchasing_and_batch_defects(): void
    {
        $year = (int) date('Y');
        $month = (int) date('n');
        $supplier = \App\Models\Supplier::firstOrFail();
        $produkJadi = Produk::produkJadi()->active()->firstOrFail();

        // 1. Transfer (5 rusak dari 50)
        $trf = Transfer::create([
            'no_transaksi' => 'TRF-SVC-COMB',
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'status' => 'selesai',
            'waktu_diterima' => now(),
            'diterima_oleh' => $this->operasional->id,
            'created_by' => $this->gudangUser->id,
        ]);
        $trf->items()->create([
            'produk_id' => $this->bahan1->id,
            'qty_dikirim' => 50,
            'qty_diterima' => 45,
            'qty_baik' => 45,
            'qty_rusak' => 5,
            'keterangan_rusak' => 'Transfer pecah',
        ]);

        // 2. Purchasing (10 rusak dari 100, termasuk saat kondisi PO dibiarkan 'baik' namun ada selisih negatif)
        $po = \App\Models\PurchaseOrder::create([
            'no_po' => 'PO-SVC-COMB',
            'supplier_id' => $supplier->id,
            'gudang_id' => $this->gudangPusat->id,
            'tanggal' => now()->toDateString(),
            'status' => 'selesai',
            'created_by' => $this->gudangUser->id,
        ]);
        $poItem = $po->items()->create([
            'produk_id' => $this->bahan2->id,
            'qty' => 100,
            'harga_total' => 100000,
        ]);
        $bardat = \App\Models\BarangDatang::create([
            'po_id' => $po->id,
            'tanggal_terima' => now()->toDateString(),
            'waktu_tiba' => now(),
            'kondisi' => 'baik',
            'status_posting' => 'diposting',
            'created_by' => $this->gudangUser->id,
        ]);
        $bardat->items()->create([
            'po_item_id' => $poItem->id,
            'qty_fisik' => 90,
            'qty_diterima' => 90,
            'selisih' => -10,
            'keterangan_selisih' => 'Rusak unboxing PO',
        ]);

        // 3. Batch Produksi multi-output (3 + 2 = 5 rusak dari 50)
        $batch = \App\Models\BatchProduksi::create([
            'no_batch' => 'B-SVC-COMB',
            'produk_id' => $produkJadi->id,
            'qty_rencana' => 50,
            'qty_baik' => 45,
            'qty_rusak' => 5,
            'gudang_operasional_id' => $this->gudangOps->id,
            'status' => 'selesai',
            'tanggal' => now()->toDateString(),
            'created_by' => $this->operasional->id,
        ]);
        $batch->outputs()->create([
            'produk_id' => $produkJadi->id,
            'qty_rencana' => 50,
            'qty_baik' => 45,
            'qty_rusak' => 5,
        ]);

        // Combined analytics: 5 + 10 + 5 = 20 rusak dari 200 volume -> 10.00%
        $combined = $this->trfService->getCombinedMonthlyWarehouseDefectAnalytics($year, $month);
        $this->assertEquals(20.0, (float) $combined['summary']['total_qty_rusak']);
        $this->assertEquals(200.0, (float) $combined['summary']['total_qty_dikirim']);
        $this->assertEquals(10.0, (float) $combined['summary']['defect_rate_percent']);
        $this->assertEquals(3, $combined['summary']['total_transfers']);
        $this->assertEquals(5.0, (float) $combined['summary']['by_source']['transfer']);
        $this->assertEquals(10.0, (float) $combined['summary']['by_source']['purchasing']);
        $this->assertEquals(5.0, (float) $combined['summary']['by_source']['batch']);

        // Transfer-only analytics (untuk Report & transfers.index) tetap menghitung Transfer saja (5 rusak)
        $transferOnly = $this->trfService->getMonthlyWarehouseDefectAnalytics($year, $month);
        $this->assertEquals(5.0, (float) $transferOnly['summary']['total_qty_rusak']);
        $this->assertEquals(1, $transferOnly['summary']['total_transfers']);
    }
}

