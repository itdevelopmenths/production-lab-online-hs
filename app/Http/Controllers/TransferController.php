<?php

namespace App\Http\Controllers;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\StockRequest;
use App\Models\Transfer;
use App\Services\Authorization\LocationScopeService;
use App\Services\StokService;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly LocationScopeService $locationScope,
        private readonly StokService $stokService
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('rt.view');

        $year = (int) $request->input('year', date('Y'));
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $gudangId = $request->filled('gudang_id') ? (int) $request->input('gudang_id') : null;

        $warehouses = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'kode', 'tipe']);
        $defectAnalytics = $this->transferService->getMonthlyWarehouseDefectAnalytics($year, $month, $gudangId);

        $user = auth()->user();
        $isManager = $user->hasAnyRole(['manager', 'admin']);
        $isGudang = $user->hasRole('gudang');

        return view('transfers.index', compact('defectAnalytics', 'year', 'month', 'gudangId', 'warehouses', 'isManager', 'isGudang'));
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('rt.view');

        $user = auth()->user();
        $userId = (int) $user->id;
        $accessibleIds = $this->locationScope->getAccessibleWarehouseIds($user);

        $query = Transfer::query()
            ->with([
                'gudangAsal:id,nama,kode',
                'gudangTujuan:id,nama,kode',
                'stockRequest:id,no_request',
                'creator:id,name',
                'items.produk:id,nama,sku,satuan',
            ])
            ->when($accessibleIds !== null, function ($q) use ($accessibleIds) {
                $q->where(function ($sub) use ($accessibleIds) {
                    $sub->whereIn('gudang_asal_id', $accessibleIds)
                        ->orWhereIn('gudang_tujuan_id', $accessibleIds);
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis', $request->jenis))
            ->latest('id');

        return DataTables::eloquent($query)
            ->editColumn('status', function ($t) {
                $badge = match ($t->status) {
                    'draft' => 'bg-gray-100 text-gray-700 border-gray-300',
                    'dikirim' => 'bg-amber-100 text-amber-800 border-amber-300',
                    'selesai' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                    'dibatalkan' => 'bg-rose-100 text-rose-800 border-rose-300',
                    default => 'bg-gray-100 text-gray-700 border-gray-300',
                };
                $label = match ($t->status) {
                    'draft' => 'Draft',
                    'dikirim' => 'In Transit',
                    'selesai' => 'Selesai',
                    'dibatalkan' => 'Dibatalkan',
                    default => ucfirst($t->status),
                };
                return "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {$badge}\">{$label}</span>";
            })
            ->editColumn('jenis', fn ($t) => match ($t->jenis) {
                'request_bahan' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200">Bahan Baku</span>',
                'request_produk_jadi' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-sky-50 text-sky-800 border border-sky-200">Produk Jadi</span>',
                'distribusi' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-800 border border-indigo-200">Distribusi</span>',
                default => e($t->jenis),
            })
            ->editColumn('tanggal_transfer', fn ($t) => $t->tanggal_transfer ? $t->tanggal_transfer->format('d/m/Y') : '-')
            ->addColumn('asal', fn ($t) => $t->gudangAsal?->nama ?? '-')
            ->addColumn('tujuan', fn ($t) => $t->gudangTujuan?->nama ?? '-')
            ->addColumn('request_no', fn ($t) => $t->stockRequest ? '<a href="' . route('requests.show', $t->stockRequest) . '" class="text-blue-600 hover:underline font-mono text-xs">' . e($t->stockRequest->no_request) . '</a>' : '-')
            ->addColumn('qty_dikirim', fn ($t) => number_format($t->items->sum('qty_dikirim'), 2))
            ->addColumn('qty_rusak', function ($t) {
                $rusak = (float) $t->items->sum('qty_rusak');
                if ($rusak > 0) {
                    return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800 border border-rose-300">' . number_format($rusak, 2) . '</span>';
                }
                return '<span class="text-slate-400">0</span>';
            })
            ->addColumn('action', function ($t) {
                $detailUrl = route('transfers.show', $t);
                $html = '<div class="flex items-center justify-center gap-1.5">';
                $html .= '<a href="' . e($detailUrl) . '" class="inline-flex items-center px-2 py-1 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded border border-slate-300">Detail</a>';

                if (in_array($t->status, ['dikirim', 'selesai'], true)) {
                    $sjUrl = route('transfers.surat-jalan', $t);
                    $html .= '<a href="' . e($sjUrl) . '" target="_blank" class="inline-flex items-center px-2 py-1 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded border border-indigo-300" title="Cetak Surat Jalan">Surat Jalan</a>';
                }

                $html .= '</div>';
                return $html;
            })
            ->rawColumns(['status', 'jenis', 'request_no', 'qty_rusak', 'action'])
            ->toJson();
    }

    public function defectAnalytics(Request $request): JsonResponse
    {
        $this->authorize('rt.view');

        $year = (int) $request->input('year', date('Y'));
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $gudangId = $request->filled('gudang_id') ? (int) $request->input('gudang_id') : null;

        $analytics = $this->transferService->getMonthlyWarehouseDefectAnalytics($year, $month, $gudangId);

        return response()->json($analytics);
    }

    public function create(Request $request)
    {
        $this->authorize('rt.create');

        $warehouses = Gudang::active()->orderBy('nama')->get();
        $products = Produk::active()->orderBy('nama')->get(['id', 'sku', 'nama', 'satuan', 'tipe']);

        $stockRequest = null;
        $defaultGudangAsalId = null;
        $defaultGudangTujuanId = null;
        $defaultJenis = 'distribusi';
        $prefilledItems = [];

        if ($request->filled('request_id')) {
            $stockRequest = StockRequest::with(['items.produk.uom', 'gudangPemohon', 'gudangTujuan'])->findOrFail($request->request_id);

            abort_if($stockRequest->status !== 'disetujui', 422, 'Hanya Request dengan status "disetujui" yang dapat dibuatkan Transfer.');

            // In active request, asal is supplier warehouse (gudang_tujuan_id in request)
            // and tujuan is applicant warehouse (gudang_pemohon_id in request)
            $defaultGudangAsalId = $stockRequest->gudang_tujuan_id;
            $defaultGudangTujuanId = $stockRequest->gudang_pemohon_id;
            $defaultJenis = $stockRequest->jenis;

            foreach ($stockRequest->items as $item) {
                $stokTersedia = $this->stokService->saldo($item->produk_id, $defaultGudangAsalId);
                $prefilledItems[] = [
                    'produk_id' => $item->produk_id,
                    'sku' => $item->produk?->sku,
                    'nama' => $item->produk?->nama,
                    'satuan' => $item->produk?->satuan ?? 'Pcs',
                    'qty_diminta' => (float) $item->qty_diminta,
                    'qty_dikirim' => (float) $item->qty_diminta,
                    'stok_tersedia' => (float) $stokTersedia,
                    'keterangan' => $item->keterangan,
                ];
            }
        }

        return view('transfers.create', compact('stockRequest', 'warehouses', 'products', 'defaultGudangAsalId', 'defaultGudangTujuanId', 'defaultJenis', 'prefilledItems'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('rt.create');

        $validated = $request->validate([
            'request_id' => 'nullable|exists:requests,id',
            'jenis' => 'nullable|string',
            'gudang_asal_id' => 'required|exists:gudang,id',
            'gudang_tujuan_id' => 'required|exists:gudang,id|different:gudang_asal_id',
            'catatan' => 'nullable|string|max:500',
            'action' => 'nullable|in:save_draft,ship',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produk,id',
            'items.*.qty_dikirim' => 'required|numeric|min:0.001',
            'items.*.keterangan' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $jenis = 'antar_fulfillment';
            if (!empty($validated['request_id'])) {
                $stockReq = StockRequest::findOrFail($validated['request_id']);
                $jenis = $stockReq->jenis === 'request_bahan' ? 'pemenuhan_request_bahan' : 'kirim_produk_jadi';
            } elseif (!empty($validated['jenis']) && in_array($validated['jenis'], Transfer::JENIS, true)) {
                $jenis = $validated['jenis'];
            }

            $transferData = [
                'jenis' => $jenis,
                'gudang_asal_id' => $validated['gudang_asal_id'],
                'gudang_tujuan_id' => $validated['gudang_tujuan_id'],
                'request_id' => $validated['request_id'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
                'items' => $validated['items'],
            ];

            $transfer = $this->transferService->create($transferData, auth()->user());

            if ($request->input('action') === 'ship') {
                $this->transferService->ship($transfer, auth()->user());
                $message = "Transfer {$transfer->no_transfer} berhasil dibuat dan langsung dikirim (In Transit). Stok gudang asal telah dipotong.";
            } else {
                $message = "Draft Transfer {$transfer->no_transfer} berhasil dibuat.";
            }
            DB::commit();

            return redirect()->route('transfers.show', $transfer)->with('success', $message);
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Transfer $transfer)
    {
        $this->authorize('rt.view');

        $transfer->load([
            'gudangAsal',
            'gudangTujuan',
            'stockRequest',
            'creator',
            'sender',
            'receiver',
            'items.produk.uom',
        ]);

        $user = auth()->user();
        $isManager = $user->hasAnyRole(['manager', 'admin']);
        $isGudang = $user->hasRole('gudang');
        $isFulfillment = $user->hasRole('fulfillment');
        $isOperasional = $user->hasRole('operasional');

        // Can ship if draft and user has permission
        $canShip = $transfer->status === 'draft' && ($isGudang || $isManager || (int) $transfer->created_by === (int) $user->id);

        // Can receive if status is dikirim
        $canReceive = $transfer->status === 'dikirim' && ($isManager || $isGudang || $isFulfillment || $isOperasional);

        // Can cancel draft
        $canCancelDraft = $transfer->status === 'draft' && ($isManager || (int) $transfer->created_by === (int) $user->id);

        return view('transfers.show', compact('transfer', 'canShip', 'canReceive', 'canCancelDraft', 'isManager'));
    }

    public function ship(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('rt.ship');

        try {
            $this->transferService->ship($transfer, auth()->user());
            return redirect()->route('transfers.show', $transfer)->with('success', "Transfer {$transfer->no_transfer} berhasil dikirim (In Transit). Stok gudang asal telah dikurangi.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function receive(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('rt.receive');

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:transfer_items,id',
            'items.*.qty_baik' => 'required|numeric|min:0',
            'items.*.qty_rusak' => 'required|numeric|min:0',
            'items.*.keterangan_rusak' => 'nullable|string|max:500',
        ]);

        // Validate defect reason and anti-fraud invariant: qty_dikirim = qty_baik + qty_rusak
        $transferItems = $transfer->items->keyBy('id');
        foreach ($validated['items'] as $itemData) {
            $tItem = $transferItems->get($itemData['id']);
            if (! $tItem) {
                return back()->with('error', "Item transfer tidak valid.");
            }

            $baik = (float) $itemData['qty_baik'];
            $rusak = (float) $itemData['qty_rusak'];
            $dikirim = (float) $tItem->qty_dikirim;

            if (abs(($baik + $rusak) - $dikirim) > 0.0001) {
                return back()->withInput()->with('error', "Total Qty Baik ({$baik}) + Qty Rusak ({$rusak}) harus sama persis dengan Qty Dikirim ({$dikirim}) untuk item {$tItem->produk?->nama}.");
            }

            if ($rusak > 0 && empty(trim($itemData['keterangan_rusak'] ?? ''))) {
                return back()->withInput()->with('error', "Keterangan rusak wajib diisi jika terdapat barang rusak pada item {$tItem->produk?->nama}.");
            }
        }

        try {
            $this->transferService->receive($transfer, ['items' => $validated['items']], auth()->user());
            return redirect()->route('transfers.show', $transfer)->with('success', "Transfer {$transfer->no_transfer} berhasil diterima. Stok baik telah masuk ke gudang tujuan dan barang rusak telah dicatat dalam metrik defect bulanan.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('rt.cancel');

        try {
            $alasan = $request->input('alasan') ?: 'Dibatalkan oleh pembuat/manager';
            $this->transferService->cancelDraft($transfer, auth()->user(), $alasan);
            return redirect()->route('transfers.show', $transfer)->with('info', "Draft Transfer {$transfer->no_transfer} berhasil dibatalkan.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function suratJalan(Transfer $transfer)
    {
        $this->authorize('rt.view');

        $transfer->load([
            'gudangAsal',
            'gudangTujuan',
            'stockRequest',
            'creator',
            'sender',
            'receiver',
            'items.produk.uom',
        ]);

        return view('transfers.surat-jalan', compact('transfer'));
    }
}
