<?php

namespace App\Http\Controllers;

use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Produk;
use App\Models\Stok;
use App\Services\AnalisaService;
use App\Services\StokService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class StokController extends Controller
{
    public function __construct(
        private readonly StokService $stok,
        private readonly AnalisaService $analisa,
    ) {
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

        // 1. Peta Batas Minimum dari modul Analisa Stok
        $batasMinMap = $this->analisa->getBatasMinimumMap();

        // 2. Inbound PO untuk Bahan/Kemas ke Gudang Pusat
        $gudangPusatId = Gudang::where('tipe', 'bahan_baku')->value('id')
            ?? Gudang::where('kode', 'GD-PUSAT')->value('id');

        $inboundPo = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.po_id')
            ->whereIn('po.status', ['diajukan', 'dikirim_ke_gudang'])
            ->select('poi.produk_id', DB::raw('SUM(poi.qty) as total_inbound'))
            ->groupBy('poi.produk_id')
            ->pluck('total_inbound', 'poi.produk_id');

        // 3. Inbound Request & Transfer
        $inboundRt = DB::table('request_transfer_items as rti')
            ->join('request_transfers as rt', 'rt.id', '=', 'rti.request_transfer_id')
            ->whereIn('rt.status', ['diproses', 'dikirim'])
            ->whereNotNull('rt.gudang_tujuan_id')
            ->select('rti.produk_id', 'rt.gudang_tujuan_id', DB::raw('SUM(COALESCE(rti.qty_dikirim, rti.qty_diminta)) as total_inbound'))
            ->groupBy('rti.produk_id', 'rt.gudang_tujuan_id')
            ->get()
            ->keyBy(fn ($r) => "{$r->produk_id}_{$r->gudang_tujuan_id}");

        // 4. Rencana Batch Produksi aktif (penambah ketersediaan produk jadi mendatang)
        $rencanaBatch = DB::table('batch_produksi')
            ->whereIn('status', ['rencana', 'release'])
            ->select('produk_id', 'gudang_tujuan_rencana_id', 'gudang_operasional_id', DB::raw('SUM(qty_rencana) as total_rencana'))
            ->groupBy('produk_id', 'gudang_tujuan_rencana_id', 'gudang_operasional_id')
            ->get();

        $rencanaBatchMap = [];
        foreach ($rencanaBatch as $b) {
            $whId = $b->gudang_tujuan_rencana_id ?? $b->gudang_operasional_id;
            if ($whId) {
                $key = "{$b->produk_id}_{$whId}";
                $rencanaBatchMap[$key] = ($rencanaBatchMap[$key] ?? 0) + (float) $b->total_rencana;
            }
        }

        // Query stok dengan alokasi bahan aktif
        $query = Stok::query()
            ->select('stok.*')
            ->join('produk', 'produk.id', '=', 'stok.produk_id')
            ->with(['produk:id,sku,nama,satuan,tipe', 'gudang:id,nama'])
            ->selectRaw('(SELECT COALESCE(SUM(ab.qty_dialokasikan),0) FROM alokasi_bahan ab JOIN batch_produksi bp ON bp.id = ab.batch_id WHERE ab.bahan_id = stok.produk_id AND ab.status = \'aktif\' AND (bp.gudang_operasional_id = stok.gudang_id OR bp.gudang_operasional_id IS NULL)) as alokasi_aktif');

        return DataTables::eloquent($query)
            ->addColumn('sku', fn ($s) => $s->produk?->sku)
            ->addColumn('nama', fn ($s) => $s->produk?->nama)
            ->addColumn('gudang_nama', fn ($s) => $s->gudang?->nama)
            ->addColumn('batas_minimum', function ($s) use ($batasMinMap) {
                $batasMin = $this->analisa->getBatasMinimumForStok($s->produk_id, $s->gudang_id, $batasMinMap);

                return $batasMin > 0
                    ? rtrim(rtrim(number_format($batasMin, 2, ',', '.'), '0'), ',') . ' ' . $s->produk?->satuan
                    : '-';
            })
            ->addColumn('kolom_stok', function ($s) use ($gudangPusatId, $inboundPo, $inboundRt, $batasMinMap) {
                $fisik = (float) $s->qty_saat_ini;
                $inbound = 0.0;
                if (in_array($s->produk?->tipe, ['bahan', 'kemas'], true) && $gudangPusatId && (int) $s->gudang_id === (int) $gudangPusatId) {
                    $inbound += (float) ($inboundPo[$s->produk_id] ?? 0);
                }
                $rtKey = "{$s->produk_id}_{$s->gudang_id}";
                if (isset($inboundRt[$rtKey])) {
                    $inbound += (float) $inboundRt[$rtKey]->total_inbound;
                }

                $kolomStok = $fisik + $inbound;
                $batasMin = $this->analisa->getBatasMinimumForStok($s->produk_id, $s->gudang_id, $batasMinMap);
                $isOrder = $batasMin > 0 && $kolomStok <= $batasMin;

                $badge = $isOrder
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700 ml-2">ORDER</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-700 ml-2">AMAN</span>';

                $val = rtrim(rtrim(number_format($kolomStok, 2, ',', '.'), '0'), ',') . ' ' . $s->produk?->satuan;

                return '<div class="flex items-center justify-end"><span>' . $val . '</span>' . $badge . '</div>';
            })
            ->addColumn('kolom_rencana', function ($s) use ($gudangPusatId, $inboundPo, $inboundRt, $rencanaBatchMap, $batasMinMap) {
                $fisik = (float) $s->qty_saat_ini;
                $inbound = 0.0;
                if (in_array($s->produk?->tipe, ['bahan', 'kemas'], true) && $gudangPusatId && (int) $s->gudang_id === (int) $gudangPusatId) {
                    $inbound += (float) ($inboundPo[$s->produk_id] ?? 0);
                }
                $rtKey = "{$s->produk_id}_{$s->gudang_id}";
                if (isset($inboundRt[$rtKey])) {
                    $inbound += (float) $inboundRt[$rtKey]->total_inbound;
                }

                $kolomStok = $fisik + $inbound;

                if (in_array($s->produk?->tipe, ['bahan', 'kemas'], true)) {
                    $kolomRencana = $kolomStok - (float) $s->alokasi_aktif;
                } elseif ($s->produk?->tipe === 'produk_jadi') {
                    $rencanaBatch = (float) ($rencanaBatchMap[$rtKey] ?? 0);
                    $kolomRencana = $kolomStok + $rencanaBatch;
                } else {
                    $kolomRencana = $kolomStok;
                }

                $batasMin = $this->analisa->getBatasMinimumForStok($s->produk_id, $s->gudang_id, $batasMinMap);
                $isOrder = $batasMin > 0 && $kolomRencana <= $batasMin;

                $badge = $isOrder
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700 ml-2">ORDER</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-700 ml-2">AMAN</span>';

                $val = rtrim(rtrim(number_format($kolomRencana, 2, ',', '.'), '0'), ',') . ' ' . $s->produk?->satuan;

                return '<div class="flex items-center justify-end"><span>' . $val . '</span>' . $badge . '</div>';
            })
            ->addColumn('action', function ($s) {
                return '<a href="' . route('stok.ledger', $s->produk_id) . '" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-primary-700 bg-primary-50 rounded-lg hover:bg-primary-100 transition">Kartu Stok</a>';
            })
            ->rawColumns(['kolom_stok', 'kolom_rencana', 'action'])
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
