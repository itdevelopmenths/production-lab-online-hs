<?php

namespace App\Http\Controllers;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\PoLeadTime;
use App\Models\PoStage;
use App\Models\PurchaseOrder;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\SupplierLtStageSummary;
use App\Models\SupplierLtSummary;
use App\Models\SupplierProdukLtSummary;
use App\Services\Purchasing\SupplierLeadTimeService;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $this->authorize('report.view');

        return view('reports.index');
    }

    public function production(Request $request)
    {
        $this->authorize('report.view');

        $query = BatchProduksi::with(['produk:id,nama', 'outputs.produk:id,nama'])
            ->where('status', 'selesai');

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal', '>=', $request->tanggal_mulai);
        }
        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('tanggal', '<=', $request->tanggal_selesai);
        }

        $batches = $query->latest('tanggal')->limit(500)->get();

        return view('reports.production', compact('batches'));
    }

    public function material()
    {
        $this->authorize('report.view');
        // Pemakaian bahan = mutasi keluar bereferensi batch_produksi.
        $rows = KartuStok::with(['produk:id,sku,nama,satuan'])
            ->where('referensi_tipe', 'batch_produksi')->where('tipe', 'out')
            ->select('produk_id', DB::raw('SUM(qty) as total_keluar'))
            ->groupBy('produk_id')->get();

        return view('reports.material', compact('rows'));
    }

    public function defect(Request $request, TransferService $transferService)
    {
        $this->authorize('report.view');

        $year = (int) ($request->input('tahun', now()->year));
        $month = $request->filled('bulan') ? (int) $request->input('bulan') : null;
        $gudangId = $request->filled('gudang_id') ? (int) $request->input('gudang_id') : null;

        $batchQuery = BatchProduksi::with(['produk:id,nama', 'outputs.produk:id,nama'])
            ->where('status', 'selesai')
            ->where('qty_rusak', '>', 0);

        if ($year) {
            $batchQuery->whereYear('tanggal', $year);
        }
        if ($month !== null) {
            $batchQuery->whereMonth('tanggal', $month);
        }
        if ($gudangId) {
            $batchQuery->where(function ($q) use ($gudangId) {
                $q->where('gudang_operasional_id', $gudangId)
                    ->orWhere('gudang_tujuan_rencana_id', $gudangId);
            });
        }

        $batches = $batchQuery->latest('tanggal')->limit(500)->get();

        // Rekapitulasi Kerusakan Pengiriman Transfer Bulanan per Gudang
        $transferAnalytics = $transferService->getMonthlyDefectAnalytics($year, $month, $gudangId);

        $gudangs = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'kode']);

        return view('reports.defect', compact('batches', 'transferAnalytics', 'year', 'month', 'gudangId', 'gudangs'));
    }

    public function lowStock()
    {
        $this->authorize('report.view');
        // Variance dari stock opname sebagai indikator selisih pemakaian.
        $opname = StockOpname::with(['bahan:id,nama', 'batch:id,no_batch'])
            ->latest()->limit(500)->get();

        return view('reports.low-stock', compact('opname'));
    }

    public function purchasing(Request $request)
    {
        $this->authorize('report.view');
        $user = auth()->user();
        $canSeePrice = $user?->can('purchasing.price.view') ?? false;

        $pos = PurchaseOrder::with(['supplier:id,nama', 'items', 'payments'])
            ->latest('tanggal')->limit(500)->get();

        return view('reports.purchasing', compact('pos', 'canSeePrice'));
    }

    public function leadTime(Request $request)
    {
        $this->authorize('report.view');

        $supplierId = $request->filled('supplier_id') ? (int) $request->input('supplier_id') : null;
        $kategori = $request->filled('kategori') ? (string) $request->input('kategori') : null;

        $suppliers = Supplier::where('is_active', true)->orderBy('nama')->get(['id', 'nama', 'kategori']);

        // Level-1 Supplier Summary
        $queryL1 = SupplierLtSummary::with('supplier:id,nama,kategori');
        if ($supplierId) {
            $queryL1->where('supplier_id', $supplierId);
        }
        if ($kategori) {
            $queryL1->whereHas('supplier', fn ($q) => $q->where('kategori', $kategori));
        }

        $supplierSummaries = $queryL1->get()->map(function ($s) {
            $avgMenit = $s->jumlah_transaksi > 0 ? ($s->total_lt_menit / $s->jumlah_transaksi) : 0;

            return [
                'supplier_id' => $s->supplier_id,
                'supplier_nama' => $s->supplier?->nama ?? '—',
                'kategori' => $s->supplier?->kategori ?? '—',
                'jumlah_transaksi' => (int) $s->jumlah_transaksi,
                'total_lt_menit' => (int) $s->total_lt_menit,
                'average_lt_hari' => (float) $s->average_lt_hari,
                'max_lt_hari' => (float) $s->max_lt_hari,
                'average_lt_jam' => SupplierLeadTimeService::minutesToHours($avgMenit),
                'max_lt_jam' => (float) round($s->max_lt_hari * 24, 2, PHP_ROUND_HALF_UP),
            ];
        });

        // 9-Stage Breakdown Level-1
        $queryStages = SupplierLtStageSummary::with('supplier:id,nama,kategori');
        if ($supplierId) {
            $queryStages->where('supplier_id', $supplierId);
        }
        if ($kategori) {
            $queryStages->whereHas('supplier', fn ($q) => $q->where('kategori', $kategori));
        }
        $stageSummaries = $queryStages->get()->groupBy('supplier_id');

        // Level-2 Supplier-Produk
        $queryL2 = SupplierProdukLtSummary::with(['supplier:id,nama,kategori', 'produk:id,sku,nama,satuan']);
        if ($supplierId) {
            $queryL2->where('supplier_id', $supplierId);
        }
        if ($kategori) {
            $queryL2->whereHas('supplier', fn ($q) => $q->where('kategori', $kategori));
        }

        $produkSummaries = $queryL2->get()->map(function ($sp) {
            $avgMenit = $sp->jumlah_transaksi > 0 ? ($sp->total_lt_menit / $sp->jumlah_transaksi) : 0;

            return [
                'supplier_id' => $sp->supplier_id,
                'supplier_nama' => $sp->supplier?->nama ?? '—',
                'produk_id' => $sp->produk_id,
                'sku' => $sp->produk?->sku ?? '—',
                'nama_produk' => $sp->produk?->nama ?? '—',
                'satuan' => $sp->produk?->satuan ?? '—',
                'jumlah_transaksi' => (int) $sp->jumlah_transaksi,
                'average_lt_hari' => (float) $sp->average_lt_hari,
                'max_lt_hari' => (float) $sp->max_lt_hari,
                'average_lt_jam' => SupplierLeadTimeService::minutesToHours($avgMenit),
            ];
        });

        // Histori Transaksi PoLeadTime
        $queryHistory = PoLeadTime::with([
            'purchaseOrder:id,no_po,no_invoice,status',
            'supplier:id,nama,kategori',
            'validator:id,name',
        ]);
        if ($supplierId) {
            $queryHistory->where('supplier_id', $supplierId);
        }
        if ($kategori) {
            $queryHistory->whereHas('supplier', fn ($q) => $q->where('kategori', $kategori));
        }

        $transaksiList = $queryHistory->latest('waktu_selesai')->latest('id')->limit(200)->get();

        // KPI Ringkasan
        $totalTrx = $supplierSummaries->sum('jumlah_transaksi');
        $totalMenit = $supplierSummaries->sum('total_lt_menit');
        $avgLtHari = $totalTrx > 0 ? SupplierLeadTimeService::minutesToDays($totalMenit / $totalTrx) : 0.0;
        $avgLtJam = $totalTrx > 0 ? SupplierLeadTimeService::minutesToHours($totalMenit / $totalTrx) : 0.0;

        $fastestSupplier = $supplierSummaries->where('jumlah_transaksi', '>', 0)->sortBy('average_lt_hari')->first();
        $slowestSupplier = $supplierSummaries->where('jumlah_transaksi', '>', 0)->sortByDesc('average_lt_hari')->first();

        $totalHistory = $transaksiList->count();
        $totalValid = $transaksiList->where('is_valid', true)->count();
        $totalInvalid = $transaksiList->where('is_valid', false)->count();

        return view('reports.lead-time', compact(
            'suppliers',
            'supplierSummaries',
            'stageSummaries',
            'produkSummaries',
            'transaksiList',
            'supplierId',
            'kategori',
            'avgLtHari',
            'avgLtJam',
            'fastestSupplier',
            'slowestSupplier',
            'totalHistory',
            'totalValid',
            'totalInvalid'
        ));
    }

    public function fulfillment()
    {
        $this->authorize('report.view');
        // Pergerakan stok produk jadi antar gudang fulfillment.
        $rows = KartuStok::with(['produk:id,nama', 'gudang:id,nama'])
            ->whereHas('gudang', fn ($q) => $q->fulfillment())
            ->latest('id')->limit(500)->get();

        return view('reports.fulfillment', compact('rows'));
    }
}
