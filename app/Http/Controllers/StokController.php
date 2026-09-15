<?php

namespace App\Http\Controllers;

use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Produk;
use App\Models\Stok;
use App\Services\AnalisaService;
use App\Services\Authorization\LocationScopeService;
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
        private readonly LocationScopeService $locationScope,
    ) {
    }

    public function index()
    {
        $this->authorize('stok.view');
        $user = auth()->user();
        $gudang = $this->locationScope->getAccessibleGudangs($user);
        $isGlobal = $this->locationScope->isGlobal($user);
        $canSeePrice = $user->can('purchasing.price.view');

        return view('stok.index', compact('gudang', 'isGlobal', 'canSeePrice'));
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('stok.view');
        $user = auth()->user();
        $canSeePrice = $user->can('purchasing.price.view');
        $allowedGudangIds = $this->locationScope->getAccessibleWarehouseIds($user);

        // 1. Peta Batas Minimum dari modul Analisa Stok
        $batasMinMap = $this->analisa->getBatasMinimumMap();

        // 2. Inbound PO untuk Bahan/Kemas ke Gudang Pusat
        $gudangPusatId = Gudang::bahanBakuPusat()->value('id')
            ?? Gudang::where('tipe', 'bahan_baku')->value('id')
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

        // 5. Pre-fetch HPP Map dari StokService (identik dengan modal & dropdown purchasing)
        $hppMap = $this->stok->resolveHppMap();

        // Query stok dengan alokasi bahan aktif dan scoping gudang/kategori
        $query = Stok::query()
            ->select('stok.*')
            ->join('produk', 'produk.id', '=', 'stok.produk_id')
            ->leftJoin('gudang', 'gudang.id', '=', 'stok.gudang_id')
            ->with(['produk:id,sku,nama,satuan,tipe,harga_hpp', 'gudang:id,nama'])
            ->selectRaw('(SELECT COALESCE(SUM(ab.qty_dialokasikan),0) FROM alokasi_bahan ab JOIN batch_produksi bp ON bp.id = ab.batch_id WHERE ab.bahan_id = stok.produk_id AND ab.status = \'aktif\' AND (bp.gudang_operasional_id = stok.gudang_id OR bp.gudang_operasional_id IS NULL)) as alokasi_aktif')
            ->when($request->filled('gudang_id'), function ($q) use ($request, $allowedGudangIds) {
                if ($allowedGudangIds === null || in_array((int) $request->gudang_id, $allowedGudangIds, true)) {
                    $q->where('stok.gudang_id', $request->gudang_id);
                }
            }, function ($q) use ($allowedGudangIds) {
                if ($allowedGudangIds !== null) {
                    $q->whereIn('stok.gudang_id', $allowedGudangIds);
                }
            })
            ->when($request->filled('kategori'), function ($q) use ($request) {
                if ($request->kategori === 'bahan') {
                    $q->whereIn('produk.tipe', ['bahan', 'kemas']);
                } elseif ($request->kategori === 'produk_jadi') {
                    $q->where('produk.tipe', 'produk_jadi');
                }
            });

        return DataTables::eloquent($query)
            ->filterColumn('sku', function ($q, $keyword) {
                $q->where('produk.sku', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama', function ($q, $keyword) {
                $q->where('produk.nama', 'like', "%{$keyword}%");
            })
            ->filterColumn('gudang_nama', function ($q, $keyword) {
                $q->where('gudang.nama', 'like', "%{$keyword}%");
            })
            ->addColumn('sku', fn ($s) => $s->produk?->sku)
            ->addColumn('nama', fn ($s) => $s->produk?->nama)
            ->addColumn('gudang_nama', fn ($s) => $s->gudang?->nama)
            ->addColumn('batas_minimum', function ($s) use ($batasMinMap) {
                $batasMin = $this->analisa->getBatasMinimumForStok($s->produk_id, $s->gudang_id, $batasMinMap);

                return $batasMin > 0
                    ? $this->formatQty((float) $batasMin) . ' ' . $s->produk?->satuan
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
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-red-100 text-red-700 ml-2">ORDER</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-700 ml-2">AMAN</span>';

                $val = $this->formatQty($kolomStok) . ' ' . $s->produk?->satuan;

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

                // Logika Rencana: Bahan berkurang komitmen alokasi; Produk jadi bertambah rencana batch
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
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-red-100 text-red-700 ml-2">ORDER</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-700 ml-2">AMAN</span>';

                $val = $this->formatQty($kolomRencana) . ' ' . $s->produk?->satuan;

                return '<div class="flex items-center justify-end"><span>' . $val . '</span>' . $badge . '</div>';
            })
            ->addColumn('hpp', function ($s) use ($hppMap, $canSeePrice) {
                if (! $canSeePrice) {
                    return '—';
                }
                $hpp = (float) ($hppMap[$s->produk_id] ?? 0);

                return $this->formatHpp($hpp);
            })
            ->addColumn('nilai_stok', function ($s) use ($hppMap, $canSeePrice) {
                if (! $canSeePrice) {
                    return '—';
                }
                $hpp = (float) ($hppMap[$s->produk_id] ?? 0);
                $nilai = (float) $s->qty_saat_ini * $hpp;

                return $this->formatNilaiStok($nilai);
            })
            ->addColumn('action', function ($s) {
                return '<a href="' . route('stok.ledger', ['produk' => $s->produk_id, 'gudang_id' => $s->gudang_id]) . '" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-primary-700 bg-primary-50 rounded-lg hover:bg-primary-100 transition">Kartu Stok</a>';
            })
            ->rawColumns(['kolom_stok', 'kolom_rencana', 'action'])
            ->toJson();
    }

    private function formatHpp(float $val): string
    {
        if ($val <= 0) {
            return '—';
        }

        // Jika bilangan bulat murni tanpa pecahan
        if (abs($val - round($val)) < 0.00001) {
            return 'Rp ' . number_format(round($val), 0, ',', '.');
        }

        // Cek apakah ada digit pecahan signifikan setelah 2 desimal (hingga 4 desimal)
        $rounded2 = round($val, 2);
        if (abs($val - $rounded2) > 0.00001) {
            $formatted = number_format($val, 4, ',', '.');

            return 'Rp ' . rtrim(rtrim($formatted, '0'), ',');
        }

        // 1 atau 2 digit desimal standar (misal Rp 526,35 atau Rp 1.000,50)
        return 'Rp ' . number_format($val, 2, ',', '.');
    }

    private function formatNilaiStok(float $val): string
    {
        if ($val <= 0) {
            return 'Rp 0';
        }

        if (abs($val - round($val)) < 0.00001) {
            return 'Rp ' . number_format(round($val), 0, ',', '.');
        }

        $rounded2 = round($val, 2);
        if (abs($val - $rounded2) > 0.00001) {
            $formatted = number_format($val, 4, ',', '.');

            return 'Rp ' . rtrim(rtrim($formatted, '0'), ',');
        }

        return 'Rp ' . number_format($val, 2, ',', '.');
    }

    private function formatQty(float $qty): string
    {
        if (floor($qty) == $qty) {
            return number_format($qty, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',');
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

    public function opname(Request $request)
    {
        $this->authorize('stok.opname');
        $produk = Produk::active()->orderBy('nama')->get(['id', 'sku', 'nama', 'satuan']);
        $gudang = Gudang::active()->orderBy('nama')->get(['id', 'kode', 'nama']);

        $selectedProdukId = $request->query('produk_id');
        $selectedGudangId = $request->query('gudang_id');

        return view('stok.opname', compact('produk', 'gudang', 'selectedProdukId', 'selectedGudangId'));
    }

    public function currentStock(Request $request): JsonResponse
    {
        $this->authorize('stok.view');

        $produkId = $request->query('produk_id');
        $gudangId = $request->query('gudang_id');

        if (! $produkId || ! $gudangId) {
            return response()->json([
                'produk_id' => $produkId ? (int) $produkId : null,
                'gudang_id' => $gudangId ? (int) $gudangId : null,
                'qty' => 0,
            ]);
        }

        $stok = Stok::where('produk_id', $produkId)
            ->where('gudang_id', $gudangId)
            ->first();

        return response()->json([
            'produk_id' => (int) $produkId,
            'gudang_id' => (int) $gudangId,
            'qty' => (float) ($stok?->qty_saat_ini ?? 0),
        ]);
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

    public function ledger(Request $request, Produk $produk)
    {
        $this->authorize('stok.ledger.view');
        $user = auth()->user();
        $gudangs = $this->locationScope->getAccessibleGudangs($user);
        $selectedGudangId = $request->query('gudang_id');
        $selectedGudang = $selectedGudangId ? Gudang::find($selectedGudangId) : null;

        return view('stok.ledger', compact('produk', 'gudangs', 'selectedGudangId', 'selectedGudang'));
    }

    public function ledgerData(Request $request, Produk $produk): JsonResponse
    {
        $this->authorize('stok.ledger.view');

        $query = KartuStok::query()->where('produk_id', $produk->id)
            ->select('kartu_stok.*')
            ->with('gudang:id,nama')
            ->when($request->filled('gudang_id'), function ($q) use ($request) {
                $q->where('kartu_stok.gudang_id', $request->gudang_id);
            })
            ->orderByDesc('id');

        return DataTables::eloquent($query)
            ->editColumn('tanggal', fn ($k) => $k->tanggal->format('d/m/Y'))
            ->addColumn('gudang_nama', fn ($k) => $k->gudang?->nama)
            ->editColumn('tipe', fn ($k) => strtoupper($k->tipe))
            ->editColumn('qty', fn ($k) => $this->formatQty((float) $k->qty) . ' ' . $produk->satuan)
            ->editColumn('saldo_setelah', fn ($k) => $this->formatQty((float) $k->saldo_setelah) . ' ' . $produk->satuan)
            ->editColumn('referensi_tipe', fn ($k) => ucwords(str_replace('_', ' ', $k->referensi_tipe)))
            ->toJson();
    }

    public function pergerakanLog(Request $request)
    {
        $this->authorize('stok.view');
        $user = auth()->user();
        $gudang = $this->locationScope->getAccessibleGudangs($user);

        return view('stok.pergerakan-log', compact('gudang'));
    }

    public function pergerakanLogData(Request $request): JsonResponse
    {
        $this->authorize('stok.view');
        $user = auth()->user();
        $allowedGudangIds = $this->locationScope->getAccessibleWarehouseIds($user);

        $query = KartuStok::query()->select('kartu_stok.*')
            ->with([
                'produk:id,sku,nama,satuan,tipe',
                'gudang:id,nama,kode',
                'creator:id,name',
            ])
            ->when($request->filled('gudang_id'), function ($q) use ($request, $allowedGudangIds) {
                if ($allowedGudangIds === null || in_array((int) $request->gudang_id, $allowedGudangIds, true)) {
                    $q->where('kartu_stok.gudang_id', $request->gudang_id);
                }
            }, function ($q) use ($allowedGudangIds) {
                if ($allowedGudangIds !== null) {
                    $q->whereIn('kartu_stok.gudang_id', $allowedGudangIds);
                }
            })
            ->when($request->filled('tipe'), function ($q) use ($request) {
                $q->where('kartu_stok.tipe', strtolower($request->tipe));
            })
            ->when($request->filled('tanggal_mulai'), function ($q) use ($request) {
                $q->whereDate('kartu_stok.tanggal', '>=', $request->tanggal_mulai);
            })
            ->when($request->filled('tanggal_selesai'), function ($q) use ($request) {
                $q->whereDate('kartu_stok.tanggal', '<=', $request->tanggal_selesai);
            });

        return DataTables::eloquent($query)
            ->editColumn('tipe', function ($k) {
                $isMasuk = strtolower($k->tipe) === 'in';
                return $isMasuk
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">MASUK (IN)</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">KELUAR (OUT)</span>';
            })
            ->addColumn('item', function ($k) {
                $sku = e($k->produk?->sku ?? '—');
                $nama = e($k->produk?->nama ?? '—');
                $satuan = e($k->produk?->satuan ?? '');
                return '<div class="font-medium text-gray-900">' . $nama . '</div><div class="text-[10px] font-mono text-gray-500">' . $sku . ' · ' . $satuan . '</div>';
            })
            ->addColumn('lokasi_gudang', fn ($k) => e($k->gudang?->nama ?? '—'))
            ->addColumn('ref', function ($k) {
                $tipe = ucwords(str_replace('_', ' ', $k->referensi_tipe));
                $refId = $k->referensi_id ? ' #' . substr($k->referensi_id, 0, 8) : '';
                $catatan = $k->catatan ? '<div class="text-[10px] text-gray-400 truncate max-w-[200px]" title="' . e($k->catatan) . '">' . e($k->catatan) . '</div>' : '';
                return '<div class="font-semibold text-gray-700">' . $tipe . '<span class="font-mono text-gray-500 text-[11px]">' . $refId . '</span></div>' . $catatan;
            })
            ->editColumn('qty', function ($k) {
                $isMasuk = strtolower($k->tipe) === 'in';
                $sign = $isMasuk ? '+' : '-';
                $color = $isMasuk ? 'text-emerald-700 font-bold' : 'text-rose-700 font-bold';
                $formatted = $this->formatQty((float) $k->qty);
                $satuan = e($k->produk?->satuan ?? '');
                return '<span class="font-mono ' . $color . '">' . $sign . ' ' . $formatted . ' ' . $satuan . '</span>';
            })
            ->addColumn('sebelum_sesudah', function ($k) {
                $isMasuk = strtolower($k->tipe) === 'in';
                $qty = (float) $k->qty;
                $saldoSesudah = (float) $k->saldo_setelah;
                $saldoSebelum = $isMasuk ? ($saldoSesudah - $qty) : ($saldoSesudah + $qty);
                $satuan = e($k->produk?->satuan ?? '');
                return '<span class="font-mono text-gray-600">' . $this->formatQty($saldoSebelum) . '</span> <span class="text-gray-400">➔</span> <span class="font-mono font-bold text-gray-900">' . $this->formatQty($saldoSesudah) . ' ' . $satuan . '</span>';
            })
            ->editColumn('tanggal', fn ($k) => '<span class="font-mono text-xs text-gray-700">' . ($k->created_at ? $k->created_at->format('d/m/Y H:i') : $k->tanggal->format('d/m/Y')) . '</span>')
            ->addColumn('oleh', function ($k) {
                return '<span class="text-gray-800 font-medium text-xs">' . e($k->creator?->name ?? 'Sistem') . '</span>';
            })
            ->rawColumns(['tipe', 'item', 'ref', 'qty', 'sebelum_sesudah', 'tanggal', 'oleh'])
            ->toJson();
    }
}
