<?php

namespace App\Http\Controllers;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\Authorization\InventoryTransferAuthorizationService;
use App\Services\Authorization\LocationScopeService;
use App\Services\RequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class RequestController extends Controller
{
    public function __construct(
        private readonly RequestService $requestService,
        private readonly LocationScopeService $locationScope,
        private readonly InventoryTransferAuthorizationService $authz
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('request.view');

        $user = auth()->user();
        $isManager = $user->hasAnyRole(['manager', 'admin']);
        $isOperasional = $user->can('request.bahan.create') && ! $isManager;
        $isFulfillment = $user->can('request.produk_jadi.create') && ! $isManager;

        return view('requests.index', compact('isManager', 'isOperasional', 'isFulfillment'));
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('request.view');

        $user = auth()->user();
        $userId = (int) $user->id;
        $isManager = $user->hasAnyRole(['manager', 'admin']);
        $accessibleIds = $this->locationScope->getAccessibleWarehouseIds($user);

        $query = StockRequest::query()
            ->with(['gudangPemohon:id,nama,kode', 'gudangTujuan:id,nama,kode', 'creator:id,name', 'batch:id,no_batch', 'transfers:id,request_id,status'])
            ->when($accessibleIds !== null, function ($q) use ($accessibleIds, $userId) {
                $q->where(function ($sub) use ($accessibleIds, $userId) {
                    $sub->whereIn('gudang_asal_id', $accessibleIds)
                        ->orWhereIn('gudang_tujuan_id', $accessibleIds)
                        ->orWhere('created_by', $userId);
                });
            })
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis', $request->jenis))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('created_at');

        return DataTables::eloquent($query)
            ->orderColumn('tanggal_request', fn ($q, $order) => $q->orderBy('created_at', $order))
            ->editColumn('jenis', fn ($r) => match ($r->jenis) {
                'request_bahan' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200">Bahan Baku (Operasional)</span>',
                'request_produk_jadi' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-sky-50 text-sky-800 border border-sky-200">Produk Jadi (Fulfillment)</span>',
                default => e($r->jenis),
            })
            ->editColumn('status', function ($r) {
                $badge = match ($r->status) {
                    'draft' => 'bg-gray-100 text-gray-700 border-gray-300',
                    'diajukan' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                    'disetujui' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                    'diproses' => 'bg-blue-100 text-blue-800 border-blue-300',
                    'selesai' => 'bg-green-100 text-green-800 border-green-300',
                    'ditolak' => 'bg-rose-100 text-rose-800 border-rose-300',
                    'dibatalkan' => 'bg-red-100 text-red-700 border-red-300',
                    default => 'bg-gray-100 text-gray-700 border-gray-300',
                };
                $label = ucfirst($r->status);
                return "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {$badge}\">{$label}</span>";
            })
            ->editColumn('tanggal_request', fn ($r) => $r->tanggal_request ? $r->tanggal_request->format('d/m/Y') : '-')
            ->addColumn('pemohon', fn ($r) => $r->gudangPemohon?->nama ?? '-')
            ->addColumn('tujuan', fn ($r) => $r->gudangTujuan?->nama ?? '-')
            ->addColumn('batch_no', fn ($r) => $r->batch?->no_batch ?? '-')
            ->addColumn('action', function ($r) use ($userId, $isManager, $user) {
                $detailUrl = route('requests.show', $r);
                $html = '<div class="flex items-center justify-center gap-1.5 whitespace-nowrap">';

                // 1. Aksi Utama: Detail (konsisten di setiap baris dengan gaya neutral outline)
                $html .= '<a href="' . e($detailUrl) . '" class="px-2.5 py-1 rounded-md text-xs font-medium text-foreground bg-card hover:bg-accent border border-border transition-colors shadow-2xs">Detail</a>';

                // 2. Status DRAFT: Pembuat atau Manager dapat Edit & Batalkan
                if ($r->status === 'draft' && ((int) $r->created_by === $userId || $isManager)) {
                    if ($this->authz->canCreateRequestForType($user, $r->jenis)) {
                        $editUrl = route('requests.edit', $r);
                        $html .= '<a href="' . e($editUrl) . '" class="px-2.5 py-1 rounded-md text-xs font-medium text-foreground hover:bg-accent border border-border transition-colors">Edit</a>';
                    }
                    if ($user->can('request.cancel')) {
                        $html .= '<button type="button" class="btn-cancel-req px-2 py-1 rounded-md text-xs font-medium text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors cursor-pointer" data-action="' . e(route('requests.cancel', $r)) . '" data-no="' . e($r->no_request) . '">Batalkan</button>';
                    }
                }

                // 3. Status DIAJUKAN: Pihak berwenang approve dapat Setujui / Tolak
                if ($r->status === 'diajukan') {
                    if ($user->can('request.approve')) {
                        $csrf = csrf_field();
                        $approveUrl = route('requests.approve', $r);
                        $html .= '<form method="POST" action="' . e($approveUrl) . '" class="inline" onsubmit="return confirm(\'Setujui request ' . e($r->no_request) . '?\')">' . $csrf . '<button type="submit" class="px-2.5 py-1 rounded-md text-xs font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/20 transition-colors cursor-pointer">Setujui</button></form>';
                        $html .= '<button type="button" class="btn-reject-req px-2 py-1 rounded-md text-xs font-medium text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors cursor-pointer" data-action="' . e(route('requests.reject', $r)) . '" data-no="' . e($r->no_request) . '">Tolak</button>';
                    } elseif ((int) $r->created_by === $userId && $user->can('request.cancel')) {
                        $html .= '<button type="button" class="btn-cancel-req px-2 py-1 rounded-md text-xs font-medium text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors cursor-pointer" data-action="' . e(route('requests.cancel', $r)) . '" data-no="' . e($r->no_request) . '">Batalkan</button>';
                    }
                }

                // 4. Status DISETUJUI: Alur kerja utama Buat Transfer (sesuai izin kategori transfer) & opsi Revisi
                if ($r->status === 'disetujui') {
                    $hasActiveTransfer = $r->transfers->where('status', '!=', 'dibatalkan')->isNotEmpty();
                    if (! $hasActiveTransfer && $this->authz->canCreateTransferForRequest($user, $r)) {
                        $trfUrl = route('transfers.create', ['request_id' => $r->id]);
                        $html .= '<a href="' . e($trfUrl) . '" class="px-2.5 py-1 rounded-md text-xs font-medium text-primary-foreground bg-primary hover:bg-primary/90 transition-colors shadow-2xs cursor-pointer">Buat Transfer</a>';
                    }

                    if (((int) $r->created_by === $userId || $isManager) && $this->authz->canCreateRequestForType($user, $r->jenis)) {
                        $editUrl = route('requests.edit', $r);
                        $html .= '<a href="' . e($editUrl) . '" class="px-2 py-1 rounded-md text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors" title="Revisi request disetujui (status akan kembali diajukan)">Revisi</a>';
                    }
                }

                $html .= '</div>';
                return $html;
            })
            ->rawColumns(['jenis', 'status', 'action'])
            ->toJson();
    }

    public function create(Request $request)
    {
        $this->authorize('request.create');
        $user = auth()->user();

        $allowedJenis = $this->authz->allowedRequestTypes($user);
        abort_if(empty($allowedJenis), 403, 'Anda tidak memiliki hak akses untuk membuat Permintaan Barang.');

        $allowBatchRequest = in_array('request_bahan', $allowedJenis, true);
        if ($request->filled('batch_id') && ! $allowBatchRequest) {
            abort(403, 'Anda tidak memiliki izin membuat Request Bahan Baku dari Batch Produksi.');
        }

        $gudang = Gudang::active()->orderBy('nama')->get();
        $allowedTipes = $this->authz->allowedRequestProductTipes($user);
        $produk = Produk::active()
            ->when($allowedTipes !== null, fn ($q) => $q->whereIn('tipe', $allowedTipes))
            ->orderBy('nama')
            ->get(['id', 'sku', 'nama', 'satuan', 'tipe']);

        $activeBatches = $allowBatchRequest
            ? BatchProduksi::with(['alokasi.bahan', 'outputs.produk'])
                ->whereIn('status', ['rencana', 'release'])
                ->orderByDesc('id')
                ->get(['id', 'no_batch', 'status', 'gudang_operasional_id'])
            : collect();

        $gudangPusat = Gudang::bahanBakuPusat()->first()
            ?? Gudang::where('tipe', 'bahan_baku')->first()
            ?? Gudang::where('kode', 'GD-PUSAT')->first();
        $defaultBatchTujuanId = $gudangPusat?->id ? (string) $gudangPusat->id : '';

        $productsById = $produk->keyBy('id');
        $batchBahanById = $activeBatches->flatMap->alokasi->pluck('bahan')->filter()->keyBy('id');

        $batchPrefills = [];
        foreach ($activeBatches as $ab) {
            $batchPrefills[(string) $ab->id] = [
                'id' => $ab->id,
                'no_batch' => $ab->no_batch,
                'gudang_pemohon_id' => $ab->gudang_operasional_id ? (string) $ab->gudang_operasional_id : '',
                'gudang_tujuan_id' => $defaultBatchTujuanId,
                'items' => $this->hydratePrefillItems(
                    $this->buildBatchPrefillItems($ab),
                    $productsById,
                    $batchBahanById
                ),
            ];
        }

        $batch = null;
        $defaultGudangPemohonId = null;
        $defaultGudangTujuanId = null;
        $prefilledItems = [];

        if ($request->filled('batch_id')) {
            $batch = $activeBatches->firstWhere('id', (int) $request->batch_id)
                ?? BatchProduksi::with(['alokasi.bahan', 'gudangOperasional', 'outputs.produk'])->find($request->batch_id);
            if ($batch) {
                $defaultGudangPemohonId = $batch->gudang_operasional_id;
                $defaultGudangTujuanId = $gudangPusat?->id;
                $prefilledItems = $this->buildBatchPrefillItems($batch);
            }
        }

        return view('requests.create', compact(
            'gudang',
            'produk',
            'batch',
            'allowedJenis',
            'defaultGudangPemohonId',
            'defaultGudangTujuanId',
            'prefilledItems',
            'activeBatches',
            'batchPrefills'
        ));
    }

    /**
     * Susun item bahan baku yang perlu diminta untuk satu batch produksi.
     *
     * @return array<int, array{produk_id: int, qty_diminta: float, keterangan: string}>
     */
    private function buildBatchPrefillItems(BatchProduksi $batch): array
    {
        $items = [];
        $rekap = $batch->rekapBahan();

        if (! empty($rekap)) {
            foreach ($rekap as $item) {
                $sisa = max(0.0, (float) $item['kebutuhan'] - (float) $item['diterima']);
                $qty = $sisa > 0 ? $sisa : (float) $item['kebutuhan'];
                if ($qty > 0) {
                    $items[] = [
                        'produk_id' => (int) $item['bahan_id'],
                        'qty_diminta' => $qty,
                        'keterangan' => 'Alokasi batch ' . $batch->no_batch,
                    ];
                }
            }
        }

        if (empty($items)) {
            $batch->loadMissing('alokasi');
            foreach ($batch->alokasi as $alokasi) {
                $qty = (float) $alokasi->qty_dialokasikan;
                if ($qty > 0) {
                    $items[] = [
                        'produk_id' => (int) $alokasi->bahan_id,
                        'qty_diminta' => $qty,
                        'keterangan' => 'Alokasi batch ' . $batch->no_batch,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Lengkapi metadata produk untuk kebutuhan komponen dropdown search pada Alpine.
     *
     * @param  array<int, array{produk_id: int, qty_diminta: float, keterangan: string}>  $items
     * @return array<int, array<string, mixed>>
     */
    private function hydratePrefillItems(array $items, $productsById, $fallbackProductsById): array
    {
        return collect($items)->map(function ($it) use ($productsById, $fallbackProductsById) {
            $prodId = (int) ($it['produk_id'] ?? 0);
            $prod = $prodId > 0 ? ($productsById->get($prodId) ?? $fallbackProductsById->get($prodId)) : null;

            return [
                'produk_id' => $prodId,
                'qty_diminta' => $it['qty_diminta'] ?? 1,
                'keterangan' => $it['keterangan'] ?? '',
                'satuan' => $prod?->satuan ?? '-',
                'selectedItem' => $prod ? [
                    'id' => $prod->id,
                    'sku' => $prod->sku,
                    'nama' => $prod->nama,
                    'satuan' => $prod->satuan,
                    'tipe' => $prod->tipe,
                ] : null,
            ];
        })->filter(fn ($it) => $it['selectedItem'] !== null)->values()->all();
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('request.create');

        $validated = $request->validate([
            'jenis' => 'required|in:request_bahan,request_produk_jadi',
            'gudang_pemohon_id' => 'required|exists:gudang,id',
            'gudang_tujuan_id' => 'required|exists:gudang,id|different:gudang_pemohon_id',
            'batch_id' => 'nullable|exists:batch_produksi,id',
            'catatan' => 'nullable|string|max:500',
            'action' => 'nullable|in:save_draft,submit',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produk,id',
            'items.*.qty_diminta' => 'required|numeric|min:0.001',
            'items.*.keterangan' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();
        $this->authorizeRequestCreationPayload($user, $validated['jenis'], $validated['items']);

        try {
            DB::beginTransaction();
            $data = [
                'jenis' => $validated['jenis'],
                'gudang_asal_id' => $validated['gudang_pemohon_id'],
                'gudang_tujuan_id' => $validated['gudang_tujuan_id'],
                'referensi_batch_id' => $validated['jenis'] === 'request_bahan' ? ($validated['batch_id'] ?? null) : null,
                'catatan' => $validated['catatan'] ?? null,
                'items' => $validated['items'],
            ];

            $stockRequest = $this->requestService->create($data, $user);

            if ($request->input('action') === 'submit') {
                $this->requestService->submit($stockRequest, $user);
                $message = "Request {$stockRequest->no_request} berhasil disimpan dan langsung diajukan ke Manajer.";
            } else {
                $message = "Draft Request {$stockRequest->no_request} berhasil dibuat.";
            }
            DB::commit();

            return redirect()->route('requests.show', $stockRequest)->with('success', $message);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(StockRequest $stockRequest)
    {
        $this->authorize('request.view');

        $stockRequest->load([
            'gudangPemohon',
            'gudangTujuan',
            'batch',
            'creator',
            'approver',
            'items.produk.uom',
            'transfers.gudangAsal',
            'transfers.gudangTujuan',
            'transfers.creator',
        ]);

        $user = auth()->user();
        $isManager = $user->hasAnyRole(['manager', 'admin']);
        $canApprove = $user->can('request.approve') && $stockRequest->status === 'diajukan';
        $canSubmit = $user->can('request.submit') && ((int) $stockRequest->created_by === (int) $user->id || $isManager) && $stockRequest->status === 'draft';
        $canCancel = $user->can('request.cancel') && in_array($stockRequest->status, ['draft', 'diajukan', 'disetujui'], true) &&
            (((int) $stockRequest->created_by === (int) $user->id) || $isManager);
        $canCreateTransfer = $stockRequest->status === 'disetujui' &&
            $stockRequest->transfers->where('status', '!=', 'dibatalkan')->isEmpty() &&
            $this->authz->canCreateTransferForRequest($user, $stockRequest);
        $canEdit = $this->authz->canCreateRequestForType($user, $stockRequest->jenis) &&
            in_array($stockRequest->status, ['draft', 'disetujui'], true) &&
            ($isManager || (int) $stockRequest->created_by === (int) $user->id);

        return view('requests.show', compact('stockRequest', 'canApprove', 'canSubmit', 'canCancel', 'canCreateTransfer', 'canEdit', 'isManager'));
    }

    public function edit(StockRequest $stockRequest)
    {
        $this->authorize('request.create');
        $user = auth()->user();
        $isManager = $user->hasAnyRole(['manager', 'admin']);

        abort_unless(
            $this->authz->canCreateRequestForType($user, $stockRequest->jenis),
            403,
            'Anda tidak memiliki izin mengubah jenis Request ini.'
        );

        abort_if(
            ! in_array($stockRequest->status, ['draft', 'disetujui'], true) ||
            ((int) $stockRequest->created_by !== (int) $user->id && ! $isManager),
            403,
            'Request tidak dapat diedit pada status saat ini.'
        );

        $stockRequest->load(['items.produk.uom', 'gudangPemohon', 'gudangTujuan', 'batch']);
        $gudang = Gudang::active()->orderBy('nama')->get();
        $allowedTipes = $this->authz->allowedRequestProductTipes($user);
        $produk = Produk::active()
            ->when($allowedTipes !== null, fn ($q) => $q->whereIn('tipe', $allowedTipes))
            ->orderBy('nama')
            ->get(['id', 'sku', 'nama', 'satuan', 'tipe']);
        $activeBatches = $stockRequest->jenis === 'request_bahan'
            ? BatchProduksi::whereIn('status', ['rencana', 'release'])->orderByDesc('id')->get(['id', 'no_batch', 'status'])
            : collect();

        return view('requests.edit', compact('stockRequest', 'gudang', 'produk', 'activeBatches'));
    }

    public function update(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->authorize('request.create');
        $user = auth()->user();
        $isManager = $user->hasAnyRole(['manager', 'admin']);

        $this->authz->authorizeOrAbort(
            $this->authz->canCreateRequestForType($user, $stockRequest->jenis),
            'Anda tidak memiliki izin mengubah jenis Request ini.'
        );

        abort_if(
            (int) $stockRequest->created_by !== (int) $user->id && ! $isManager,
            403,
            'Anda tidak memiliki izin mengubah Request ini.'
        );

        $validated = $request->validate([
            'gudang_pemohon_id' => 'required|exists:gudang,id',
            'gudang_tujuan_id' => 'required|exists:gudang,id|different:gudang_pemohon_id',
            'batch_id' => 'nullable|exists:batch_produksi,id',
            'catatan' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produk,id',
            'items.*.qty_diminta' => 'required|numeric|min:0.001',
            'items.*.keterangan' => 'nullable|string|max:255',
        ]);

        $this->authorizeRequestCreationPayload($user, $stockRequest->jenis, $validated['items']);

        try {
            $wasApproved = $stockRequest->status === 'disetujui';
            $data = [
                'gudang_asal_id' => $validated['gudang_pemohon_id'],
                'gudang_tujuan_id' => $validated['gudang_tujuan_id'],
                'referensi_batch_id' => $stockRequest->jenis === 'request_bahan' ? ($validated['batch_id'] ?? null) : null,
                'catatan' => $validated['catatan'] ?? null,
                'items' => $validated['items'],
            ];
            $stockRequest = $this->requestService->update($stockRequest, $data, $user);

            $msg = "Request {$stockRequest->no_request} berhasil diperbarui.";
            if ($wasApproved) {
                $msg .= " Karena request sebelumnya sudah disetujui, perubahan ini mereset status kembali ke 'diajukan' dan memerlukan persetujuan ulang Manager (FR-REQ-08).";
            }

            return redirect()->route('requests.show', $stockRequest)->with('success', $msg);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function submit(StockRequest $stockRequest): RedirectResponse
    {
        $this->authorize('request.submit');

        try {
            $this->requestService->submit($stockRequest, auth()->user());
            return redirect()->route('requests.show', $stockRequest)->with('success', "Request {$stockRequest->no_request} berhasil diajukan ke Manajer.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->authorize('request.approve');

        try {
            $this->requestService->approve($stockRequest, auth()->user());
            return redirect()->route('requests.show', $stockRequest)->with('success', "Request {$stockRequest->no_request} berhasil disetujui.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->authorize('request.approve');

        $request->validate([
            'alasan' => 'required|string|max:500',
        ]);

        try {
            $this->requestService->reject($stockRequest, auth()->user(), $request->input('alasan'));
            return redirect()->route('requests.show', $stockRequest)->with('warning', "Request {$stockRequest->no_request} ditolak.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->authorize('request.cancel');

        try {
            $alasan = $request->input('alasan') ?: 'Dibatalkan oleh pengguna';
            $this->requestService->cancel($stockRequest, auth()->user(), $alasan);
            return redirect()->route('requests.show', $stockRequest)->with('info', "Request {$stockRequest->no_request} telah dibatalkan.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Pastikan actor berizin atas jenis Request serta seluruh kategori produk item pada payload.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function authorizeRequestCreationPayload(User $user, string $jenis, array $items): void
    {
        $this->authz->authorizeOrAbort(
            $this->authz->canCreateRequestForType($user, $jenis),
            'Anda tidak memiliki izin untuk membuat atau mengubah jenis Request ini.'
        );

        $produkIds = collect($items)->pluck('produk_id')->filter()->all();
        $categories = $this->authz->resolveProductCategories($produkIds);
        $this->authz->authorizeOrAbort(
            $this->authz->canCreateRequestWithCategories($user, $categories),
            'Anda tidak memiliki izin Request untuk kategori produk yang dipilih.'
        );
    }
}
