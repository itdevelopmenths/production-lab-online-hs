<?php

namespace App\Http\Controllers;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\RequestTransfer;
use App\Models\Stok;
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
            ->editColumn('status', fn ($rt) => ucwords(str_replace('_', ' ', $rt->status)))
            ->addColumn('asal', fn ($rt) => $rt->gudangAsal?->nama ?? '-')
            ->addColumn('tujuan', fn ($rt) => $rt->gudangTujuan?->nama ?? '-')
            ->editColumn('created_at', fn ($rt) => $rt->created_at?->format('d/m/Y'))
            ->addColumn('action', fn ($rt) => view('request-transfer._actions', ['rt' => $rt])->render())
            ->rawColumns(['action'])
            ->toJson();
    }

    public function create()
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

        return view('request-transfer.create', compact('gudang', 'allGudang', 'produk'));
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
        $isManager = $user->hasRole('manager') || $this->locationScope->isGlobal($user);
        $canAccessDestination = $requestTransfer->gudang_tujuan_id 
            ? $this->locationScope->canAccessWarehouse($user, $requestTransfer->gudang_tujuan_id) 
            : false;

        $isFulfillmentDestination = $requestTransfer->gudangTujuan 
            && in_array($requestTransfer->gudangTujuan->tipe, ['fulfillment_pusat', 'fulfillment_cabang', 'fulfillment'], true);

        $canReceive = false;
        if (in_array($requestTransfer->status, ['diproses', 'dikirim'], true) && $user->can('rt.receive')) {
            if ($isManager) {
                $canReceive = true;
            } elseif ($canAccessDestination) {
                if (!$isFulfillmentDestination || $user->hasRole('fulfillment')) {
                    $canReceive = true;
                }
            }
        }

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
        $perm = [
            'submit' => 'rt.submit', 'approve' => 'rt.approve', 'process' => 'rt.process',
            'ship' => 'rt.ship', 'receive' => 'rt.receive', 'cancel' => 'rt.cancel',
        ][$aksi];
        $this->authorize($perm);

        $user = auth()->user();
        $isManager = $user->hasRole('manager') || $this->locationScope->isGlobal($user);

        // Enforced Separation of Duties (SoD) on Receive
        if ($aksi === 'receive' && !$isManager) {
            $canAccessDestination = $rt->gudang_tujuan_id 
                ? $this->locationScope->canAccessWarehouse($user, $rt->gudang_tujuan_id) 
                : false;

            $isFulfillmentDestination = $rt->gudangTujuan 
                && in_array($rt->gudangTujuan->tipe, ['fulfillment_pusat', 'fulfillment_cabang', 'fulfillment'], true);

            // User must be authorized for destination warehouse
            if (!$canAccessDestination) {
                abort(403, 'Aksi Ditolak: Anda merupakan pihak pengirim. Konfirmasi penerimaan wajib dilakukan oleh petugas Gudang Fulfillment tujuan.');
            }

            // If destination is fulfillment, only fulfillment role can receive
            if ($isFulfillmentDestination && !$user->hasRole('fulfillment')) {
                abort(403, 'Aksi Ditolak: Anda merupakan pihak pengirim. Konfirmasi penerimaan wajib dilakukan oleh petugas Gudang Fulfillment tujuan.');
            }
        }

        $qtyMap = collect($request->input('items', []))->keyBy('id');

        try {
            DB::transaction(function () use ($rt, $aksi, $qtyMap) {
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
    }

    private function doApprove(RequestTransfer $rt): void
    {
        abort_unless($rt->pakaiApproval() && $rt->status === 'diajukan', 422, 'Transisi tidak valid.');
        $rt->update(['status' => 'disetujui']);
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

        foreach ($rt->items as $item) {
            $qty = (float) ($qtyMap[$item->id]['qty'] ?? $item->qty_diminta);
            $item->update(['qty_dikirim' => $qty]);
            if ($qty > 0 && $rt->gudang_asal_id) {
                $this->stok->keluar($item->produk_id, $rt->gudang_asal_id, $qty, 'request_transfer', $rt->id, "Kirim {$rt->no_transaksi}");
            }
        }
        $rt->update(['status' => $statusBaru]);
    }

    /** Terima: stok masuk ke gudang tujuan, dokumen selesai. */
    private function doReceive(RequestTransfer $rt, $qtyMap): void
    {
        abort_unless(in_array($rt->status, ['diproses', 'dikirim'], true), 422, 'Transisi tidak valid.');

        foreach ($rt->items as $item) {
            $row = $qtyMap[$item->id] ?? [];
            $hasQualityInput = isset($row['qty_baik']) || isset($row['qty_rusak']);

            if ($hasQualityInput) {
                $qtyBaik = (float) ($row['qty_baik'] ?? 0);
                $qtyRusak = (float) ($row['qty_rusak'] ?? 0);
                $qtyTotal = $qtyBaik + $qtyRusak;
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
    }

    private function doCancel(RequestTransfer $rt): void
    {
        abort_if(in_array($rt->status, ['selesai', 'dibatalkan'], true), 422, 'Dokumen tidak dapat dibatalkan.');
        $rt->update(['status' => 'dibatalkan']);
    }

    private function nextNo(): string
    {
        $year = now()->year;
        $count = RequestTransfer::whereYear('created_at', $year)->count() + 1;

        return sprintf('TR-%d-%04d', $year, $count);
    }
}
