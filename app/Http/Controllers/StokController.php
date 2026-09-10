<?php

namespace App\Http\Controllers;

use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Produk;
use App\Models\Stok;
use App\Services\StokService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class StokController extends Controller
{
    public function __construct(private readonly StokService $stok)
    {
    }

    public function index()
    {
        $this->authorize('stok.view');
        $produk = Produk::active()->orderBy('nama')->get(['id', 'sku', 'nama', 'satuan']);
        $gudang = Gudang::active()->orderBy('nama')->get(['id', 'nama']);

        return view('stok.index', compact('produk', 'gudang'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('stok.view');

        // Kolom Stok = saldo fisik. Kolom Rencana = saldo − alokasi bahan aktif.
        $query = Stok::query()
            ->select('stok.*')
            ->join('produk', 'produk.id', '=', 'stok.produk_id')
            ->with(['produk:id,sku,nama,satuan,tipe', 'gudang:id,nama'])
            ->selectRaw('(SELECT COALESCE(SUM(qty_dialokasikan),0) FROM alokasi_bahan ab JOIN batch_produksi bp ON bp.id = ab.batch_id WHERE ab.bahan_id = stok.produk_id AND ab.status = \'aktif\') as alokasi_aktif');

        return DataTables::eloquent($query)
            ->addColumn('sku', fn ($s) => $s->produk?->sku)
            ->addColumn('nama', fn ($s) => $s->produk?->nama)
            ->addColumn('gudang_nama', fn ($s) => $s->gudang?->nama)
            ->addColumn('kolom_stok', fn ($s) => (float) $s->qty_saat_ini)
            ->addColumn('kolom_rencana', fn ($s) => (float) $s->qty_saat_ini - (float) $s->alokasi_aktif)
            ->rawColumns([])
            ->toJson();
    }

    public function mutasi(Request $request)
    {
        $this->authorize('stok.mutasi');

        $data = $request->validate([
            'produk_id' => ['required', 'exists:produk,id'],
            'gudang_id' => ['required', 'exists:gudang,id'],
            'tipe' => ['required', Rule::in(['in', 'out'])],
            'qty' => ['required', 'numeric', 'gt:0'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            if ($data['tipe'] === 'in') {
                $this->stok->masuk($data['produk_id'], $data['gudang_id'], (float) $data['qty'], 'mutasi_manual', null, $data['catatan'] ?? null);
            } else {
                $this->stok->keluar($data['produk_id'], $data['gudang_id'], (float) $data['qty'], 'mutasi_manual', null, $data['catatan'] ?? null);
            }
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Mutasi stok dicatat.');
    }

    public function opname()
    {
        $this->authorize('stok.opname');
        $produk = Produk::active()->orderBy('nama')->get(['id', 'sku', 'nama']);
        $gudang = Gudang::active()->orderBy('nama')->get(['id', 'nama']);

        return view('stok.opname', compact('produk', 'gudang'));
    }

    public function storeOpname(Request $request)
    {
        $this->authorize('stok.opname');

        $data = $request->validate([
            'produk_id' => ['required', 'exists:produk,id'],
            'gudang_id' => ['required', 'exists:gudang,id'],
            'qty_fisik' => ['required', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $this->stok->setAbsolut(
            $data['produk_id'],
            $data['gudang_id'],
            (float) $data['qty_fisik'],
            'Opname: ' . ($data['catatan'] ?? '-'),
        );

        return back()->with('success', 'Opname stok dicatat.');
    }

    public function ledger(Produk $produk)
    {
        $this->authorize('stok.ledger.view');

        return view('stok.ledger', compact('produk'));
    }

    public function ledgerData(Produk $produk): JsonResponse
    {
        $this->authorize('stok.ledger.view');

        $query = KartuStok::query()->where('produk_id', $produk->id)
            ->select('kartu_stok.*')->with('gudang:id,nama')->orderByDesc('id');

        return DataTables::eloquent($query)
            ->editColumn('tanggal', fn ($k) => $k->tanggal->format('d/m/Y'))
            ->addColumn('gudang_nama', fn ($k) => $k->gudang?->nama)
            ->editColumn('tipe', fn ($k) => strtoupper($k->tipe))
            ->editColumn('referensi_tipe', fn ($k) => ucwords(str_replace('_', ' ', $k->referensi_tipe)))
            ->toJson();
    }
}
