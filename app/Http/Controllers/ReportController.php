<?php

namespace App\Http\Controllers;

use App\Models\BatchProduksi;
use App\Models\KartuStok;
use App\Models\PurchaseOrder;
use App\Models\StockOpname;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $this->authorize('report.view');

        return view('reports.index');
    }

    public function production()
    {
        $this->authorize('report.view');
        $batches = BatchProduksi::with('produk:id,nama')
            ->where('status', 'selesai')->latest('tanggal')->limit(500)->get();

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

    public function defect()
    {
        $this->authorize('report.view');
        $batches = BatchProduksi::with('produk:id,nama')
            ->where('status', 'selesai')->where('qty_rusak', '>', 0)
            ->latest('tanggal')->limit(500)->get();

        return view('reports.defect', compact('batches'));
    }

    public function lowStock()
    {
        $this->authorize('report.view');
        // Variance dari stock opname sebagai indikator selisih pemakaian.
        $opname = StockOpname::with(['bahan:id,nama', 'batch:id,no_batch'])
            ->latest()->limit(500)->get();

        return view('reports.low-stock', compact('opname'));
    }

    public function purchasing()
    {
        $this->authorize('report.view');
        $pos = PurchaseOrder::with(['supplier:id,nama'])
            ->withSum('items as total_nilai', 'harga_total')
            ->withSum('payments as total_dibayar', 'nominal')
            ->latest('tanggal')->limit(500)->get();

        return view('reports.purchasing', compact('pos'));
    }

    public function fulfillment()
    {
        $this->authorize('report.view');
        // Pergerakan stok produk jadi antar gudang fulfillment.
        $rows = KartuStok::with(['produk:id,nama', 'gudang:id,nama'])
            ->whereHas('gudang', fn ($q) => $q->whereIn('tipe', ['fulfillment_pusat', 'fulfillment_cabang']))
            ->latest('id')->limit(500)->get();

        return view('reports.fulfillment', compact('rows'));
    }
}
