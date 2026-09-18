<?php

namespace App\Http\Controllers;

use App\Models\BarangDatang;
use App\Models\BarangDatangItem;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Uom;
use App\Services\Purchasing\PurchasingCostCalculator;
use App\Services\Purchasing\PurchasingPaymentService;
use App\Services\StokService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class PurchasingController extends Controller
{
    public function __construct(
        private readonly StokService $stok,
        private readonly PurchasingCostCalculator $calculator,
        private readonly PurchasingPaymentService $paymentService
    ) {
    }

    public function index()
    {
        $this->authorize('purchasing.view');
        $user = auth()->user();
        $canSeePrice = $user->can('purchasing.price.view');
        $canSeeApprovalTab = $user->hasAnyRole(['manager', 'purchasing', 'admin_holding', 'super_admin']) || $user->can('purchasing.approve');
        $pendingApprovalCount = $canSeeApprovalTab
            ? PurchaseOrder::where('status', 'diajukan')->count()
            : 0;

        return view('purchasing.index', compact('canSeePrice', 'pendingApprovalCount', 'canSeeApprovalTab'));
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('purchasing.view');
        $user = auth()->user();
        $canSeePrice = $user->can('purchasing.price.view');
        $canEditUser = $user->can('purchasing.edit');

        $query = PurchaseOrder::query()->select('purchase_orders.*')
            ->with(['supplier:id,nama', 'gudang:id,nama', 'barangDatang:id,po_id,tanggal_terima'])
            ->withSum('items as total_nilai', 'harga_total')
            ->withSum('payments as total_dibayar', 'nominal');

        if ($canSeePrice && $request->filled('status_bayar')) {
            if ($request->status_bayar === 'belum_lunas') {
                $query->where(function ($q) {
                    $q->where('purchase_orders.status_pembayaran', 'belum_lunas')
                      ->orWhereNull('purchase_orders.status_pembayaran');
                });
            } else {
                $query->where('purchase_orders.status_pembayaran', $request->status_bayar);
            }
        }

        if ($request->filled('status_po')) {
            $query->where('purchase_orders.status', $request->status_po);
        }

        return DataTables::eloquent($query)
            ->addColumn('no_invoice', fn ($po) => $po->no_invoice ? $po->no_invoice : $po->no_po)
            ->addColumn('supplier_nama', fn ($po) => $po->supplier?->nama ?? '—')
            ->addColumn('gudang_nama', fn ($po) => $po->gudang?->nama ?? '—')
            ->editColumn('tanggal', fn ($po) => $po->tanggal?->format('d/m/Y') ?? '—')
            ->addColumn('eta', fn ($po) => $po->eta?->format('d/m/Y') ?? '—')
            ->addColumn('tgl_aktual_tiba', function ($po) {
                $lastBardat = $po->barangDatang->sortByDesc('tanggal_terima')->first();

                return $lastBardat?->tanggal_terima?->format('d/m/Y') ?? '—';
            })
            ->editColumn('status', fn ($po) => ucwords(str_replace('_', ' ', $po->status)))
            ->addColumn('status_pembayaran', fn ($po) => $canSeePrice ? ucwords(str_replace('_', ' ', $po->status_pembayaran ?? 'belum_lunas')) : '—')
            ->addColumn('total_nilai', fn ($po) => $canSeePrice ? number_format((float) $po->totalNilai(), 0, ',', '.') : '—')
            ->addColumn('sisa', fn ($po) => $canSeePrice ? number_format((float) $po->sisaTagihan(), 0, ',', '.') : '—')
            ->addColumn('action', fn ($po) => view('purchasing._actions', [
                'po' => $po,
                'canSeePrice' => $canSeePrice,
                'canEdit' => $canEditUser && in_array($po->status, ['draft', 'diajukan', 'disetujui'], true) && $po->barangDatang->isEmpty(),
            ])->render())
            ->rawColumns(['action'])
            ->toJson();
    }

    public function dataAp(Request $request): JsonResponse
    {
        $this->authorize('purchasing.price.view');

        $query = PurchaseOrder::query()->select('purchase_orders.*')
            ->with(['supplier:id,nama', 'termins'])
            ->withSum('items as total_nilai', 'harga_total')
            ->withSum('payments as total_dibayar', 'nominal');

        if ($request->filled('status_bayar')) {
            if ($request->status_bayar === 'belum_lunas') {
                $query->where(function ($q) {
                    $q->where('purchase_orders.status_pembayaran', 'belum_lunas')
                      ->orWhereNull('purchase_orders.status_pembayaran');
                });
            } else {
                $query->where('purchase_orders.status_pembayaran', $request->status_bayar);
            }
        }

        if ($request->filled('skema_bayar')) {
            $query->where('purchase_orders.skema_bayar', $request->skema_bayar);
        }

        return DataTables::eloquent($query)
            ->addColumn('no_invoice', fn ($po) => $po->no_invoice ? $po->no_invoice : $po->no_po)
            ->addColumn('supplier_nama', fn ($po) => $po->supplier?->nama ?? '—')
            ->editColumn('tanggal', fn ($po) => $po->tanggal?->format('d/m/Y') ?? '—')
            ->addColumn('skema_bayar', fn ($po) => ucfirst($po->skema_bayar ?? 'cash'))
            ->addColumn('total_nilai', fn ($po) => number_format((float) $po->totalNilai(), 0, ',', '.'))
            ->addColumn('total_dibayar', fn ($po) => number_format((float) $po->totalDibayar(), 0, ',', '.'))
            ->addColumn('sisa', fn ($po) => number_format((float) $po->sisaTagihan(), 0, ',', '.'))
            ->addColumn('jatuh_tempo_terdekat', function ($po) {
                if ($po->skema_bayar === 'tempo' && $po->tanggal_tempo) {
                    return $po->tanggal_tempo->format('d/m/Y');
                }
                $nextTermin = $po->termins->where('status', '!=', 'lunas')->sortBy('tanggal_tempo')->first();

                return $nextTermin ? $nextTermin->tanggal_tempo->format('d/m/Y') : ($po->eta ? $po->eta->format('d/m/Y') : '—');
            })
            ->addColumn('status_pembayaran', fn ($po) => $po->status_pembayaran ?? 'belum_lunas')
            ->addColumn('action', fn ($po) => view('purchasing._actions_ap', ['po' => $po])->render())
            ->rawColumns(['action'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('purchasing.create');
        $suppliers = Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori']);
        $gudang = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'tipe']);
        $produk = Produk::bahan()->active()->orderBy('nama')->get(['id', 'sku', 'nama', 'nama_produk', 'satuan', 'faktor_konversi']);
        $uomList = Uom::active()->orderBy('nama')->get(['id', 'kode', 'nama', 'kategori', 'satuan_dasar', 'faktor_konversi']);

        return view('purchasing.create', compact('suppliers', 'gudang', 'produk', 'uomList'));
    }

    public function store(Request $request)
    {
        $this->authorize('purchasing.create');

        $data = $request->validate([
            'no_invoice' => ['nullable', 'string', 'max:50'],
            'supplier_id' => ['required', 'exists:supplier,id'],
            'gudang_id' => ['nullable', 'exists:gudang,id'],
            'tanggal' => ['required', 'date'],
            'eta' => ['nullable', 'date'],
            'sumber_dana' => ['nullable', 'string', 'max:50'],
            'skema_bayar' => ['nullable', Rule::in(['cash', 'tempo', 'termin'])],
            'tanggal_tempo' => ['nullable', 'date', 'required_if:skema_bayar,tempo'],
            'diskon_total' => ['nullable', 'numeric', 'min:0'],
            'ppn_nominal' => ['nullable', 'numeric', 'min:0'],
            'ongkos_kirim' => ['nullable', 'numeric', 'min:0'],
            'adjustment' => ['nullable', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'distinct', 'exists:produk,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.qty_satuan_beli' => ['nullable', 'numeric', 'gt:0'],
            'items.*.satuan_beli' => ['nullable', 'string', 'max:20'],
            'items.*.faktor_konversi' => ['nullable', 'numeric', 'gt:0'],
            'items.*.harga_total' => ['required', 'numeric', 'min:0'],
            'items.*.diskon' => ['nullable', 'numeric', 'min:0'],
            'items.*.ppn' => ['nullable', 'numeric', 'min:0'],
            'items.*.ongkir' => ['nullable', 'numeric', 'min:0'],
            'items.*.adjustment' => ['nullable', 'numeric'],
            'termins' => ['nullable', 'array'],
            'termins.*.tanggal_tempo' => ['required_with:termins', 'date'],
            'termins.*.nominal_tagihan' => ['required_with:termins', 'numeric', 'gt:0'],
            'termins.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $totals = $this->calculator->calculatePoTotals(
            $data['items'],
            (float) ($data['diskon_total'] ?? 0),
            (float) ($data['ppn_nominal'] ?? 0),
            (float) ($data['ongkos_kirim'] ?? 0),
            (float) ($data['adjustment'] ?? 0)
        );

        $po = DB::transaction(function () use ($data, $totals) {
            $po = PurchaseOrder::create([
                'no_po' => $this->nextNoPo(),
                'no_invoice' => ! empty($data['no_invoice']) ? $data['no_invoice'] : $this->nextNoInvoice(),
                'supplier_id' => $data['supplier_id'],
                'gudang_id' => $data['gudang_id'] ?? null,
                'tanggal' => $data['tanggal'],
                'eta' => $data['eta'] ?? null,
                'sumber_dana' => $data['sumber_dana'] ?? null,
                'skema_bayar' => $data['skema_bayar'] ?? 'cash',
                'tanggal_tempo' => ($data['skema_bayar'] ?? '') === 'tempo' ? ($data['tanggal_tempo'] ?? null) : null,
                'subtotal_produk' => $totals['subtotal_produk'],
                'diskon_total' => $totals['diskon_total'],
                'ppn_nominal' => $totals['ppn_nominal'],
                'ongkos_kirim' => $totals['ongkos_kirim'],
                'adjustment' => $totals['adjustment'],
                'grand_total' => $totals['grand_total'],
                'status' => 'draft',
                'status_pembayaran' => 'belum_lunas',
                'dari_analisa' => false,
                'created_by' => auth()->id(),
            ]);

            foreach ($totals['items'] as $itemData) {
                $po->items()->create($itemData);
            }

            if (! empty($data['termins']) && ($data['skema_bayar'] ?? '') === 'termin') {
                $this->paymentService->createTerminsForPo($po, $data['termins']);
            }

            return $po;
        });

        return redirect()->route('purchasing.show', $po)->with('success', "PO {$po->no_po} dibuat (draft).");
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.edit');

        if (in_array($purchaseOrder->status, ['selesai', 'dibatalkan'], true) || $purchaseOrder->barangDatang()->exists()) {
            return redirect()->route('purchasing.show', $purchaseOrder)
                ->with('error', 'Dokumen Purchase Order ini sudah final / barang sudah diterima sehingga tidak dapat diubah.');
        }

        $purchaseOrder->load(['items.produk', 'termins']);
        $suppliers = Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori']);
        $gudang = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'tipe']);
        $produk = Produk::bahan()->active()->orderBy('nama')->get(['id', 'sku', 'nama', 'nama_produk', 'satuan', 'faktor_konversi']);
        $uomList = Uom::active()->orderBy('nama')->get(['id', 'kode', 'nama', 'kategori', 'satuan_dasar', 'faktor_konversi']);
        $productIds = $purchaseOrder->items->pluck('produk_id')->filter()->unique()->values()->all();
        $hppMap = app(\App\Services\StokService::class)->resolveHppMap($productIds);

        return view('purchasing.edit', compact('purchaseOrder', 'suppliers', 'gudang', 'produk', 'uomList', 'hppMap'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('purchasing.edit');

        if (in_array($purchaseOrder->status, ['selesai', 'dibatalkan'], true) || $purchaseOrder->barangDatang()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Dokumen Purchase Order ini sudah final / barang sudah diterima sehingga tidak dapat diubah.',
            ]);
        }

        $data = $request->validate([
            'no_invoice' => ['nullable', 'string', 'max:50'],
            'supplier_id' => ['required', 'exists:supplier,id'],
            'gudang_id' => ['nullable', 'exists:gudang,id'],
            'tanggal' => ['required', 'date'],
            'eta' => ['nullable', 'date'],
            'sumber_dana' => ['nullable', 'string', 'max:50'],
            'skema_bayar' => ['nullable', Rule::in(['cash', 'tempo', 'termin'])],
            'tanggal_tempo' => ['nullable', 'date', 'required_if:skema_bayar,tempo'],
            'diskon_total' => ['nullable', 'numeric', 'min:0'],
            'ppn_nominal' => ['nullable', 'numeric', 'min:0'],
            'ongkos_kirim' => ['nullable', 'numeric', 'min:0'],
            'adjustment' => ['nullable', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'distinct', 'exists:produk,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.qty_satuan_beli' => ['nullable', 'numeric', 'gt:0'],
            'items.*.satuan_beli' => ['nullable', 'string', 'max:20'],
            'items.*.faktor_konversi' => ['nullable', 'numeric', 'gt:0'],
            'items.*.harga_total' => ['required', 'numeric', 'min:0'],
            'items.*.diskon' => ['nullable', 'numeric', 'min:0'],
            'items.*.ppn' => ['nullable', 'numeric', 'min:0'],
            'items.*.ongkir' => ['nullable', 'numeric', 'min:0'],
            'items.*.adjustment' => ['nullable', 'numeric'],
            'termins' => ['nullable', 'array'],
            'termins.*.tanggal_tempo' => ['required_with:termins', 'date'],
            'termins.*.nominal_tagihan' => ['required_with:termins', 'numeric', 'gt:0'],
            'termins.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $totals = $this->calculator->calculatePoTotals(
            $data['items'],
            (float) ($data['diskon_total'] ?? 0),
            (float) ($data['ppn_nominal'] ?? 0),
            (float) ($data['ongkos_kirim'] ?? 0),
            (float) ($data['adjustment'] ?? 0)
        );

        $totalDibayar = $purchaseOrder->totalDibayar();
        if ($totalDibayar > 0 && $totals['grand_total'] < $totalDibayar) {
            throw ValidationException::withMessages([
                'items' => 'Grand total baru (Rp ' . number_format($totals['grand_total'], 0, ',', '.') . ') tidak boleh lebih kecil dari total yang sudah dibayar (Rp ' . number_format($totalDibayar, 0, ',', '.') . ').',
            ]);
        }

        DB::transaction(function () use ($purchaseOrder, $data, $totals) {
            $purchaseOrder->update([
                'no_invoice' => ! empty($data['no_invoice']) ? $data['no_invoice'] : $purchaseOrder->no_invoice,
                'supplier_id' => $data['supplier_id'],
                'gudang_id' => $data['gudang_id'] ?? null,
                'tanggal' => $data['tanggal'],
                'eta' => $data['eta'] ?? null,
                'sumber_dana' => $data['sumber_dana'] ?? null,
                'skema_bayar' => $data['skema_bayar'] ?? 'cash',
                'tanggal_tempo' => ($data['skema_bayar'] ?? '') === 'tempo' ? ($data['tanggal_tempo'] ?? null) : null,
                'subtotal_produk' => $totals['subtotal_produk'],
                'diskon_total' => $totals['diskon_total'],
                'ppn_nominal' => $totals['ppn_nominal'],
                'ongkos_kirim' => $totals['ongkos_kirim'],
                'adjustment' => $totals['adjustment'],
                'grand_total' => $totals['grand_total'],
            ]);

            // Replace items dengan data terbaru & alokasi HPP prorata
            $purchaseOrder->items()->delete();
            foreach ($totals['items'] as $itemData) {
                $purchaseOrder->items()->create($itemData);
            }

            // Sync jadwal termin jika skema termin
            if (! empty($data['termins']) && ($data['skema_bayar'] ?? '') === 'termin') {
                $purchaseOrder->termins()->delete();
                $this->paymentService->createTerminsForPo($purchaseOrder, $data['termins']);
            } elseif (($data['skema_bayar'] ?? '') !== 'termin') {
                $purchaseOrder->termins()->delete();
            }

            $purchaseOrder->refreshStatusPembayaran();
            $purchaseOrder->save();
        });

        return redirect()->route('purchasing.show', $purchaseOrder)
            ->with('success', "PO {$purchaseOrder->no_po} berhasil diperbarui.");
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.view');
        $purchaseOrder->load([
            'supplier',
            'gudang',
            'items.produk:id,sku,nama,satuan,harga_hpp',
            'items.bardatItems',
            'payments',
            'termins',
            'barangDatang.creator:id,name',
            'barangDatang.items.poItem.produk:id,sku,nama,satuan',
            'creator:id,name',
        ]);

        // Auto-refresh status pembayaran jika ada perubahan status (misal lewat jatuh tempo)
        if (! $purchaseOrder->isLunas()) {
            $prevStatus = $purchaseOrder->status_pembayaran;
            $purchaseOrder->refreshStatusPembayaran();
            if ($purchaseOrder->status_pembayaran !== $prevStatus) {
                $purchaseOrder->save();
            }
        }

        $gudang = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'tipe']);
        $canSeePrice = auth()->user()->can('purchasing.price.view');

        return view('purchasing.show', compact('purchaseOrder', 'gudang', 'canSeePrice'));
    }

    public function quickDates(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('purchasing.edit');
        if (in_array($purchaseOrder->status, ['selesai', 'dibatalkan'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'PO sudah final dan tidak dapat diubah tanggalnya.',
            ], 422);
        }

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'eta' => ['nullable', 'date'],
        ]);

        $purchaseOrder->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Tanggal PO & ETA berhasil diperbarui.',
            'tanggal' => $purchaseOrder->tanggal?->format('d/m/Y'),
            'eta' => $purchaseOrder->eta?->format('d/m/Y') ?? '—',
        ]);
    }

    public function submit(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.submit');
        $this->ensureStatus($purchaseOrder, ['draft']);
        $purchaseOrder->update(['status' => 'diajukan']);

        return back()->with('success', 'PO diajukan ke Manager.');
    }

    public function approve(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.approve');
        $this->ensureStatus($purchaseOrder, ['diajukan']);
        $purchaseOrder->update(['status' => 'dikirim_ke_gudang']);

        return back()->with('success', 'PO disetujui & dikirim ke Gudang.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.receive');
        $this->ensureStatus($purchaseOrder, ['dikirim_ke_gudang']);

        $data = $request->validate([
            'gudang_id' => ['nullable', 'exists:gudang,id'],
            'tanggal_terima' => ['required', 'date'],
            'kondisi' => ['required', Rule::in(['baik', 'rusak_sebagian'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.po_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.qty_diterima' => ['required', 'numeric', 'min:0'],
            'items.*.keterangan_selisih' => ['nullable', 'string', 'max:500'],
        ]);

        // Lokasi stok penerimaan: Mutlak sesuai dengan lokasi gudang pada PO
        $targetGudangId = $purchaseOrder->gudang_id ?: ($data['gudang_id'] ?? null);

        if (! $targetGudangId) {
            $targetGudangId = Gudang::where('status', 'aktif')->first()?->id;
        }

        DB::transaction(function () use ($data, $purchaseOrder, $targetGudangId) {
            $bardat = BarangDatang::create([
                'po_id' => $purchaseOrder->id,
                'tanggal_terima' => $data['tanggal_terima'],
                'kondisi' => $data['kondisi'],
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $row) {
                $qty = (float) $row['qty_diterima'];
                $item = $purchaseOrder->items()->findOrFail($row['po_item_id']);
                $selisih = round($qty - (float) $item->qty, 2);

                BarangDatangItem::create([
                    'bardat_id' => $bardat->id,
                    'po_item_id' => $item->id,
                    'qty_diterima' => $qty,
                    'selisih' => $selisih,
                    'keterangan_selisih' => $row['keterangan_selisih'] ?? null,
                ]);

                if ($qty > 0) {
                    $this->stok->masuk(
                        $item->produk_id,
                        $targetGudangId,
                        $qty,
                        'purchase_order',
                        $purchaseOrder->id,
                        "Barang Datang {$purchaseOrder->no_po}",
                        \Illuminate\Support\Carbon::parse($data['tanggal_terima']),
                    );

                    if ((float) $item->hpp_per_satuan > 0) {
                        $item->produk()->update(['harga_hpp' => (float) $item->hpp_per_satuan]);
                    }
                }
            }

            $updateData = ['status' => 'selesai'];
            if (! $purchaseOrder->gudang_id) {
                $updateData['gudang_id'] = $targetGudangId;
            }
            $purchaseOrder->update($updateData);
        });

        return back()->with('success', 'Barang datang dicatat & stok bertambah sesuai lokasi PO.');
    }

    public function pay(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.pay');

        $data = $request->validate([
            'skema' => ['required', Rule::in(['cash', 'tempo', 'termin', 'pelunasan'])],
            'tanggal_bayar' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'gt:0'],
            'termin_id' => ['nullable', 'exists:purchase_order_termins,id'],
        ]);

        $this->paymentService->recordPayment($purchaseOrder, $data);

        return back()->with('success', 'Pembayaran dicatat.');
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.cancel');
        $this->ensureStatus($purchaseOrder, ['draft', 'diajukan', 'disetujui', 'dikirim_ke_gudang']);
        $purchaseOrder->update(['status' => 'dibatalkan']);

        return back()->with('success', 'PO dibatalkan.');
    }

    private function ensureStatus(PurchaseOrder $po, array $allowed): void
    {
        abort_unless(in_array($po->status, $allowed, true), 422, 'Transisi status PO tidak valid.');
    }

    private function nextNoPo(): string
    {
        $year = now()->year;
        $count = PurchaseOrder::whereYear('tanggal', $year)->count() + 1;

        return sprintf('PO-%d-%04d', $year, $count);
    }

    private function nextNoInvoice(): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $count = PurchaseOrder::whereYear('tanggal', now()->year)->whereMonth('tanggal', now()->month)->count() + 1;

        return sprintf('INV/PO/%s%s/%04d', $year, $month, $count);
    }
}
