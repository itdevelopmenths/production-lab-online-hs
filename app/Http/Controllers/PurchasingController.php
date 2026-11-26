<?php

namespace App\Http\Controllers;

use App\Models\BarangDatang;
use App\Models\BarangDatangItem;
use App\Models\Gudang;
use App\Models\PoLeadTime;
use App\Models\PoRevisi;
use App\Models\PoStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierLtStageSummary;
use App\Models\SupplierLtSummary;
use App\Models\SupplierProdukLtStageSummary;
use App\Models\SupplierProdukLtSummary;
use App\Models\Uom;
use App\Services\Authorization\LocationScopeService;
use App\Services\Purchasing\PurchasingCostCalculator;
use App\Services\Purchasing\PurchasingPaymentService;
use App\Services\Purchasing\PurchasingStageService;
use App\Services\Purchasing\SupplierLeadTimeService;
use App\Services\StokService;
use App\Support\UiTableHelper;
use App\Traits\ResolvesGudangOnlyUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class PurchasingController extends Controller
{
    use ResolvesGudangOnlyUser;

    public function __construct(
        private readonly StokService $stok,
        private readonly PurchasingCostCalculator $calculator,
        private readonly PurchasingPaymentService $paymentService
    ) {}

    public function index()
    {
        $this->authorize('purchasing.view');
        $user = auth()->user();
        $canSeePrice = $user->can('purchasing.price.view');
        $canSeeApprovalTab = $user->hasAnyRole(['manager', 'purchasing', 'admin_holding', 'super_admin']) || $user->can('purchasing.approve');

        $pendingPoCount = PurchaseOrder::whereNotIn('status', ['selesai', 'dibatalkan'])
            ->where(function ($q) {
                $q->where('status', 'diajukan')
                    ->orWhere('stage_aktif', PoStage::STAGE_2);
            })
            ->count();

        $pendingRevisis = $canSeeApprovalTab
            ? PoRevisi::with(['purchaseOrder.supplier', 'requester:id,name'])
                ->where('status', PoRevisi::STATUS_MENUNGGU)
                ->whereHas('purchaseOrder', fn ($q) => $q->whereNotIn('status', ['selesai', 'dibatalkan']))
                ->latest()
                ->get()
            : collect();

        $pendingApprovalCount = $canSeeApprovalTab
            ? ($pendingPoCount + $pendingRevisis->count())
            : 0;

        return view('purchasing.index', compact('canSeePrice', 'pendingApprovalCount', 'canSeeApprovalTab', 'pendingRevisis'));
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('purchasing.view');
        $user = auth()->user();
        $canSeePrice = $user->can('purchasing.price.view');
        $canEditUser = $user->can('purchasing.edit');

        $query = PurchaseOrder::query()
            ->select('purchase_orders.*')
            ->leftJoin('gudang', 'purchase_orders.gudang_id', '=', 'gudang.id')
            ->leftJoin('po_lead_time', 'purchase_orders.id', '=', 'po_lead_time.po_id')
            ->leftJoin('supplier_lt_summary', 'purchase_orders.supplier_id', '=', 'supplier_lt_summary.supplier_id')
            ->addSelect([
                'gudang.nama as gudang_nama',
                'po_lead_time.total_lt_menit as completed_lt_menit',
                'po_lead_time.total_lt_hari as completed_lt_hari',
                'supplier_lt_summary.jumlah_transaksi as supplier_lt_count',
                'supplier_lt_summary.total_lt_menit as supplier_lt_total_menit',
                'supplier_lt_summary.average_lt_hari as supplier_lt_avg_hari',
            ])
            ->with([
                'supplier:id,nama,kategori',
                'supplier.stageSummaries',
                'barangDatang:id,po_id,tanggal_terima',
                'stages',
                'revisis:id,po_id,status',
            ])
            ->withSum('items as total_nilai', 'harga_total')
            ->withSum('payments as total_dibayar', 'nominal');

        if ($canSeePrice && $request->filled('status_bayar')) {
            $this->applyStatusBayarFilter($query, (string) $request->status_bayar);
        }

        if ($request->boolean('approval_queue')) {
            $query->whereNotIn('purchase_orders.status', ['selesai', 'dibatalkan'])
                ->where(function ($q) {
                    $q->where('purchase_orders.status', 'diajukan')
                        ->orWhere('purchase_orders.stage_aktif', PoStage::STAGE_2);
                });
        } elseif ($request->filled('status_po')) {
            $query->where('purchase_orders.status', $request->status_po);
        }

        if ($request->filled('stage_aktif')) {
            $query->where('purchase_orders.stage_aktif', $request->stage_aktif);
        }

        if ($request->filled('kategori_supplier')) {
            $query->whereHas('supplier', fn ($q) => $q->where('kategori', $request->kategori_supplier));
        }

        // Gudang hanya melihat PO legacy, status legacy gudang, atau yang sudah mencapai Tahap 5 (PO Terbit).
        if ($this->isGudangOnly($user)) {
            $query->visibleToGudang();
        }

        $query->latest('purchase_orders.created_at');

        return DataTables::eloquent($query)
            ->filterColumn('supplier_nama', function ($q, $keyword) {
                $kw = '%'.strtolower(trim($keyword)).'%';
                $q->whereHas('supplier', function ($sq) use ($kw) {
                    $sq->whereRaw('LOWER(nama) LIKE ?', [$kw]);
                });
            })
            ->filterColumn('no_invoice', function ($q, $keyword) {
                $kw = '%'.strtolower(trim($keyword)).'%';
                $q->where(function ($sub) use ($kw) {
                    $sub->whereRaw('LOWER(purchase_orders.no_invoice) LIKE ?', [$kw])
                        ->orWhereRaw('LOWER(purchase_orders.no_po) LIKE ?', [$kw]);
                });
            })
            ->orderColumn('tanggal', fn ($q, $order) => $q->orderBy('purchase_orders.tanggal', $order)->orderBy('purchase_orders.created_at', $order))
            ->addColumn('no_invoice', fn ($po) => $po->no_invoice ? $po->no_invoice : $po->no_po)
            ->addColumn('supplier_nama', fn ($po) => $po->supplier?->nama ?? '—')
            ->addColumn('gudang_nama', fn ($po) => $po->gudang_nama ?? '—')
            ->editColumn('tanggal', fn ($po) => $po->tanggal?->format('d/m/Y') ?? '—')
            ->addColumn('eta', fn ($po) => $po->eta?->format('d/m/Y') ?? '—')
            ->addColumn('tgl_aktual_tiba', function ($po) {
                $lastBardat = $po->barangDatang->sortByDesc('tanggal_terima')->first();

                return $lastBardat?->tanggal_terima?->format('d/m/Y') ?? '—';
            })
            ->editColumn('status', fn ($po) => ucwords(str_replace('_', ' ', $po->status)))
            ->addColumn('stage_label', function ($po) {
                if ($po->status === 'dibatalkan') {
                    return 'Dibatalkan';
                }
                if (! $po->stage_aktif) {
                    return $po->status === 'selesai' ? 'Selesai' : ucwords(str_replace('_', ' ', $po->status));
                }

                return PoStage::STAGE_LABELS[$po->stage_aktif] ?? ucwords(str_replace('_', ' ', $po->stage_aktif));
            })
            ->addColumn('stage_badge_state', function ($po) {
                if ($po->status === 'selesai') {
                    return 'selesai';
                }
                if ($po->status === 'dibatalkan') {
                    return 'dibatalkan';
                }
                if (! $po->stage_aktif) {
                    return 'belum_mulai';
                }

                $currentStage = $po->stages->where('stage', $po->stage_aktif)->sortByDesc('putaran')->first();
                if (! $currentStage) {
                    return 'berjalan';
                }

                if ($currentStage->status === 'selesai') {
                    return 'selesai';
                }
                if ($currentStage->status === 'dikembalikan') {
                    return 'dikembalikan';
                }

                if ($currentStage->waktu_mulai) {
                    $benchmark = $po->supplier?->stageSummaries?->firstWhere('stage', $po->stage_aktif);
                    if ($benchmark && $benchmark->jumlah_transaksi > 0 && $benchmark->total_menit > 0) {
                        $avgMinutes = $benchmark->total_menit / $benchmark->jumlah_transaksi;
                        $elapsedMinutes = $currentStage->waktu_mulai->diffInMinutes(now());
                        if ($elapsedMinutes > $avgMinutes) {
                            return 'terlambat';
                        }
                    }
                }

                return 'berjalan';
            })
            ->addColumn('has_pending_revisi', function ($po) {
                return ! $po->isFinal() && $po->revisis->where('status', PoRevisi::STATUS_MENUNGGU)->isNotEmpty();
            })
            ->addColumn('sla', fn ($po) => $this->resolvePoSlaLabel($po))
            ->addColumn('status_pembayaran', fn ($po) => $canSeePrice ? ucwords(str_replace('_', ' ', $this->resolveEffectivePaymentStatus($po))) : '—')
            ->addColumn('total_nilai', fn ($po) => $canSeePrice ? number_format((float) $po->totalNilai(), 0, ',', '.') : '—')
            ->addColumn('sisa', fn ($po) => $canSeePrice ? number_format((float) $po->sisaTagihan(), 0, ',', '.') : '—')
            ->addColumn('action', function ($po) use ($canEditUser) {
                $buttons = [
                    UiTableHelper::actionLink(route('purchasing.show', $po), 'Detail', 'eye', 'default'),
                ];

                $canEdit = $canEditUser && in_array($po->status, ['draft', 'diajukan', 'disetujui'], true) && $po->barangDatang->isEmpty();
                if ($canEdit) {
                    $buttons[] = UiTableHelper::actionLink(route('purchasing.edit', $po), 'Edit', 'pencil', 'default');
                    $buttons[] = UiTableHelper::actionButton(
                        "openQuickDateModal('".e($po->id)."', '".e($po->no_po)."', '".e($po->tanggal?->format('Y-m-d') ?? '')."', '".e($po->eta?->format('Y-m-d') ?? '')."')",
                        'Edit Tanggal PO & ETA',
                        'calendar',
                        'warning'
                    );
                }

                return UiTableHelper::actionGroup($buttons);
            })
            ->rawColumns(['action'])
            ->toJson();
    }

    public function dataAp(Request $request): JsonResponse
    {
        $this->authorize('purchasing.price.view');

        $query = PurchaseOrder::query()->select('purchase_orders.*')
            ->with(['supplier:id,nama', 'termins'])
            ->withSum('items as total_nilai', 'harga_total')
            ->withSum('payments as total_dibayar', 'nominal')
            ->withExists('stages as has_stages')
            ->withExists(['revisis as has_pending_revisi' => fn ($q) => $q->where('status', PoRevisi::STATUS_MENUNGGU)]);

        if ($request->filled('status_bayar')) {
            $this->applyStatusBayarFilter($query, (string) $request->status_bayar);
        }

        if ($request->filled('skema_bayar')) {
            $query->where('purchase_orders.skema_bayar', $request->skema_bayar);
        }

        $query->latest('purchase_orders.created_at');

        return DataTables::eloquent($query)
            ->filterColumn('supplier_nama', function ($q, $keyword) {
                $kw = '%'.strtolower(trim($keyword)).'%';
                $q->whereHas('supplier', function ($sq) use ($kw) {
                    $sq->whereRaw('LOWER(nama) LIKE ?', [$kw]);
                });
            })
            ->filterColumn('no_invoice', function ($q, $keyword) {
                $kw = '%'.strtolower(trim($keyword)).'%';
                $q->where(function ($sub) use ($kw) {
                    $sub->whereRaw('LOWER(purchase_orders.no_invoice) LIKE ?', [$kw])
                        ->orWhereRaw('LOWER(purchase_orders.no_po) LIKE ?', [$kw]);
                });
            })
            ->orderColumn('tanggal', fn ($q, $order) => $q->orderBy('purchase_orders.tanggal', $order)->orderBy('purchase_orders.created_at', $order))
            ->addColumn('no_invoice', fn ($po) => $po->no_invoice ? $po->no_invoice : $po->no_po)
            ->addColumn('supplier_nama', fn ($po) => $po->supplier?->nama ?? '—')
            ->editColumn('tanggal', fn ($po) => $po->tanggal?->format('d/m/Y') ?? '—')
            ->addColumn('skema_bayar', fn ($po) => ucfirst($po->skema_bayar ?? 'cash'))
            ->addColumn('total_nilai', fn ($po) => number_format((float) $po->totalNilai(), 0, ',', '.'))
            ->addColumn('total_dibayar', fn ($po) => number_format((float) $po->totalDibayar(), 0, ',', '.'))
            ->addColumn('sisa', fn ($po) => number_format((float) $po->sisaTagihan(), 0, ',', '.'))
            ->addColumn('jatuh_tempo_terdekat', function ($po) {
                if ($po->status === 'dibatalkan') {
                    return '—';
                }
                if ($po->skema_bayar === 'tempo' && $po->tanggal_tempo) {
                    return $po->tanggal_tempo->format('d/m/Y');
                }
                $nextTermin = $po->termins->where('status', '!=', 'lunas')->sortBy('tanggal_tempo')->first();

                return $nextTermin ? $nextTermin->tanggal_tempo->format('d/m/Y') : ($po->eta ? $po->eta->format('d/m/Y') : '—');
            })
            ->addColumn('status_pembayaran', fn ($po) => $this->resolveEffectivePaymentStatus($po))
            ->addColumn('action', function ($po) {
                $showUrl = route('purchasing.show', $po);
                $buttons = [
                    UiTableHelper::actionLink($showUrl, 'Detail', 'eye', 'default'),
                ];
                if (! $po->isLunas() && ! $po->isCancelled() && ! $po->hasPendingRevision() && $po->isApprovedForPayment()) {
                    $buttons[] = UiTableHelper::actionLink($showUrl.'#form-pembayaran', 'Bayar', 'check', 'success');
                }

                return UiTableHelper::actionGroup($buttons);
            })
            ->rawColumns(['action'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('purchasing.create');
        $suppliers = Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori', 'termin_default']);
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
            'catatan' => ['nullable', 'string', 'max:1000'],
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
        ], [
            'items.*.produk_id.distinct' => 'Bahan baku tidak boleh dipilih lebih dari satu kali dalam satu Purchase Order.',
            'items.*.produk_id.required' => 'Bahan baku wajib dipilih untuk setiap baris.',
            'items.*.produk_id.exists' => 'Bahan baku yang dipilih tidak valid atau tidak terdaftar.',
        ]);

        $supplierId = (int) $data['supplier_id'];
        $itemProdukIds = collect($data['items'])->pluck('produk_id')->unique()->all();

        $invalidProdukNames = Produk::whereIn('id', $itemProdukIds)
            ->where(function ($query) use ($supplierId) {
                $query->whereDoesntHave('suppliers', function ($q) use ($supplierId) {
                    $q->where('supplier.id', $supplierId)
                        ->where('produk_supplier.status', 'aktif');
                })
                    ->where(function ($q) use ($supplierId) {
                        $q->whereNull('supplier_id')
                            ->orWhere('supplier_id', '!=', $supplierId);
                    });
            })
            ->pluck('nama');

        if ($invalidProdukNames->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Item produk berikut tidak terdaftar aktif pada supplier yang dipilih: '.$invalidProdukNames->implode(', ').'.',
            ]);
        }

        $totals = $this->calculator->calculatePoTotals(
            $data['items'],
            (float) ($data['diskon_total'] ?? 0),
            (float) ($data['ppn_nominal'] ?? 0),
            (float) ($data['ongkos_kirim'] ?? 0),
            (float) ($data['adjustment'] ?? 0)
        );

        if (($totals['raw_grand_total'] ?? $totals['grand_total']) < 0) {
            throw ValidationException::withMessages([
                'items' => 'Total bersih Purchase Order tidak boleh bernilai negatif (diskon/potongan melebihi nilai barang).',
            ]);
        }

        if (! empty($data['termins']) && ($data['skema_bayar'] ?? '') === 'termin') {
            $this->paymentService->assertTerminsMatchGrandTotal($data['termins'], (float) $totals['grand_total']);
        }

        $po = DB::transaction(function () use ($data, $totals) {
            $po = PurchaseOrder::create([
                'no_po' => $this->nextNoPo(),
                'no_invoice' => ! empty($data['no_invoice']) ? $data['no_invoice'] : $this->nextNoInvoice(),
                'supplier_id' => $data['supplier_id'],
                'gudang_id' => $data['gudang_id'] ?? null,
                'tanggal' => $data['tanggal'],
                'eta' => $data['eta'] ?? null,
                'catatan' => $data['catatan'] ?? null,
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

            app(PurchasingStageService::class)->initializePoStages($po, now(), auth()->user());

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

        if (! $this->isPoEditablePreApproval($purchaseOrder)) {
            return redirect()->route('purchasing.show', $purchaseOrder)
                ->with('error', 'PO sudah masuk tahap approval / disetujui sehingga terkunci dan tidak dapat diubah. Batalkan PO dan buat baru jika ada kesalahan.');
        }

        if ($purchaseOrder->hasPendingRevision()) {
            return redirect()->route('purchasing.show', $purchaseOrder)
                ->with('error', 'PO sedang memiliki usulan revisi komitmen yang menunggu keputusan Manager.');
        }

        $purchaseOrder->load(['items.produk', 'termins']);
        $suppliers = Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori', 'termin_default']);
        $gudang = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'tipe']);
        $produk = Produk::bahan()->active()->orderBy('nama')->get(['id', 'sku', 'nama', 'nama_produk', 'satuan', 'faktor_konversi']);
        $uomList = Uom::active()->orderBy('nama')->get(['id', 'kode', 'nama', 'kategori', 'satuan_dasar', 'faktor_konversi']);
        $productIds = $purchaseOrder->items->pluck('produk_id')->filter()->unique()->values()->all();
        $hppMap = app(StokService::class)->resolveHppMap($productIds);

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

        if (! $this->isPoEditablePreApproval($purchaseOrder)) {
            throw ValidationException::withMessages([
                'stage_aktif' => 'PO sudah masuk tahap approval / disetujui sehingga terkunci dan tidak dapat diubah. Batalkan PO dan buat baru jika ada kesalahan.',
            ]);
        }

        if ($purchaseOrder->hasPendingRevision()) {
            throw ValidationException::withMessages([
                'revisi' => 'PO sedang memiliki usulan revisi komitmen yang menunggu keputusan Manager.',
            ]);
        }

        $data = $request->validate([
            'no_invoice' => ['nullable', 'string', 'max:50'],
            'supplier_id' => ['required', 'exists:supplier,id'],
            'gudang_id' => ['nullable', 'exists:gudang,id'],
            'tanggal' => ['required', 'date'],
            'eta' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:1000'],
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
        ], [
            'items.*.produk_id.distinct' => 'Bahan baku tidak boleh dipilih lebih dari satu kali dalam satu Purchase Order.',
            'items.*.produk_id.required' => 'Bahan baku wajib dipilih untuk setiap baris.',
            'items.*.produk_id.exists' => 'Bahan baku yang dipilih tidak valid atau tidak terdaftar.',
        ]);

        $supplierId = (int) $data['supplier_id'];
        $itemProdukIds = collect($data['items'])->pluck('produk_id')->unique()->all();

        $invalidProdukNames = Produk::whereIn('id', $itemProdukIds)
            ->where(function ($query) use ($supplierId) {
                $query->whereDoesntHave('suppliers', function ($q) use ($supplierId) {
                    $q->where('supplier.id', $supplierId)
                        ->where('produk_supplier.status', 'aktif');
                })
                    ->where(function ($q) use ($supplierId) {
                        $q->whereNull('supplier_id')
                            ->orWhere('supplier_id', '!=', $supplierId);
                    });
            })
            ->pluck('nama');

        if ($invalidProdukNames->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Item produk berikut tidak terdaftar aktif pada supplier yang dipilih: '.$invalidProdukNames->implode(', ').'.',
            ]);
        }

        $totals = $this->calculator->calculatePoTotals(
            $data['items'],
            (float) ($data['diskon_total'] ?? 0),
            (float) ($data['ppn_nominal'] ?? 0),
            (float) ($data['ongkos_kirim'] ?? 0),
            (float) ($data['adjustment'] ?? 0)
        );

        if (($totals['raw_grand_total'] ?? $totals['grand_total']) < 0) {
            throw ValidationException::withMessages([
                'items' => 'Total bersih Purchase Order tidak boleh bernilai negatif (diskon/potongan melebihi nilai barang).',
            ]);
        }

        $totalDibayar = $purchaseOrder->totalDibayar();
        if ($totalDibayar > 0 && $totals['grand_total'] < $totalDibayar) {
            throw ValidationException::withMessages([
                'items' => 'Grand total baru (Rp '.number_format($totals['grand_total'], 0, ',', '.').') tidak boleh lebih kecil dari total yang sudah dibayar (Rp '.number_format($totalDibayar, 0, ',', '.').').',
            ]);
        }

        if (($data['skema_bayar'] ?? '') === 'termin') {
            // Termin baru dikirim → validasi terhadap grand total baru. Jika tidak
            // dikirim, jadwal lama dipertahankan sehingga tetap wajib cocok.
            $terminsToCheck = ! empty($data['termins'])
                ? $data['termins']
                : $purchaseOrder->termins()->get(['nominal_tagihan'])->toArray();

            if (! empty($terminsToCheck)) {
                $this->paymentService->assertTerminsMatchGrandTotal($terminsToCheck, (float) $totals['grand_total']);
            }
        }

        DB::transaction(function () use ($purchaseOrder, $data, $totals) {
            $purchaseOrder->update([
                'no_invoice' => ! empty($data['no_invoice']) ? $data['no_invoice'] : $purchaseOrder->no_invoice,
                'supplier_id' => $data['supplier_id'],
                'gudang_id' => $data['gudang_id'] ?? null,
                'tanggal' => $data['tanggal'],
                'eta' => $data['eta'] ?? null,
                'catatan' => $data['catatan'] ?? null,
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
        abort_if(
            $this->isGudangOnly(auth()->user()) && ! $purchaseOrder->isVisibleToGudang(),
            403,
            'Anda tidak memiliki akses ke PO ini pada tahap saat ini.'
        );
        $purchaseOrder->load([
            'supplier',
            'gudang',
            'items.produk:id,sku,nama,satuan,harga_hpp',
            'items.bardatItems',
            'payments',
            'termins',
            'stages.picUser:id,name',
            'stages.recordedBy:id,name',
            'pengiriman',
            'revisis.requester:id,name',
            'revisis.decider:id,name',
            'leadTime.validator:id,name',
            'barangDatang.creator:id,name',
            'barangDatang.poster:id,name',
            'barangDatang.items.poItem.produk:id,sku,nama,satuan',
            'creator:id,name',
        ]);

        if (auth()->user()->canany(['purchasing.audit', 'audit.view'])) {
            $purchaseOrder->load('logPerubahan.changer:id,name');
        }

        // Auto-refresh status pembayaran jika ada perubahan status (misal lewat jatuh tempo atau legacy PO batal)
        if ($purchaseOrder->isCancelled() || ! $purchaseOrder->isLunas()) {
            $prevStatus = $purchaseOrder->status_pembayaran;
            $purchaseOrder->refreshStatusPembayaran();
            if ($purchaseOrder->status_pembayaran !== $prevStatus) {
                $purchaseOrder->save();
            }
        }

        $gudang = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'tipe']);
        $canSeePrice = auth()->user()->can('purchasing.price.view');
        $stageBenchmarks = SupplierLtStageSummary::where('supplier_id', $purchaseOrder->supplier_id)->get()->keyBy('stage');
        $uomList = Uom::active()->orderBy('nama')->get(['id', 'kode', 'nama', 'kategori', 'satuan_dasar', 'faktor_konversi']);

        return view('purchasing.show', compact('purchaseOrder', 'gudang', 'canSeePrice', 'stageBenchmarks', 'uomList'));
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

    /**
     * Ubah No. Invoice & ETA lewat modal di detail PO, sejak keluar dari draft sampai final.
     * Perubahan tercatat otomatis di audit PO (AuditableOperation).
     */
    public function updateInvoiceEta(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('purchasing.edit_invoice_eta');

        if (! $purchaseOrder->canEditInvoiceEta()) {
            throw ValidationException::withMessages([
                'status' => $purchaseOrder->isFinal()
                    ? 'PO sudah final sehingga No. Invoice dan ETA tidak dapat diubah.'
                    : 'PO masih draft. Ubah No. Invoice dan ETA lewat Edit PO.',
            ]);
        }

        $data = $request->validate([
            'no_invoice' => ['nullable', 'string', 'max:50'],
            'eta' => ['nullable', 'date', 'after_or_equal:'.$purchaseOrder->tanggal->toDateString()],
        ], [
            'no_invoice.string' => 'No. Invoice harus berupa teks.',
            'no_invoice.max' => 'No. Invoice maksimal 50 karakter.',
            'eta.date' => 'ETA harus berupa tanggal yang valid.',
            'eta.after_or_equal' => 'ETA tidak boleh sebelum tanggal PO ('.$purchaseOrder->tanggal->format('d/m/Y').').',
        ]);

        $purchaseOrder->update($data);

        return redirect()
            ->route('purchasing.show', $purchaseOrder)
            ->with('success', 'No. Invoice & ETA PO berhasil diperbarui.');
    }

    public function submit(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.submit');
        $this->ensureLegacyFlow($purchaseOrder);
        $this->ensureStatus($purchaseOrder, ['draft']);
        $this->ensureNoPendingRevision($purchaseOrder);
        $purchaseOrder->update(['status' => 'diajukan']);

        $purchaseOrder->recordAudit(
            event: 'submitted',
            actionTitle: "Pengajuan PO {$purchaseOrder->no_po} ke Manager untuk persetujuan",
            customChanges: ['status' => ['draft', 'diajukan']],
            metadata: [
                'total_item' => $purchaseOrder->items()->count(),
                'grand_total' => (float) $purchaseOrder->grand_total,
            ]
        );

        return back()->with('success', 'PO diajukan ke Manager.');
    }

    public function approve(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.approve');
        $this->ensureLegacyFlow($purchaseOrder);
        $this->ensureStatus($purchaseOrder, ['diajukan']);
        $this->ensureNoPendingRevision($purchaseOrder);
        $purchaseOrder->update(['status' => 'dikirim_ke_gudang']);

        $purchaseOrder->recordAudit(
            event: 'approved',
            actionTitle: "Persetujuan PO {$purchaseOrder->no_po} oleh Manager & dikirim ke Gudang",
            customChanges: ['status' => ['diajukan', 'dikirim_ke_gudang']],
            metadata: [
                'approved_by' => auth()->user()->name,
                'grand_total' => (float) $purchaseOrder->grand_total,
            ]
        );

        return back()->with('success', 'PO disetujui & dikirim ke Gudang.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.receive');
        $this->ensureLegacyFlow($purchaseOrder);
        $this->ensureStatus($purchaseOrder, ['dikirim_ke_gudang']);
        $this->ensureNoPendingRevision($purchaseOrder);

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

        // Batasi penerimaan ke gudang yang boleh diakses user (gudang efektif setelah fallback).
        abort_unless(
            app(LocationScopeService::class)->canAccessWarehouse(auth()->user(), $targetGudangId ? (int) $targetGudangId : null),
            403,
            $purchaseOrder->gudang_id
                ? 'Anda tidak memiliki akses ke gudang tujuan PO ini.'
                : 'PO belum memiliki gudang tujuan; minta Purchasing melengkapi.'
        );

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
                        Carbon::parse($data['tanggal_terima']),
                    );
                }
            }

            // Master HPP = average HPP global seluruh PO yang sudah diterima
            $this->stok->syncHargaHppAverage($purchaseOrder->items()->pluck('produk_id')->all());

            $updateData = ['status' => 'selesai'];
            if (! $purchaseOrder->gudang_id) {
                $updateData['gudang_id'] = $targetGudangId;
            }
            $purchaseOrder->update($updateData);

            $targetGudang = Gudang::find($targetGudangId);
            $purchaseOrder->recordAudit(
                event: 'received',
                actionTitle: "Penerimaan fisik barang PO {$purchaseOrder->no_po} di ".($targetGudang?->nama ?? 'Gudang').' (Kondisi: '.ucwords(str_replace('_', ' ', $data['kondisi'])).')',
                customChanges: ['status' => ['dikirim_ke_gudang', 'selesai']],
                metadata: [
                    'tanggal_terima' => $data['tanggal_terima'],
                    'kondisi' => $data['kondisi'],
                    'total_item' => count($data['items']),
                ]
            );
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
            'termin_id' => [
                'nullable',
                Rule::exists('purchase_order_termins', 'id')->where(fn ($q) => $q->where('po_id', $purchaseOrder->id)),
            ],
            'tanggal_tempo_baru' => ['nullable', 'date'],
        ]);

        $oldStatusBayar = $purchaseOrder->status_pembayaran;
        $this->paymentService->recordPayment($purchaseOrder, $data);
        $freshPo = $purchaseOrder->fresh();

        $customChanges = ['status_pembayaran' => [$oldStatusBayar, $freshPo?->status_pembayaran ?? $oldStatusBayar]];

        // Jika ada adjustment tanggal jatuh tempo pada skema tempo
        if (! empty($data['tanggal_tempo_baru']) && $purchaseOrder->skema_bayar === 'tempo') {
            $oldTempo = $purchaseOrder->tanggal_tempo?->format('Y-m-d');
            $purchaseOrder->update(['tanggal_tempo' => $data['tanggal_tempo_baru']]);
            $customChanges['tanggal_tempo'] = [$oldTempo, $data['tanggal_tempo_baru']];
        }

        $purchaseOrder->recordAudit(
            event: 'paid',
            actionTitle: 'Pencatatan pembayaran '.ucfirst($data['skema']).' senilai Rp '.number_format($data['nominal'], 0, ',', '.')." untuk PO {$purchaseOrder->no_po}",
            customChanges: $customChanges,
            metadata: [
                'skema' => $data['skema'],
                'nominal' => (float) $data['nominal'],
                'tanggal_bayar' => $data['tanggal_bayar'],
                'tanggal_tempo_baru' => $data['tanggal_tempo_baru'] ?? null,
            ]
        );

        return back()->with('success', 'Pembayaran dicatat.');
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.cancel');
        $this->ensureStatus($purchaseOrder, ['draft', 'berjalan', 'diajukan', 'disetujui', 'diproses_supplier', 'dikirim_ke_gudang']);

        $data = $request->validate([
            'alasan' => ['nullable', 'string', 'max:500'],
        ], [
            'alasan.max' => 'Alasan pembatalan maksimal 500 karakter.',
        ]);

        $alasan = ! empty($data['alasan']) ? trim($data['alasan']) : 'Dibatalkan oleh pengguna';

        $oldStatus = $purchaseOrder->status;
        $oldStatusPembayaran = $purchaseOrder->status_pembayaran;
        $oldCatatan = $purchaseOrder->catatan;
        $alasanEntry = "[Alasan Pembatalan: {$alasan}]";
        $newCatatan = $oldCatatan ? ($oldCatatan."\n".$alasanEntry) : $alasanEntry;

        app(PurchasingStageService::class)->cancelPo(
            $purchaseOrder,
            auth()->user(),
            $alasan,
            ['catatan' => $newCatatan]
        );

        $purchaseOrder->recordAudit(
            event: 'cancelled',
            actionTitle: "Pembatalan Dokumen PO {$purchaseOrder->no_po}: {$alasan}",
            customChanges: [
                'status' => [$oldStatus, 'dibatalkan'],
                'status_pembayaran' => [$oldStatusPembayaran, 'batal'],
                'catatan' => [$oldCatatan, $newCatatan],
            ],
            metadata: [
                'alasan_pembatalan' => $alasan,
            ]
        );

        return back()->with('success', 'Purchase Order berhasil dibatalkan.');
    }

    public function leadTimeData(Request $request): JsonResponse
    {
        $this->authorize('purchasing.view');

        $suppliers = SupplierLtSummary::with('supplier:id,nama,kategori')
            ->get()
            ->map(function ($s) {
                $avgMenit = $s->jumlah_transaksi > 0 ? ($s->total_lt_menit / $s->jumlah_transaksi) : 0;

                return [
                    'supplier_id' => $s->supplier_id,
                    'supplier_nama' => $s->supplier?->nama ?? '—',
                    'kategori' => $s->supplier?->kategori ?? '—',
                    'jumlah_transaksi' => (int) $s->jumlah_transaksi,
                    'total_lt_menit' => (int) $s->total_lt_menit,
                    'average_lt_hari' => (float) $s->average_lt_hari,
                    'max_lt_hari' => (float) $s->max_lt_hari,
                    'average_lt_jam' => SupplierLeadTimeService::minutesToHours($avgMenit),
                    'max_lt_jam' => (float) round($s->max_lt_hari * 24, 2, PHP_ROUND_HALF_UP),
                ];
            });

        $supplierStages = SupplierLtStageSummary::with('supplier:id,nama')
            ->get()
            ->map(function ($st) {
                $avgMenit = $st->jumlah_transaksi > 0 ? ($st->total_menit / $st->jumlah_transaksi) : 0;

                return [
                    'supplier_id' => $st->supplier_id,
                    'supplier_nama' => $st->supplier?->nama ?? '—',
                    'stage' => $st->stage,
                    'stage_label' => PoStage::STAGE_LABELS[$st->stage] ?? $st->stage,
                    'jumlah_transaksi' => (int) $st->jumlah_transaksi,
                    'average_hari' => (float) $st->average_hari,
                    'max_hari' => (float) $st->max_hari,
                    'average_jam' => SupplierLeadTimeService::minutesToHours($avgMenit),
                ];
            });

        $supplierProduk = SupplierProdukLtSummary::with(['supplier:id,nama', 'produk:id,sku,nama,satuan'])
            ->get()
            ->map(function ($sp) {
                $avgMenit = $sp->jumlah_transaksi > 0 ? ($sp->total_lt_menit / $sp->jumlah_transaksi) : 0;

                return [
                    'supplier_id' => $sp->supplier_id,
                    'supplier_nama' => $sp->supplier?->nama ?? '—',
                    'produk_id' => $sp->produk_id,
                    'sku' => $sp->produk?->sku ?? '—',
                    'nama_produk' => $sp->produk?->nama ?? '—',
                    'satuan' => $sp->produk?->satuan ?? '—',
                    'jumlah_transaksi' => (int) $sp->jumlah_transaksi,
                    'average_lt_hari' => (float) $sp->average_lt_hari,
                    'max_lt_hari' => (float) $sp->max_lt_hari,
                    'average_lt_jam' => SupplierLeadTimeService::minutesToHours($avgMenit),
                ];
            });

        $supplierProdukStages = SupplierProdukLtStageSummary::with(['supplier:id,nama', 'produk:id,sku,nama'])
            ->get()
            ->map(function ($sps) {
                return [
                    'supplier_id' => $sps->supplier_id,
                    'supplier_nama' => $sps->supplier?->nama ?? '—',
                    'produk_id' => $sps->produk_id,
                    'nama_produk' => $sps->produk?->nama ?? '—',
                    'stage' => $sps->stage,
                    'stage_label' => PoStage::STAGE_LABELS[$sps->stage] ?? $sps->stage,
                    'jumlah_transaksi' => (int) $sps->jumlah_transaksi,
                    'average_hari' => (float) $sps->average_hari,
                    'max_hari' => (float) $sps->max_hari,
                ];
            });

        $transaksi = PoLeadTime::with([
            'purchaseOrder:id,no_po,no_invoice,status',
            'supplier:id,nama',
            'validator:id,name',
        ])
            ->latest('waktu_selesai')
            ->limit(100)
            ->get()
            ->map(function ($lt) {
                return [
                    'id' => $lt->id,
                    'po_id' => $lt->po_id,
                    'no_po' => $lt->purchaseOrder?->no_po ?? '—',
                    'no_invoice' => $lt->purchaseOrder?->no_invoice ?? '—',
                    'supplier_id' => $lt->supplier_id,
                    'supplier_nama' => $lt->supplier?->nama ?? '—',
                    'waktu_mulai' => $lt->waktu_mulai?->format('d/m/Y H:i') ?? '—',
                    'waktu_selesai' => $lt->waktu_selesai?->format('d/m/Y H:i') ?? '—',
                    'total_lt_hari' => (float) $lt->total_lt_hari,
                    'total_lt_jam' => SupplierLeadTimeService::minutesToHours($lt->total_lt_menit),
                    'lt_internal_hari' => SupplierLeadTimeService::minutesToDays($lt->lt_internal_menit),
                    'lt_internal_jam' => SupplierLeadTimeService::minutesToHours($lt->lt_internal_menit),
                    'lt_supplier_hari' => SupplierLeadTimeService::minutesToDays($lt->lt_supplier_menit),
                    'lt_supplier_jam' => SupplierLeadTimeService::minutesToHours($lt->lt_supplier_menit),
                    'lt_logistik_hari' => SupplierLeadTimeService::minutesToDays($lt->lt_logistik_menit),
                    'lt_logistik_jam' => SupplierLeadTimeService::minutesToHours($lt->lt_logistik_menit),
                    'is_valid' => (bool) $lt->is_valid,
                    'alasan_tidak_valid' => $lt->alasan_tidak_valid,
                    'validator_name' => $lt->validator?->name,
                ];
            });

        return response()->json([
            'suppliers' => $suppliers,
            'supplier_stages' => $supplierStages,
            'supplier_produk' => $supplierProduk,
            'supplier_produk_stages' => $supplierProdukStages,
            'transaksi' => $transaksi,
        ]);
    }

    private function resolvePoSlaLabel(PurchaseOrder $po): string
    {
        if ($po->status === 'selesai') {
            return $this->resolveCompletedPoSlaLabel($po);
        }

        if (! $po->stage_aktif) {
            return '—';
        }

        $currentStage = $po->stages->where('stage', $po->stage_aktif)->sortByDesc('putaran')->first();
        if (! $currentStage || ! $currentStage->waktu_mulai) {
            return '—';
        }

        if ($po->status === 'dibatalkan') {
            // Durasi dibekukan: dari awal stage sampai waktu pembatalan, tanpa penanda terlambat.
            $endAt = $currentStage->waktu_selesai ?? $po->updated_at ?? now();
            $frozenMinutes = $endAt->greaterThanOrEqualTo($currentStage->waktu_mulai)
                ? (float) $currentStage->waktu_mulai->diffInMinutes($endAt)
                : 0.0;

            return $this->formatSlaDuration($frozenMinutes, SupplierLeadTimeService::minutesToDays($frozenMinutes));
        }

        $elapsedMinutes = (float) $currentStage->waktu_mulai->diffInMinutes(now());
        $elapsedDays = SupplierLeadTimeService::minutesToDays($elapsedMinutes);
        $benchmark = $po->supplier?->stageSummaries?->firstWhere('stage', $po->stage_aktif);

        $avgHari = ($benchmark && $benchmark->jumlah_transaksi > 0) ? (float) $benchmark->average_hari : null;
        $avgMinutes = ($avgHari && $avgHari > 0) ? ($benchmark->total_menit / $benchmark->jumlah_transaksi) : null;

        return $this->formatSlaDuration($elapsedMinutes, $elapsedDays, $avgHari, $avgMinutes);
    }

    private function resolveCompletedPoSlaLabel(PurchaseOrder $po): string
    {
        if ($po->completed_lt_menit !== null || $po->completed_lt_hari !== null) {
            $totalMinutes = (float) ($po->completed_lt_menit ?? ((float) $po->completed_lt_hari * 1440));
            $totalDays = $po->completed_lt_hari !== null
                ? (float) $po->completed_lt_hari
                : SupplierLeadTimeService::minutesToDays($totalMinutes);
        } elseif ($po->stages->isNotEmpty()) {
            $stageMinutes = (int) $po->stages->sum('durasi_menit');
            if ($stageMinutes > 0) {
                $totalMinutes = (float) $stageMinutes;
            } else {
                $firstStart = $po->stages->whereNotNull('waktu_mulai')->sortBy('waktu_mulai')->first()?->waktu_mulai;
                $lastEnd = $po->stages->whereNotNull('waktu_selesai')->sortByDesc('waktu_selesai')->first()?->waktu_selesai
                    ?? $po->updated_at;
                $totalMinutes = ($firstStart && $lastEnd && $lastEnd->greaterThanOrEqualTo($firstStart))
                    ? (float) $firstStart->diffInMinutes($lastEnd)
                    : 0.0;
            }
            $totalDays = SupplierLeadTimeService::minutesToDays($totalMinutes);
        } else {
            $lastBardat = $po->barangDatang->sortByDesc('tanggal_terima')->first();
            $startAt = $po->tanggal?->copy()->startOfDay() ?? $po->created_at;
            $endAt = $lastBardat?->tanggal_terima?->copy()->startOfDay() ?? $po->updated_at;

            $totalMinutes = ($startAt && $endAt && $endAt->greaterThanOrEqualTo($startAt))
                ? (float) $startAt->diffInMinutes($endAt)
                : 0.0;
            $totalDays = SupplierLeadTimeService::minutesToDays($totalMinutes);
        }

        $supplierCount = (int) ($po->supplier_lt_count ?? 0);
        $avgHari = ($supplierCount > 0 && (float) ($po->supplier_lt_avg_hari ?? 0) > 0)
            ? (float) $po->supplier_lt_avg_hari
            : null;
        $avgMinutes = $avgHari !== null
            ? ((float) ($po->supplier_lt_total_menit ?? ($avgHari * 1440 * $supplierCount))) / $supplierCount
            : null;

        return $this->formatSlaDuration($totalMinutes, $totalDays, $avgHari, $avgMinutes);
    }

    private function formatSlaDuration(float $elapsedMinutes, float $elapsedDays, ?float $avgHari = null, ?float $avgMinutes = null): string
    {
        if ($avgHari !== null && $avgHari > 0 && $avgMinutes !== null && $avgMinutes > 0) {
            $isLate = $elapsedMinutes > $avgMinutes;

            return ($isLate ? '⚠️ ' : '').$elapsedDays.' hr (Avg: '.$avgHari.' hr)';
        }

        return $elapsedDays.' hr';
    }

    private function applyStatusBayarFilter($query, string $statusBayar): void
    {
        if ($statusBayar === 'batal') {
            $query->where(function ($q) {
                $q->where('purchase_orders.status', 'dibatalkan')
                    ->orWhere('purchase_orders.status_pembayaran', 'batal');
            });

            return;
        }

        $query->where('purchase_orders.status', '!=', 'dibatalkan');

        if ($statusBayar === 'belum_lunas') {
            $query->where(function ($q) {
                $q->where('purchase_orders.status_pembayaran', 'belum_lunas')
                    ->orWhereNull('purchase_orders.status_pembayaran');
            });

            return;
        }

        $query->where('purchase_orders.status_pembayaran', $statusBayar);
    }

    private function resolveEffectivePaymentStatus(PurchaseOrder $po): string
    {
        if ($po->status === 'dibatalkan') {
            return 'batal';
        }

        return $po->status_pembayaran ?? 'belum_lunas';
    }

    private function ensureLegacyFlow(PurchaseOrder $po): void
    {
        abort_if(
            $po->usesStageFlow(),
            422,
            'PO ini memakai alur 8 tahap. Gunakan tombol pada panel Eksekusi Tahap Aktif.'
        );
    }

    private function ensureStatus(PurchaseOrder $po, array $allowed): void
    {
        abort_unless(in_array($po->status, $allowed, true), 422, 'Transisi status PO tidak valid.');
    }

    /**
     * PO hanya boleh diedit penuh sebelum tahap Approval Manager
     * (perencanaan atau konfirmasi supplier). Setelah itu terkunci.
     */
    private function isPoEditablePreApproval(PurchaseOrder $po): bool
    {
        return $po->stage_aktif === null
            || in_array($po->stage_aktif, [PoStage::STAGE_1, PoStage::STAGE_3], true);
    }

    private function ensureNoPendingRevision(PurchaseOrder $po): void
    {
        if ($po->hasPendingRevision()) {
            throw ValidationException::withMessages([
                'revisi' => 'Aksi dikunci karena PO masih memiliki usulan revisi komitmen yang menunggu keputusan Manager.',
            ]);
        }
    }

    private function nextNoPo(): string
    {
        $year = now()->year;
        $prefix = "PO-{$year}-";

        $last = PurchaseOrder::where('no_po', 'like', $prefix.'%')
            ->orderByDesc('no_po')
            ->value('no_po');

        $nextSeq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        do {
            $candidate = sprintf('PO-%d-%04d', $year, $nextSeq);
            if (! PurchaseOrder::where('no_po', $candidate)->exists()) {
                return $candidate;
            }
            $nextSeq++;
        } while (true);
    }

    private function nextNoInvoice(): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $prefix = "INV/PO/{$year}{$month}/";

        $last = PurchaseOrder::where('no_invoice', 'like', $prefix.'%')
            ->orderByDesc('no_invoice')
            ->value('no_invoice');

        $nextSeq = 1;
        if ($last && preg_match('/\/(\d+)$/', $last, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        do {
            $candidate = sprintf('INV/PO/%s%s/%04d', $year, $month, $nextSeq);
            if (! PurchaseOrder::where('no_invoice', $candidate)->exists()) {
                return $candidate;
            }
            $nextSeq++;
        } while (true);
    }
}
