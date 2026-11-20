<?php

namespace App\Http\Controllers;

use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Stok;
use App\Services\AnalisaService;
use App\Services\Authorization\LocationScopeService;
use App\Services\StokService;
use App\Support\UiTableHelper;
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

        // Prioritaskan Gudang Bahan Baku Pusat (GD-PUSAT), lalu gudang pusat lain yang dapat diakses user
        $defaultPusat = $gudang->first(fn (Gudang $g) => $g->isBahanBaku() && $g->isPusat())
            ?? $gudang->first(fn (Gudang $g) => $g->isPusat())
            ?? $gudang->firstWhere('kode', 'GD-PUSAT');

        $defaultFilterGudangId = $defaultPusat?->id ?? ($isGlobal ? null : $gudang->first()?->id);
        $defaultMutasiGudangId = $defaultPusat?->id ?? $gudang->first()?->id;

        $gudangTypeOptions = collect([
            [
                'value' => 'type:bahan_baku',
                'label' => 'All Gudang Bahan Baku',
                'available' => $gudang->contains(fn (Gudang $g) => $g->isBahanBaku()),
            ],
            [
                'value' => 'type:operasional',
                'label' => 'All Gudang Operasional',
                'available' => $gudang->contains(fn (Gudang $g) => $g->isOperasional()),
            ],
            [
                'value' => 'type:fulfillment',
                'label' => 'All Gudang Fulfillment',
                'available' => $gudang->contains(fn (Gudang $g) => $g->isFulfillment()),
            ],
        ])->filter(fn (array $opt) => $opt['available'])
            ->map(fn (array $opt) => ['value' => $opt['value'], 'label' => $opt['label']])
            ->values();

        return view('stok.index', compact(
            'gudang',
            'gudangTypeOptions',
            'isGlobal',
            'canSeePrice',
            'defaultFilterGudangId',
            'defaultMutasiGudangId',
        ));
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
        $gudangPusatRawId = Gudang::bahanBakuPusat()->value('id')
            ?? Gudang::where('tipe', 'bahan_baku')->value('id')
            ?? Gudang::where('kode', 'GD-PUSAT')->value('id');
        $gudangPusatId = $gudangPusatRawId !== null ? (int) $gudangPusatRawId : null;

        $inboundPo = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.po_id')
            // PO yang sudah disetujui Manager & belum diposting (sama dengan AnalisaService::akanDatangGudangBahanBaku).
            ->whereIn('poi.po_id', PurchaseOrder::query()->disetujuiBelumDiterima()->select('purchase_orders.id'))
            ->select('poi.produk_id', DB::raw('SUM(poi.qty) as total_inbound'))
            ->groupBy('poi.produk_id')
            ->pluck('total_inbound', 'poi.produk_id');

        // 3. Inbound Request & Transfer (New transfers table + legacy request_transfers)
        $inboundRtMap = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('transfers')) {
            $transfersInbound = DB::table('transfer_items as ti')
                ->join('transfers as t', 't.id', '=', 'ti.transfer_id')
                ->where('t.status', 'dikirim')
                ->whereNotNull('t.gudang_tujuan_id')
                ->select('ti.produk_id', 't.gudang_tujuan_id', DB::raw('SUM(ti.qty_dikirim) as total_inbound'))
                ->groupBy('ti.produk_id', 't.gudang_tujuan_id')
                ->get();
            foreach ($transfersInbound as $ti) {
                $inboundRtMap["{$ti->produk_id}_{$ti->gudang_tujuan_id}"] = (float) $ti->total_inbound;
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('request_transfers')) {
            $inboundRt = DB::table('request_transfer_items as rti')
                ->join('request_transfers as rt', 'rt.id', '=', 'rti.request_transfer_id')
                ->whereIn('rt.status', ['diproses', 'dikirim'])
                ->whereNotNull('rt.gudang_tujuan_id')
                ->select('rti.produk_id', 'rt.gudang_tujuan_id', DB::raw('SUM(COALESCE(rti.qty_dikirim, rti.qty_diminta)) as total_inbound'))
                ->groupBy('rti.produk_id', 'rt.gudang_tujuan_id')
                ->get();
            foreach ($inboundRt as $r) {
                $key = "{$r->produk_id}_{$r->gudang_tujuan_id}";
                $inboundRtMap[$key] = ($inboundRtMap[$key] ?? 0) + (float) $r->total_inbound;
            }
        }

        // 4. Rencana Batch Produksi aktif (penambah ketersediaan produk jadi mendatang)
        // Mendukung multi-produk dari batch_produksi_outputs dengan fallback ke batch_produksi (single-product legacy)
        $rencanaBatchMap = [];

        if (\Illuminate\Support\Facades\Schema::hasTable('batch_produksi_outputs')) {
            $multiOutputs = DB::table('batch_produksi_outputs as bpo')
                ->join('batch_produksi as bp', 'bp.id', '=', 'bpo.batch_id')
                ->whereIn('bp.status', ['rencana', 'release'])
                ->select(
                    'bpo.produk_id',
                    DB::raw('COALESCE(bpo.gudang_tujuan_rencana_id, bp.gudang_tujuan_rencana_id, bp.gudang_operasional_id) as gudang_id'),
                    DB::raw('SUM(bpo.qty_rencana) as total_rencana')
                )
                ->groupBy('bpo.produk_id', DB::raw('COALESCE(bpo.gudang_tujuan_rencana_id, bp.gudang_tujuan_rencana_id, bp.gudang_operasional_id)'))
                ->get();

            foreach ($multiOutputs as $b) {
                if ($b->gudang_id) {
                    $key = "{$b->produk_id}_{$b->gudang_id}";
                    $rencanaBatchMap[$key] = ($rencanaBatchMap[$key] ?? 0) + (float) $b->total_rencana;
                }
            }
        }

        // Fallback untuk batch legacy yang belum memiliki baris output di batch_produksi_outputs
        $legacyBatchesQuery = DB::table('batch_produksi as bp')
            ->whereIn('bp.status', ['rencana', 'release'])
            ->whereNotNull('bp.produk_id');

        if (\Illuminate\Support\Facades\Schema::hasTable('batch_produksi_outputs')) {
            $legacyBatchesQuery->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('batch_produksi_outputs as bpo')
                    ->whereColumn('bpo.batch_id', 'bp.id');
            });
        }

        $legacyBatches = $legacyBatchesQuery
            ->select(
                'bp.produk_id',
                DB::raw('COALESCE(bp.gudang_tujuan_rencana_id, bp.gudang_operasional_id) as gudang_id'),
                DB::raw('SUM(bp.qty_rencana) as total_rencana')
            )
            ->groupBy('bp.produk_id', DB::raw('COALESCE(bp.gudang_tujuan_rencana_id, bp.gudang_operasional_id)'))
            ->get();

        foreach ($legacyBatches as $b) {
            if ($b->gudang_id) {
                $key = "{$b->produk_id}_{$b->gudang_id}";
                $rencanaBatchMap[$key] = ($rencanaBatchMap[$key] ?? 0) + (float) $b->total_rencana;
            }
        }

        // 5. Pre-fetch HPP Map dari StokService (identik dengan modal & dropdown purchasing)
        $hppMap = $this->stok->resolveHppMap();

        // Query stok dengan scoping gudang/kategori
        $query = Stok::query()
            ->select('stok.*')
            ->join('produk', 'produk.id', '=', 'stok.produk_id')
            ->leftJoin('gudang', 'gudang.id', '=', 'stok.gudang_id')
            ->with(['produk:id,sku,nama,satuan,tipe,harga_hpp', 'gudang:id,nama'])
            ->when($allowedGudangIds !== null, function ($q) use ($allowedGudangIds) {
                $q->whereIn('stok.gudang_id', $allowedGudangIds);
            })
            ->when($request->filled('gudang_id'), function ($q) use ($request, $allowedGudangIds) {
                $gudangFilter = (string) $request->input('gudang_id');

                if (str_starts_with($gudangFilter, 'type:')) {
                    $tipes = Gudang::resolveTipesForFilterGroup(substr($gudangFilter, 5));
                    if (empty($tipes)) {
                        $q->whereRaw('1 = 0');
                    } else {
                        $q->whereIn('gudang.tipe', $tipes);
                    }

                    return;
                }

                $gudangId = ctype_digit($gudangFilter) ? (int) $gudangFilter : null;
                if ($gudangId !== null && ($allowedGudangIds === null || in_array($gudangId, $allowedGudangIds, true))) {
                    $q->where('stok.gudang_id', $gudangId);
                } else {
                    $q->whereRaw('1 = 0');
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
            ->filter(function ($q) use ($request) {
                $searchVal = trim((string) $request->input('search.value'));
                if ($searchVal !== '') {
                    $words = array_filter(explode(' ', strtolower($searchVal)));
                    if (! empty($words)) {
                        $q->where(function ($parentQuery) use ($words) {
                            foreach ($words as $word) {
                                $parentQuery->where(function ($sub) use ($word) {
                                    $sub->whereRaw('LOWER(produk.sku) LIKE ?', ["%{$word}%"])
                                        ->orWhereRaw('LOWER(produk.nama) LIKE ?', ["%{$word}%"])
                                        ->orWhereRaw('LOWER(gudang.nama) LIKE ?', ["%{$word}%"])
                                        ->orWhereRaw('LOWER(gudang.kode) LIKE ?', ["%{$word}%"]);
                                });
                            }
                        });
                    }
                }
            })
            ->filterColumn('sku', function ($q, $keyword) {
                $q->whereRaw('LOWER(produk.sku) LIKE ?', ['%' . strtolower(trim($keyword)) . '%']);
            })
            ->filterColumn('nama', function ($q, $keyword) {
                $q->whereRaw('LOWER(produk.nama) LIKE ?', ['%' . strtolower(trim($keyword)) . '%']);
            })
            ->filterColumn('gudang_nama', function ($q, $keyword) {
                $q->whereRaw('LOWER(gudang.nama) LIKE ?', ['%' . strtolower(trim($keyword)) . '%']);
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
            ->addColumn('stok_aktual', function ($s) {
                $fisik = (float) $s->qty_saat_ini;

                return $this->formatQty($fisik) . ' ' . $s->produk?->satuan;
            })
            ->addColumn('stok_berjalan', function ($s) use ($gudangPusatId, $inboundPo, $inboundRtMap, $rencanaBatchMap) {
                $berjalan = $this->resolveStokBerjalan($s, $gudangPusatId, $inboundPo, $inboundRtMap, $rencanaBatchMap);
                $val = $this->formatQty($berjalan) . ' ' . $s->produk?->satuan;
                $toneClass = $berjalan > 0 ? 'text-foreground font-medium' : 'text-muted-foreground';

                return '<span class="' . $toneClass . '">' . e($val) . '</span>';
            })
            ->addColumn('status_stok', function ($s) use ($gudangPusatId, $inboundPo, $inboundRtMap, $rencanaBatchMap, $batasMinMap) {
                $aktual = (float) $s->qty_saat_ini;
                $berjalan = $this->resolveStokBerjalan($s, $gudangPusatId, $inboundPo, $inboundRtMap, $rencanaBatchMap);
                $batasMin = (float) $this->analisa->getBatasMinimumForStok($s->produk_id, $s->gudang_id, $batasMinMap);
                $selisih = ($aktual + $berjalan) - $batasMin;
                $isOrder = $batasMin > 0 && round($selisih, 4) <= 0;

                return $isOrder
                    ? UiTableHelper::statusPill('Perlu Order', 'cancelled')
                    : UiTableHelper::statusPill('Aman', 'completed');
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
                return UiTableHelper::actionGroup([
                    UiTableHelper::actionLink(route('stok.ledger', ['produk' => $s->produk_id, 'gudang_id' => $s->gudang_id]), 'Kartu Stok', 'book', 'default'),
                ]);
            })
            ->rawColumns(['stok_berjalan', 'status_stok', 'action'])
            ->toJson();
    }

    /**
     * Hitung kuantitas stok yang sedang berjalan (PO aktif, transfer in-transit, dan batch produksi berjalan untuk produk jadi).
     */
    private function resolveStokBerjalan(
        Stok $s,
        ?int $gudangPusatId,
        \Illuminate\Support\Collection $inboundPo,
        array $inboundRtMap,
        array $rencanaBatchMap
    ): float {
        $berjalan = 0.0;
        $tipe = $s->produk?->tipe;

        if (in_array($tipe, ['bahan', 'kemas'], true) && $gudangPusatId && (int) $s->gudang_id === $gudangPusatId) {
            $berjalan += (float) ($inboundPo[$s->produk_id] ?? 0);
        }

        $rtKey = "{$s->produk_id}_{$s->gudang_id}";
        if (isset($inboundRtMap[$rtKey])) {
            $berjalan += (float) $inboundRtMap[$rtKey];
        }

        if ($tipe === 'produk_jadi' && isset($rencanaBatchMap[$rtKey])) {
            $berjalan += (float) $rencanaBatchMap[$rtKey];
        }

        return $berjalan;
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

        // Maksimal 2 digit desimal, hilangkan trailing zero
        $valRounded = round($val, 2);
        $formatted = number_format($valRounded, 2, ',', '.');

        return 'Rp ' . rtrim(rtrim($formatted, '0'), ',');
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

        if (! $this->locationScope->canAccessWarehouse(auth()->user(), (int) $data['gudang_id'])) {
            abort(403, 'Anda tidak memiliki wewenang mengakses gudang ini.');
        }

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
        $user = auth()->user();
        $produk = Produk::active()->orderBy('nama')->get(['id', 'sku', 'nama', 'satuan']);
        $gudang = $this->locationScope->getAccessibleGudangs($user);

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

        if (! $this->locationScope->canAccessWarehouse(auth()->user(), (int) $gudangId)) {
            return response()->json(['error' => 'Akses gudang tidak diizinkan.'], 403);
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

        if (! $this->locationScope->canAccessWarehouse(auth()->user(), (int) $data['gudang_id'])) {
            abort(403, 'Anda tidak memiliki wewenang mengakses gudang ini.');
        }

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
            ->editColumn('referensi_tipe', fn ($k) => $this->formatReferenceLink($k->referensi_tipe, $k->referensi_id))
            ->rawColumns(['referensi_tipe'])
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
            })
            ->orderByDesc('kartu_stok.created_at')
            ->orderByDesc('kartu_stok.id');

        return DataTables::eloquent($query)
            ->orderColumn('tanggal', function ($q, $order) {
                $q->orderBy('kartu_stok.created_at', $order)->orderBy('kartu_stok.id', $order);
            })
            ->editColumn('tipe', function ($k) {
                $isMasuk = strtolower($k->tipe) === 'in';
                $arrow = $isMasuk ? 'M12 5v14m0 0l-5-5m5 5l5-5' : 'M12 19V5m0 0l-5 5m5-5l5 5';
                $label = $isMasuk ? 'Masuk' : 'Keluar';

                return '<span class="inline-flex items-center gap-1 text-xs font-medium text-[#475467] dark:text-gray-300">'
                    . '<svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' . $arrow . '"/></svg>'
                    . $label . '</span>';
            })
            ->addColumn('item', function ($k) {
                $sku = e($k->produk?->sku ?? '—');
                $nama = e($k->produk?->nama ?? '—');

                return '<div class="text-[13px] text-[#101828] dark:text-white">' . $nama . '</div><div class="text-[11px] text-text-muted tabular-nums">' . $sku . '</div>';
            })
            ->addColumn('lokasi_gudang', fn ($k) => '<span class="text-[13px] text-[#475467] dark:text-gray-300">' . e($k->gudang?->nama ?? '—') . '</span>')
            ->addColumn('ref', function ($k) {
                $refHtml = $this->formatReferenceLink($k->referensi_tipe, $k->referensi_id);
                $catatan = $k->catatan ? '<div class="text-[11px] text-text-muted max-w-[260px] leading-snug" title="' . e($k->catatan) . '">' . e($k->catatan) . '</div>' : '';

                return $refHtml . $catatan;
            })
            ->editColumn('qty', function ($k) {
                $isMasuk = strtolower($k->tipe) === 'in';
                $sign = $isMasuk ? '+' : '−';
                $color = $isMasuk ? 'text-[#101828] dark:text-white' : 'text-rose-600 dark:text-rose-400';
                $formatted = $this->formatQty((float) $k->qty);
                $satuan = e($k->produk?->satuan ?? '');

                return '<span class="text-[13px] font-medium tabular-nums ' . $color . '">' . $sign . $formatted . '</span> <span class="text-[11px] text-text-muted">' . $satuan . '</span>';
            })
            ->addColumn('sebelum_sesudah', function ($k) {
                $isMasuk = strtolower($k->tipe) === 'in';
                $qty = (float) $k->qty;
                $saldoSesudah = (float) $k->saldo_setelah;
                $saldoSebelum = $isMasuk ? ($saldoSesudah - $qty) : ($saldoSesudah + $qty);
                $satuan = e($k->produk?->satuan ?? '');

                return '<span class="text-[13px] tabular-nums text-text-muted">' . $this->formatQty($saldoSebelum) . '</span>'
                    . ' <span class="text-text-muted" aria-hidden="true">→</span> '
                    . '<span class="text-[13px] font-medium tabular-nums text-[#101828] dark:text-white">' . $this->formatQty($saldoSesudah) . '</span> <span class="text-[11px] text-text-muted">' . $satuan . '</span>';
            })
            ->editColumn('tanggal', function ($k) {
                $dt = $k->created_at ?? $k->tanggal;

                return '<div class="text-[13px] tabular-nums text-[#101828] dark:text-white">' . $dt->format('d/m/Y') . '</div>'
                    . ($k->created_at ? '<div class="text-[11px] tabular-nums text-text-muted">' . $k->created_at->format('H:i') . '</div>' : '');
            })
            ->addColumn('oleh', function ($k) {
                return '<span class="text-[13px] text-[#475467] dark:text-gray-300">' . e($k->creator?->name ?? 'Sistem') . '</span>';
            })
            ->rawColumns(['tipe', 'item', 'lokasi_gudang', 'ref', 'qty', 'sebelum_sesudah', 'tanggal', 'oleh'])
            ->toJson();
    }

    private function formatReferenceLink(?string $type, string|int|null $id): string
    {
        if (! $type) {
            return '—';
        }
        $label = ucwords(str_replace('_', ' ', $type));
        $idShort = $id ? ' #' . substr((string) $id, 0, 8) : '';

        $url = null;
        if ($id) {
            try {
                if ($type === 'purchase_order' && \Illuminate\Support\Facades\Route::has('purchasing.show')) {
                    $url = route('purchasing.show', $id);
                } elseif (in_array($type, ['transfer', 'request_transfer'], true)) {
                    if ($type === 'transfer' && \Illuminate\Support\Facades\Route::has('transfers.show')) {
                        $url = route('transfers.show', $id);
                    } elseif (\Illuminate\Support\Facades\Route::has('transfers.show') && \Illuminate\Support\Facades\Schema::hasTable('transfers') && DB::table('transfers')->where('id', $id)->exists()) {
                        $url = route('transfers.show', $id);
                    }
                } elseif ($type === 'batch_produksi' && \Illuminate\Support\Facades\Route::has('batches.show')) {
                    $url = route('batches.show', $id);
                }
            } catch (\Throwable) {
                $url = null;
            }
        }

        if ($url) {
            return '<a href="' . e($url) . '" class="text-[#101828] dark:text-white hover:underline text-[13px] inline-flex items-center gap-1">' . e($label) . '<span class="text-text-muted text-[11px] tabular-nums">' . e($idShort) . '</span></a>';
        }

        return '<div class="text-[13px] text-text-active">' . e($label) . '<span class="text-text-muted text-[11px] tabular-nums">' . e($idShort) . '</span></div>';
    }
}
