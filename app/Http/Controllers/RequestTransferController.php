<?php

namespace App\Http\Controllers;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\RequestTransfer;
use App\Models\Stok;
use App\Models\User;
use App\Services\Authorization\LocationScopeService;
use App\Services\StokService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class RequestTransferController extends Controller
{
    public function __construct(
        private readonly StokService $stok,
        private readonly LocationScopeService $locationScope
    ) {
    }

    public function index()
    {
        $this->authorize('rt.view');

        return view('request-transfer.index');
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('rt.view');

        $user = auth()->user();
        $accessibleIds = $this->locationScope->getAccessibleWarehouseIds($user);

        // Cek izin sekali per request, bukan per baris
        $userId = (int) $user->id;
        $canCreate = $user->can('rt.create');
        $canCancelAny = $user->can('rt.cancel');
        $csrf = csrf_token();

        $query = RequestTransfer::query()->select('request_transfers.*')
            ->with(['gudangAsal:id,nama', 'gudangTujuan:id,nama'])
            ->when($accessibleIds !== null, function ($q) use ($accessibleIds) {
                $q->where(function ($sub) use ($accessibleIds) {
                    $sub->whereIn('gudang_asal_id', $accessibleIds)
                        ->orWhereIn('gudang_tujuan_id', $accessibleIds);
                });
            })
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis', $request->jenis));

        return DataTables::eloquent($query)
            ->editColumn('jenis', fn ($rt) => ucwords(str_replace('_', ' ', $rt->jenis)))
            ->editColumn('status', function ($rt) {
                $variant = match ($rt->status) {
                    'draft' => 'bg-gray-100 text-gray-700 border-gray-300',
                    'diajukan' => 'bg-amber-50 text-amber-700 border-amber-300',
                    'disetujui' => 'bg-blue-50 text-blue-700 border-blue-300',
                    'diproses' => 'bg-indigo-50 text-indigo-700 border-indigo-300',
                    'dikirim' => 'bg-purple-50 text-purple-700 border-purple-300',
                    'selesai' => 'bg-emerald-50 text-emerald-700 border-emerald-300',
                    'dibatalkan' => 'bg-rose-50 text-rose-700 border-rose-300',
                    default => 'bg-gray-100 text-gray-700 border-gray-300',
                };
                $dotColor = match ($rt->status) {
                    'draft' => 'bg-gray-400',
                    'diajukan' => 'bg-amber-500',
                    'disetujui' => 'bg-blue-500',
                    'diproses' => 'bg-indigo-500',
                    'dikirim' => 'bg-purple-500',
                    'selesai' => 'bg-emerald-500',
                    'dibatalkan' => 'bg-rose-500',
                    default => 'bg-gray-400',
                };
                $label = ucwords(str_replace('_', ' ', $rt->status));

                return "<span class=\"inline-flex items-center gap-1.5 px-2 py-0.5 rounded-xs text-[11px] font-semibold border {$variant}\"><span class=\"w-1.5 h-1.5 rounded-full {$dotColor}\"></span>{$label}</span>";
            })
            ->addColumn('asal', fn ($rt) => $rt->gudangAsal?->nama ?? '-')
            ->addColumn('tujuan', fn ($rt) => $rt->gudangTujuan?->nama ?? '-')
            ->editColumn('created_at', fn ($rt) => $rt->created_at?->format('d/m/Y'))
            ->addColumn('action', function ($rt) use ($userId, $canCreate, $canCancelAny, $csrf) {
                $sep = '<span class="text-gray-300">|</span>';
                $html = '<div class="flex items-center justify-center gap-2">'
                    . '<a href="' . e(route('rt.show', $rt)) . '" class="text-primary-600 hover:text-primary-800 text-xs font-semibold hover:underline" title="Lihat rincian dokumen">Detail</a>';

                if ($rt->status === 'draft' && $canCreate) {
                    $html .= $sep . '<a href="' . e(route('rt.edit', $rt)) . '" class="text-amber-600 hover:text-amber-800 text-xs font-semibold hover:underline" title="Edit dokumen draft">Edit</a>';
                }

                $cancellable = in_array($rt->status, ['draft', 'diajukan'], true);
                $canCancel = $canCancelAny || ($cancellable && (int) $rt->created_by === $userId && $canCreate);
                if ($cancellable && $canCancel) {
                    $html .= $sep
                        . '<form method="POST" action="' . e(route('rt.transition', $rt)) . '" onsubmit="return confirm(\'Apakah Anda yakin ingin membatalkan dokumen ' . e($rt->no_transaksi) . '?\');" class="inline">'
                        . '<input type="hidden" name="_token" value="' . e($csrf) . '" autocomplete="off">'
                        . '<input type="hidden" name="aksi" value="cancel">'
                        . '<button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold hover:underline cursor-pointer" title="Batalkan pengajuan sebelum disetujui">Batalkan</button>'
                        . '</form>';
                }

                if (in_array($rt->status, ['diproses', 'dikirim', 'selesai'], true)) {
                    $html .= $sep
                        . '<a href="' . e(route('rt.surat-jalan', $rt)) . '" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold hover:underline flex items-center gap-0.5" title="Buka / Cetak Surat Jalan">'
                        . '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>'
                        . 'Surat Jalan</a>';
                }

                return $html . '</div>';
            })
            ->rawColumns(['action', 'status'])
            ->toJson();
    }

    public function create(Request $request)
    {
        $this->authorize('rt.create');
        $user = auth()->user();
        $accessibleIds = $this->locationScope->getAccessibleWarehouseIds($user);

        $gudang = Gudang::active()
            ->when($accessibleIds !== null, fn ($q) => $q->whereIn('id', $accessibleIds))
            ->orderBy('nama')
            ->get(['id', 'nama', 'tipe']);

        $allGudang = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'tipe']);
        $produk = Produk::active()->orderBy('nama')->get(['id', 'sku', 'nama', 'satuan']);

        $prefilledRows = [];
        $defaultJenis = 'req_bahan';
        $defaultGudangAsalId = null;
        $defaultGudangTujuanId = null;
        $defaultCatatan = null;
        $batch = null;

        if ($request->old('items')) {
            $oldItems = $request->old('items');
            $oldProductIds = array_filter(array_column($oldItems, 'produk_id'));
            $products = Produk::whereIn('id', $oldProductIds)->get()->keyBy('id');

            foreach ($oldItems as $item) {
                $pId = $item['produk_id'] ?? null;
                $prod = $pId ? $products->get($pId) : null;
                $prefilledRows[] = [
                    'produk_id' => $pId,
                    'qty' => $item['qty_diminta'] ?? '',
                    'satuan' => $prod?->satuan ?? '',
                    'selectedItem' => $prod ? [
                        'id' => $prod->id,
                        'sku' => $prod->sku,
                        'nama' => $prod->nama,
                        'satuan' => $prod->satuan,
                    ] : null,
                ];
            }
        } elseif ($request->filled('batch_id')) {
            $batch = BatchProduksi::with(['alokasi.bahan', 'gudangOperasional', 'produk'])->find($request->batch_id);
            if ($batch) {
                $defaultJenis = 'req_bahan';
                $defaultGudangTujuanId = $batch->gudang_operasional_id 
                    ? (int) $batch->gudang_operasional_id 
                    : (int) Gudang::where('tipe', 'operasional')->value('id');

                // Gudang pusat bahan baku sebagai default gudang asal
                $gudangPusat = Gudang::bahanBakuPusat()->first()
                    ?? Gudang::where('tipe', 'bahan_baku')->first()
                    ?? Gudang::where('kode', 'GD-PUSAT')->first();
                if ($gudangPusat) {
                    $defaultGudangAsalId = (int) $gudangPusat->id;
                }

                $defaultCatatan = "Permintaan kekurangan bahan baku untuk produksi Batch {$batch->no_batch}";

                $bahanIds = $batch->alokasi->pluck('bahan_id')->unique()->filter()->values();
                $stokOperasional = [];
                if ($batch->gudang_operasional_id && $bahanIds->isNotEmpty()) {
                    $stokOperasional = Stok::where('gudang_id', $batch->gudang_operasional_id)
                        ->whereIn('produk_id', $bahanIds)
                        ->pluck('qty_saat_ini', 'produk_id')
                        ->map(fn ($v) => (float) $v)
                        ->toArray();
                }

                $prefilledMap = [];
                foreach ($batch->alokasi as $alokasi) {
                    $bahan = $alokasi->bahan;
                    if (!$bahan) {
                        continue;
                    }

                    if ($request->filled('bahan_id') && (int) $request->bahan_id !== (int) $bahan->id) {
                        continue;
                    }

                    $qtyAlokasi = (float) $alokasi->qty_dialokasikan;
                    $fisikOp = (float) ($stokOperasional[$bahan->id] ?? 0);
                    $kurangFisik = max(0, $qtyAlokasi - $fisikOp);

                    if ($kurangFisik > 0 || $request->filled('bahan_id')) {
                        $qtyToRequest = $kurangFisik > 0 ? $kurangFisik : $qtyAlokasi;
                        $prefilledMap[$bahan->id] = [
                            'produk_id' => $bahan->id,
                            'qty' => round($qtyToRequest, 2),
                            'satuan' => $bahan->satuan,
                            'selectedItem' => [
                                'id' => $bahan->id,
                                'sku' => $bahan->sku,
                                'nama' => $bahan->nama,
                                'satuan' => $bahan->satuan,
                            ],
                        ];
                    }
                }
                $prefilledRows = array_values($prefilledMap);
            }
        }

        return view('request-transfer.create', compact(
            'gudang',
            'allGudang',
            'produk',
            'prefilledRows',
            'defaultJenis',
            'defaultGudangAsalId',
            'defaultGudangTujuanId',
            'defaultCatatan',
            'batch'
        ));
    }

    public function store(Request $request)
    {
        $this->authorize('rt.create');

        $data = $request->validate([
            'jenis' => ['required', Rule::in(RequestTransfer::JENIS)],
            'gudang_asal_id' => ['nullable', 'exists:gudang,id'],
            'gudang_tujuan_id' => ['nullable', 'exists:gudang,id'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'distinct', 'exists:produk,id'],
            'items.*.qty_diminta' => ['required', 'numeric', 'gt:0'],
        ]);

        $rt = DB::transaction(function () use ($data) {
            $rt = RequestTransfer::create([
                'no_transaksi' => $this->nextNo(),
                'jenis' => $data['jenis'],
                'gudang_asal_id' => $data['gudang_asal_id'] ?? null,
                'gudang_tujuan_id' => $data['gudang_tujuan_id'] ?? null,
                'status' => 'draft',
                'catatan' => $data['catatan'] ?? null,
                'created_by' => auth()->id(),
            ]);
            foreach ($data['items'] as $row) {
                $rt->items()->create([
                    'produk_id' => $row['produk_id'],
                    'qty_diminta' => $row['qty_diminta'],
                ]);
            }

            return $rt;
        });

        return redirect()->route('rt.show', $rt)->with('success', "Dokumen {$rt->no_transaksi} dibuat.");
    }

    public function stokTersedia(Request $request): JsonResponse
    {
        $gudangId = $request->query('gudang_id');
        if (! $gudangId) {
            return response()->json(['stok' => []]);
        }

        $produkIds = $request->query('produk_ids');
        if (is_string($produkIds)) {
            $produkIds = array_filter(explode(',', $produkIds));
        }

        $query = Stok::where('gudang_id', $gudangId);
        if (! empty($produkIds)) {
            $query->whereIn('produk_id', (array) $produkIds);
        }

        $stokMap = $query->pluck('qty_saat_ini', 'produk_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        return response()->json([
            'gudang_id' => (int) $gudangId,
            'stok' => $stokMap,
        ]);
    }

    public function edit(RequestTransfer $requestTransfer)
    {
        $this->authorize('rt.create');

        abort_unless($requestTransfer->status === 'draft', 422, 'Hanya dokumen mutasi berstatus Draft yang dapat diedit.');

        $user = auth()->user();
        $accessibleIds = $this->locationScope->getAccessibleWarehouseIds($user);

        $allGudang = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'tipe']);
        $produk = Produk::active()->orderBy('nama')->get(['id', 'sku', 'nama', 'satuan']);

        $requestTransfer->load(['items.produk:id,sku,nama,satuan']);

        $prefilledRows = [];
        $productIds = $requestTransfer->items->pluck('produk_id')->all();

        $stokMap = [];
        if ($requestTransfer->gudang_asal_id && ! empty($productIds)) {
            $stokMap = Stok::where('gudang_id', $requestTransfer->gudang_asal_id)
                ->whereIn('produk_id', $productIds)
                ->pluck('qty_saat_ini', 'produk_id')
                ->map(fn ($v) => (float) $v)
                ->toArray();
        }

        foreach ($requestTransfer->items as $item) {
            $prod = $item->produk;
            $prefilledRows[] = [
                'produk_id' => $item->produk_id,
                'qty' => (float) $item->qty_diminta,
                'satuan' => $prod?->satuan ?? '',
                'selectedItem' => $prod ? [
                    'id' => $prod->id,
                    'sku' => $prod->sku,
                    'nama' => $prod->nama,
                    'satuan' => $prod->satuan,
                ] : null,
            ];
        }

        return view('request-transfer.edit', [
            'requestTransfer' => $requestTransfer,
            'allGudang' => $allGudang,
            'produk' => $produk,
            'prefilledRows' => $prefilledRows,
            'stokMap' => $stokMap,
            'defaultJenis' => $requestTransfer->jenis,
            'defaultGudangAsalId' => $requestTransfer->gudang_asal_id,
            'defaultGudangTujuanId' => $requestTransfer->gudang_tujuan_id,
            'defaultCatatan' => $requestTransfer->catatan,
        ]);
    }

    public function update(Request $request, RequestTransfer $requestTransfer)
    {
        $this->authorize('rt.create');

        abort_unless($requestTransfer->status === 'draft', 422, 'Hanya dokumen mutasi berstatus Draft yang dapat diubah.');

        $data = $request->validate([
            'jenis' => ['required', Rule::in(RequestTransfer::JENIS)],
            'gudang_asal_id' => ['nullable', 'exists:gudang,id'],
            'gudang_tujuan_id' => ['nullable', 'exists:gudang,id'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'distinct', 'exists:produk,id'],
            'items.*.qty_diminta' => ['required', 'numeric', 'gt:0'],
        ]);

        DB::transaction(function () use ($requestTransfer, $data) {
            $requestTransfer->update([
                'jenis' => $data['jenis'],
                'gudang_asal_id' => $data['gudang_asal_id'] ?? null,
                'gudang_tujuan_id' => $data['gudang_tujuan_id'] ?? null,
                'catatan' => $data['catatan'] ?? null,
            ]);

            $requestTransfer->items()->delete();

            foreach ($data['items'] as $row) {
                $requestTransfer->items()->create([
                    'produk_id' => $row['produk_id'],
                    'qty_diminta' => $row['qty_diminta'],
                ]);
            }
        });

        return redirect()->route('rt.show', $requestTransfer)->with('success', "Dokumen {$requestTransfer->no_transaksi} berhasil diperbarui.");
    }

    public function suratJalan(RequestTransfer $requestTransfer)
    {
        $this->authorize('rt.view');

        $requestTransfer->load([
            'items.produk:id,sku,nama,satuan',
            'gudangAsal',
            'gudangTujuan',
            'creator:id,name',
        ]);

        return view('request-transfer.surat-jalan', compact('requestTransfer'));
    }

    public function show(RequestTransfer $requestTransfer)
    {
        $this->authorize('rt.view');
        $requestTransfer->load([
            'items.produk:id,sku,nama,satuan',
            'gudangAsal',
            'gudangTujuan',
            'creator:id,name',
        ]);

        $stokAsal = [];
        $hasDeficit = false;
        $itemWarnings = [];

        if ($requestTransfer->gudang_asal_id) {
            $produkIds = $requestTransfer->items->pluck('produk_id')->unique()->filter()->values();
            if ($produkIds->isNotEmpty()) {
                $stokAsal = Stok::where('gudang_id', $requestTransfer->gudang_asal_id)
                    ->whereIn('produk_id', $produkIds)
                    ->pluck('qty_saat_ini', 'produk_id')
                    ->map(fn ($v) => (float) $v)
                    ->toArray();
            }
        }

        foreach ($requestTransfer->items as $item) {
            $fisikAsal = (float) ($stokAsal[$item->produk_id] ?? 0);
            $diminta = (float) $item->qty_diminta;
            $kurang = max(0, $diminta - $fisikAsal);

            // Sinyal kritis jika pada tahap pra-pengiriman stok fisik di gudang asal tidak mencukupi
            if ($kurang > 0 && in_array($requestTransfer->status, ['draft', 'diajukan', 'disetujui'], true)) {
                $hasDeficit = true;
            }

            $itemWarnings[$item->id] = [
                'stok_fisik_asal' => $fisikAsal,
                'kurang' => $kurang,
                'is_cukup' => $kurang <= 0,
            ];
        }

        $user = auth()->user();
        $canReceive = in_array($requestTransfer->status, ['diproses', 'dikirim'], true)
            && $this->canUserReceiveTransfer($user, $requestTransfer);

        return view('request-transfer.show', compact(
            'requestTransfer',
            'stokAsal',
            'hasDeficit',
            'itemWarnings',
            'canReceive'
        ));
    }

    /**
     * Satu pintu transisi status untuk kedua pipeline.
     * Aksi: submit, approve, process, ship, receive, cancel.
     */
    public function transition(Request $request, RequestTransfer $requestTransfer)
    {
        $data = $request->validate([
            'aksi' => ['required', Rule::in(['submit', 'approve', 'process', 'ship', 'receive', 'cancel'])],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'exists:request_transfer_items,id'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.qty_baik' => ['nullable', 'numeric', 'min:0'],
            'items.*.qty_rusak' => ['nullable', 'numeric', 'min:0'],
            'items.*.keterangan_rusak' => ['nullable', 'string', 'max:500'],
        ]);
        $aksi = $data['aksi'];

        $rt = $requestTransfer;
        $user = auth()->user();

        if ($aksi === 'cancel') {
            $isCreatorBeforeApproval = in_array($rt->status, ['draft', 'diajukan'], true) && (int) $rt->created_by === (int) $user->id;
            if (! $user->can('rt.cancel') && ! ($isCreatorBeforeApproval && $user->can('rt.create'))) {
                $this->authorize('rt.cancel');
            }
        } else {
            $perm = [
                'submit' => 'rt.submit', 'approve' => 'rt.approve', 'process' => 'rt.process',
                'ship' => 'rt.ship', 'receive' => 'rt.receive',
            ][$aksi];
            $this->authorize($perm);
        }

        // Enforced Separation of Duties (SoD) on Receive
        if ($aksi === 'receive') {
            if (! $this->canUserReceiveTransfer($user, $rt)) {
                $destName = $rt->gudangTujuan?->nama ?? 'Tujuan';
                abort(403, "Aksi Ditolak: Anda tidak memiliki wewenang menerima transfer ini. Konfirmasi penerimaan dan inspeksi kualitas fisik wajib dilakukan oleh petugas Gudang {$destName}.");
            }
        }

        $qtyMap = collect($request->input('items', []))->keyBy('id');

        try {
            DB::transaction(function () use ($requestTransfer, $aksi, $qtyMap) {
                // Lock baris untuk mencegah race condition
                $rt = RequestTransfer::where('id', $requestTransfer->id)->lockForUpdate()->firstOrFail();

                // Idempotency: Jika status dokumen sudah mencapai target aksi ini (akibat double-click / network retry),
                // anggap sukses secara idempoten tanpa mengeksekusi mutasi stok berulang dan tanpa error transisi tidak valid.
                $isAlreadyTarget = match ($aksi) {
                    'submit' => $rt->status === 'diajukan',
                    'approve' => $rt->status === 'disetujui',
                    'process' => $rt->status === 'diproses',
                    'ship' => $rt->status === 'dikirim',
                    'receive' => $rt->status === 'selesai',
                    'cancel' => $rt->status === 'dibatalkan',
                };

                if ($isAlreadyTarget) {
                    return;
                }

                match ($aksi) {
                    'submit' => $this->doSubmit($rt),
                    'approve' => $this->doApprove($rt),
                    'process' => $this->doProcessOrShip($rt, $qtyMap, 'diproses', ['disetujui']),
                    'ship' => $this->doProcessOrShip($rt, $qtyMap, 'dikirim', ['draft']),
                    'receive' => $this->doReceive($rt, $qtyMap),
                    'cancel' => $this->doCancel($rt),
                };
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Status dokumen diperbarui.');
    }

    private function doSubmit(RequestTransfer $rt): void
    {
        abort_unless($rt->pakaiApproval() && $rt->status === 'draft', 422, 'Transisi tidak valid.');
        $rt->update(['status' => 'diajukan']);

        $rt->recordAudit(
            event: 'submitted',
            actionTitle: "Pengajuan dokumen {$rt->no_transaksi} ke Manager",
            customChanges: ['status' => ['draft', 'diajukan']],
            metadata: [
                'total_item' => $rt->items()->count(),
            ]
        );
    }

    private function doApprove(RequestTransfer $rt): void
    {
        abort_unless($rt->pakaiApproval() && $rt->status === 'diajukan', 422, 'Transisi tidak valid.');
        $rt->update(['status' => 'disetujui']);

        $rt->recordAudit(
            event: 'approved',
            actionTitle: "Persetujuan dokumen {$rt->no_transaksi} oleh Manager",
            customChanges: ['status' => ['diajukan', 'disetujui']],
            metadata: [
                'approved_by' => auth()->user()->name,
            ]
        );
    }

    /** Proses (approval pipeline) / kirim langsung (direct pipeline): stok keluar dari gudang asal. */
    private function doProcessOrShip(RequestTransfer $rt, $qtyMap, string $statusBaru, array $allowed): void
    {
        if ($statusBaru === 'diproses') {
            abort_unless($rt->pakaiApproval(), 422, 'Aksi proses hanya untuk Request Bahan.');
        } else {
            abort_if($rt->pakaiApproval(), 422, 'Transfer langsung tidak memakai aksi ini.');
        }
        abort_unless(in_array($rt->status, $allowed, true), 422, 'Transisi tidak valid.');
        $oldStatus = $rt->status;

        foreach ($rt->items as $item) {
            $qty = (float) ($qtyMap[$item->id]['qty'] ?? $item->qty_diminta);
            $item->update(['qty_dikirim' => $qty]);
            if ($qty > 0 && $rt->gudang_asal_id) {
                $this->stok->keluar($item->produk_id, $rt->gudang_asal_id, $qty, 'request_transfer', $rt->id, "Kirim {$rt->no_transaksi}");
            }
        }
        $rt->update(['status' => $statusBaru]);

        $rt->loadMissing('gudangAsal');
        $rt->recordAudit(
            event: $statusBaru === 'diproses' ? 'processed' : 'shipped',
            actionTitle: ($statusBaru === 'diproses' ? "Pemrosesan & pengeluaran bahan dokumen {$rt->no_transaksi} dari " : "Pengiriman surat jalan dokumen {$rt->no_transaksi} dari ") . ($rt->gudangAsal?->nama ?? 'Gudang Asal'),
            customChanges: ['status' => [$oldStatus, $statusBaru]],
            metadata: [
                'total_item' => $rt->items->count(),
                'gudang_asal' => $rt->gudangAsal?->nama,
            ]
        );
    }

    /** Terima: stok masuk ke gudang tujuan, dokumen selesai. */
    private function doReceive(RequestTransfer $rt, $qtyMap): void
    {
        abort_unless(in_array($rt->status, ['diproses', 'dikirim'], true), 422, 'Transisi tidak valid.');

        $rt->loadMissing('items.produk');

        foreach ($rt->items as $item) {
            $row = $qtyMap[$item->id] ?? [];
            $hasQualityInput = isset($row['qty_baik']) || isset($row['qty_rusak']);

            if ($hasQualityInput) {
                $qtyBaik = (float) ($row['qty_baik'] ?? 0);
                $qtyRusak = (float) ($row['qty_rusak'] ?? 0);
                $qtyTotal = $qtyBaik + $qtyRusak;
                $qtyKirim = (float) ($item->qty_dikirim ?? $item->qty_diminta);

                if ($qtyTotal > $qtyKirim) {
                    throw new \RuntimeException("Total kuantitas diterima ({$qtyTotal}) untuk {$item->produk->nama} tidak boleh melebihi kuantitas dikirim ({$qtyKirim}).");
                }

                $satuanLower = strtolower(trim($item->produk->satuan ?? 'pcs'));
                $isDecimal = in_array($satuanLower, ['ml', 'l', 'liter', 'gr', 'gram', 'kg', 'kilogram'], true);
                if (! $isDecimal) {
                    if (abs($qtyBaik - round($qtyBaik)) > 0.00001 || abs($qtyRusak - round($qtyRusak)) > 0.00001) {
                        throw new \RuntimeException("Kuantitas untuk {$item->produk->nama} ({$item->produk->satuan}) harus berupa bilangan bulat (satuan), bukan pecahan/desimal.");
                    }
                    $qtyBaik = (float) round($qtyBaik);
                    $qtyRusak = (float) round($qtyRusak);
                    $qtyTotal = $qtyBaik + $qtyRusak;
                }

                $keteranganRusak = $row['keterangan_rusak'] ?? null;

                $item->update([
                    'qty_diterima' => $qtyTotal,
                    'qty_baik' => $qtyBaik,
                    'qty_rusak' => $qtyRusak,
                    'keterangan_rusak' => $keteranganRusak,
                ]);

                // Hanya qty_baik yang masuk ke saldo stok fisik aktif gudang tujuan!
                if ($qtyBaik > 0 && $rt->gudang_tujuan_id) {
                    $note = "Terima {$rt->no_transaksi}" . ($qtyRusak > 0 ? " (Baik: {$qtyBaik}, Rusak: {$qtyRusak})" : "");
                    $this->stok->masuk($item->produk_id, $rt->gudang_tujuan_id, $qtyBaik, 'request_transfer', $rt->id, $note);
                }
            } else {
                $qty = (float) ($row['qty'] ?? $item->qty_dikirim ?? $item->qty_diminta);
                $item->update([
                    'qty_diterima' => $qty,
                    'qty_baik' => $qty,
                    'qty_rusak' => 0,
                ]);
                if ($qty > 0 && $rt->gudang_tujuan_id) {
                    $this->stok->masuk($item->produk_id, $rt->gudang_tujuan_id, $qty, 'request_transfer', $rt->id, "Terima {$rt->no_transaksi}");
                }
            }
        }
        $rt->update(['status' => 'selesai']);

        $rt->loadMissing(['gudangAsal', 'gudangTujuan']);
        $rt->recordAudit(
            event: 'received',
            actionTitle: "Penerimaan mutasi {$rt->no_transaksi} di " . ($rt->gudangTujuan?->nama ?? 'Gudang Tujuan') . " (Status: Selesai)",
            customChanges: ['status' => ['diproses', 'selesai']],
            metadata: [
                'total_item' => $rt->items->count(),
                'gudang_asal' => $rt->gudangAsal?->nama,
                'gudang_tujuan' => $rt->gudangTujuan?->nama,
            ]
        );
    }

    private function doCancel(RequestTransfer $rt): void
    {
        abort_if(in_array($rt->status, ['selesai', 'dibatalkan'], true), 422, 'Dokumen tidak dapat dibatalkan.');
        $oldStatus = $rt->status;
        $rt->update(['status' => 'dibatalkan']);

        $rt->recordAudit(
            event: 'cancelled',
            actionTitle: "Pembatalan dokumen {$rt->no_transaksi}",
            customChanges: ['status' => [$oldStatus, 'dibatalkan']]
        );
    }

    private function nextNo(): string
    {
        $year = now()->year;
        $count = RequestTransfer::whereYear('created_at', $year)->count() + 1;

        return sprintf('TR-%d-%04d', $year, $count);
    }

    /**
     * Memeriksa apakah pengguna berwenang mengonfirmasi penerimaan transfer ini
     * sesuai prinsip Separation of Duties (SoD) dan hierarki gudang.
     */
    public function canUserReceiveTransfer(User $user, RequestTransfer $rt): bool
    {
        if (! $user->can('rt.receive')) {
            return false;
        }

        // Hanya role 'manager' eksplisit yang memiliki hak supervisi/override manajerial
        if ($user->hasRole('manager')) {
            return true;
        }

        $dest = $rt->gudangTujuan;
        if (! $dest) {
            return false;
        }

        // 1. User wajib memiliki hak akses lokasi ke gudang tujuan
        if (! $this->locationScope->canAccessWarehouse($user, $dest->id)) {
            return false;
        }

        // 2. Anti Self-Dealing: Pembuat pengajuan transfer keluar dilarang menerima dokumennya sendiri
        //    (Kecuali jenis 'req_bahan', di mana staf pemohon di lab meminta bahan untuk dikonsumsi di labnya).
        if ($rt->jenis !== 'req_bahan' && (int) $rt->created_by === (int) $user->id) {
            return false;
        }

        // 3. Separation of Duties (SoD) berdasarkan fungsi fasilitas tujuan:

        // A. Pengiriman ke Fulfillment (misal kirim_produk_jadi, antar_fulfillment):
        //    Penerima WAJIB memiliki peran 'fulfillment'. Staf operasional/pengirim dilarang menerima!
        if ($dest->isFulfillment() || in_array($rt->jenis, ['kirim_produk_jadi', 'antar_fulfillment'], true)) {
            return $user->hasRole('fulfillment');
        }

        // B. Penerimaan di Gudang Operasional (Lab) (misal req_bahan, retur_produk_jadi):
        //    Dapat diterima oleh:
        //    1) Staf Operasional Lab (role 'operasional'), ATAU
        //    2) Staf Gudang Operasional (role 'gudang' yang ditugaskan di gudang operasional tujuan),
        //       dengan syarat pos utamanya bukan di gudang asal pengirim (Gudang Bahan Baku Pusat).
        if ($dest->isOperasional() || in_array($rt->jenis, ['req_bahan', 'retur_produk_jadi'], true)) {
            if ($user->hasRole('operasional')) {
                return true;
            }

            if ($user->hasRole('gudang')) {
                // Jika pos utama user adalah gudang asal pengirim, tolak (SoD)
                $primaryGudangId = $user->primaryGudang()?->id;
                if ($primaryGudangId && (int) $primaryGudangId === (int) $rt->gudang_asal_id) {
                    return false;
                }

                // Jika user memiliki akses restricted, pastikan dia memang memiliki hak atas gudang tujuan
                if ($user->warehouse_access_type === 'restricted') {
                    return $user->gudangs()->where('gudang.id', $dest->id)->exists();
                }

                return true;
            }

            return false;
        }

        // C. Penerimaan di Gudang Bahan Baku (misal retur_bahan):
        //    Penerima WAJIB memiliki peran 'gudang' dan pos utamanya bukan di gudang asal pengirim.
        if ($dest->isBahanBaku() || $rt->jenis === 'retur_bahan') {
            if (! $user->hasRole('gudang')) {
                return false;
            }

            $primaryGudangId = $user->primaryGudang()?->id;
            if ($primaryGudangId && (int) $primaryGudangId === (int) $rt->gudang_asal_id) {
                return false;
            }

            if ($user->warehouse_access_type === 'restricted') {
                return $user->gudangs()->where('gudang.id', $dest->id)->exists();
            }

            return true;
        }

        return true;
    }
}
