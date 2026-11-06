<?php

namespace App\Http\Controllers;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\PoStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\RequestTransfer;
use App\Models\StockRequest;
use App\Models\Stok;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use App\Services\AnalisaService;
use App\Services\Authorization\LocationScopeService;
use App\Services\StokService;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AnalisaService $analisa,
        private readonly StokService $stokService,
        private readonly LocationScopeService $locationScope,
        private readonly TransferService $transferService
    ) {
    }

    public function index(Request $request)
    {
        /** @var User $user */
        $user = auth()->user()->loadMissing(['roles', 'gudangs']);
        $role = $user->roleName() ?? 'operasional';

        // Filter untuk Dashboard Rekapitulasi Barang Rusak / Defect Mutasi
        $year = (int) $request->input('year', date('Y'));
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $gudangId = $request->filled('gudang_id') ? (int) $request->input('gudang_id') : null;

        $warehouses = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'kode', 'tipe']);
        $defectAnalytics = $this->transferService->getMonthlyWarehouseDefectAnalytics($year, $month, $gudangId);

        // Dispatch ke builder data spesifik peran
        $roleData = match ($role) {
            'manager' => $this->buildManagerData($user),
            'purchasing' => $this->buildPurchasingData($user),
            'gudang' => $this->buildGudangData($user),
            'operasional' => $this->buildOperasionalData($user),
            'fulfillment' => $this->buildFulfillmentData($user),
            default => $this->buildDefaultData($user),
        };

        return view('dashboard', array_merge([
            'user' => $user,
            'role' => $role,
            'defectAnalytics' => $defectAnalytics,
            'year' => $year,
            'month' => $month,
            'gudangId' => $gudangId,
            'warehouses' => $warehouses,
        ], $roleData));
    }

    /**
     * Dashboard Data Builder untuk peran Manager
     */
    private function buildManagerData(User $user): array
    {
        $orderSummary = $this->analisa->getItemPerluOrderSummary();

        // 1. PO Butuh Approval / Revisi
        $poApprovalQuery = PurchaseOrder::query()
            ->whereNotIn('status', ['selesai', 'dibatalkan'])
            ->where(function ($q) {
                $q->where('stage_aktif', 'approval')
                    ->orWhereHas('revisis', fn ($rq) => $rq->where('status', 'menunggu'))
                    ->orWhere('status', 'diajukan');
            });

        $poApprovalCount = (clone $poApprovalQuery)->count();

        $poApprovalItems = (clone $poApprovalQuery)
            ->with([
                'supplier:id,nama',
                'supplier.stageSummaries',
                'stages',
                'revisis' => fn ($q) => $q->where('status', 'menunggu')
            ])
            ->latest('tanggal')
            ->limit(5)
            ->get();

        $this->markPoOverdueAndRevisi($poApprovalItems);

        // 2. Request Transfer Menunggu Persetujuan
        $requestPendingCount = StockRequest::where('status', 'diajukan')->count()
            + RequestTransfer::where('status', 'diajukan')->count();

        $requestPendingItems = StockRequest::with(['gudangAsal:id,nama', 'gudangTujuan:id,nama'])
            ->where('status', 'diajukan')
            ->latest('created_at')
            ->limit(5)
            ->get();

        // 3. Batch Produksi Aktif
        $batchAktifCount = BatchProduksi::whereIn('status', ['rencana', 'release'])->count();

        // 4. Defect Rate Mutasi Bulan Ini
        $defectStats = DB::table('transfer_items')
            ->join('transfers', 'transfer_items.transfer_id', '=', 'transfers.id')
            ->where('transfers.status', 'selesai')
            ->whereMonth('transfers.created_at', now()->month)
            ->whereYear('transfers.created_at', now()->year)
            ->selectRaw('SUM(qty_dikirim) as total_kirim, SUM(qty_rusak) as total_rusak')
            ->first();

        $totalKirim = (float) ($defectStats->total_kirim ?? 0);
        $totalRusak = (float) ($defectStats->total_rusak ?? 0);
        $defectRate = $totalKirim > 0 ? round(($totalRusak / $totalKirim) * 100, 2) : 0.0;

        // 5. Komitmen Pembayaran PO Belum Lunas
        $poKomitmenBayar = PurchaseOrder::with(['supplier:id,nama'])
            ->whereNotIn('status', ['selesai', 'dibatalkan'])
            ->where('status_pembayaran', '!=', 'lunas')
            ->latest('tanggal')
            ->limit(5)
            ->get();

        // 6. Peringatan Stok Menipis
        $lowStockItems = Stok::with(['produk:id,sku,nama,satuan', 'gudang:id,nama,kode'])
            ->where('qty_saat_ini', '<=', 10)
            ->limit(5)
            ->get();

        // Susun Action Queue
        $actionQueueItems = collect();
        foreach ($poApprovalItems as $po) {
            $hasRev = $po->revisis->isNotEmpty();
            $stageLabel = $po->stage_aktif ? (PoStage::STAGE_LABELS[$po->stage_aktif] ?? 'Persetujuan Manager') : 'Persetujuan Manager';
            $actionQueueItems->push((object) [
                'type' => 'PO',
                'nomor' => $po->no_po,
                'meta' => ($po->supplier?->nama ?? '-') . ' · ' . 'Rp ' . number_format($po->grand_total, 0, ',', '.'),
                'badge_label' => $hasRev ? 'Menunggu Revisi' : $stageLabel,
                'badge_variant' => $hasRev ? 'warning' : 'primary',
                'is_terlambat' => !empty($po->is_terlambat),
                'url' => route('purchasing.show', $po),
                'action_label' => 'Review PO',
                'created_at' => $po->created_at,
            ]);
        }
        foreach ($requestPendingItems as $sr) {
            $actionQueueItems->push((object) [
                'type' => 'Request',
                'nomor' => $sr->no_request,
                'meta' => ($sr->gudangAsal?->nama ?? '-') . ' → ' . ($sr->gudangTujuan?->nama ?? '-'),
                'badge_label' => 'Otorisasi Transfer',
                'badge_variant' => 'warning',
                'url' => route('requests.show', $sr),
                'action_label' => 'Review Mutasi',
                'created_at' => $sr->created_at,
            ]);
        }

        $kpis = [
            [
                'title' => 'PO Menunggu Approval',
                'value' => $poApprovalCount,
                'subtitle' => 'Otorisasi & Revisi Pimpinan',
                'href' => route('purchasing.index', ['tab' => 'approval']),
                'footerText' => 'Buka Otorisasi PO',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            ],
            [
                'title' => 'Request Mutasi Pending',
                'value' => $requestPendingCount,
                'subtitle' => 'Persetujuan Perpindahan',
                'href' => route('requests.index'),
                'footerText' => 'Lihat Permintaan',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
            ],
            [
                'title' => 'Batch Produksi Aktif',
                'value' => $batchAktifCount,
                'subtitle' => 'Proses di Lantai Lab',
                'href' => route('batches.index'),
                'footerText' => 'Daftar Batch',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>',
            ],
            [
                'title' => 'Defect Rate Bulan Ini',
                'value' => $defectRate . '%',
                'subtitle' => number_format($totalRusak, 1) . ' unit rusak dari mutasi',
                'href' => route('transfers.index', ['year' => now()->year, 'month' => now()->month]),
                'footerText' => 'Audit Defect',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
            ],
            [
                'title' => 'Item Perlu Order Kritis',
                'value' => $orderSummary['total'],
                'subtitle' => 'Lokal: ' . $orderSummary['lokal'] . ' · Impor: ' . $orderSummary['impor'],
                'href' => route('analisa.index'),
                'footerText' => 'Buka Analisa Stok',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>',
            ],
        ];

        return [
            'kpis' => $kpis,
            'actionQueue' => [
                'title' => 'Antrean Otorisasi & Persetujuan Tertunda',
                'subtitle' => 'Purchase Order dan mutasi logistik yang memerlukan tindakan otorisasi manajerial',
                'items' => $actionQueueItems->sortByDesc('created_at')->values(),
                'viewAllUrl' => route('purchasing.index', ['tab' => 'approval']),
            ],
            'streamLeft' => [
                'title' => 'Komitmen Pembayaran PO Terbit',
                'subtitle' => 'Pesanan aktif yang memerlukan realisasi pembayaran termin / DP',
                'items' => $poKomitmenBayar,
                'viewAllUrl' => route('purchasing.index', ['tab' => 'ap']),
            ],
            'streamRight' => [
                'title' => 'Peringatan Stok di Bawah Minimum',
                'subtitle' => 'Bahan baku dan produk dengan stok fisik rendah',
                'items' => $lowStockItems,
                'viewAllUrl' => route('stok.index'),
            ],
            // Backwards compatibility legacy vars
            'orderSummary' => $orderSummary,
            'stats' => [
                'item_perlu_order' => $orderSummary['total'],
                'po_perlu_tindakan' => $poApprovalCount,
                'rt_perlu_tindakan' => $requestPendingCount,
                'batch_aktif' => $batchAktifCount,
                'batch_selesai' => BatchProduksi::where('status', 'selesai')->count(),
            ],
            'poTerbaru' => $poApprovalItems,
            'rtTerbaru' => $requestPendingItems,
        ];
    }

    /**
     * Dashboard Data Builder untuk peran Purchasing
     */
    private function buildPurchasingData(User $user): array
    {
        $orderSummary = $this->analisa->getItemPerluOrderSummary();

        // PO dalam tanggung jawab purchasing
        $poPurchasingQuery = PurchaseOrder::query()
            ->whereNotIn('status', ['selesai', 'dibatalkan'])
            ->where(function ($q) {
                $q->whereIn('stage_aktif', ['perencanaan', 'konfirmasi_supplier', 'pembayaran', 'po_terbit', 'pengemasan'])
                    ->orWhere('status', 'draft');
            });

        $poPurchasingCount = (clone $poPurchasingQuery)->count();

        $poPurchasingItems = (clone $poPurchasingQuery)
            ->with([
                'supplier:id,nama',
                'supplier.stageSummaries',
                'stages',
                'revisis' => fn ($q) => $q->where('status', 'menunggu'),
            ])
            ->latest('tanggal')
            ->limit(8)
            ->get();

        $this->markPoOverdueAndRevisi($poPurchasingItems);

        $poOverdueCount = $poPurchasingItems->where('is_terlambat', true)->count();

        // Total komitmen tagihan belum lunas
        $totalKomitmenTagihan = PurchaseOrder::whereNotIn('status', ['selesai', 'dibatalkan'])
            ->where('status_pembayaran', '!=', 'lunas')
            ->sum('grand_total');

        // Monitoring pengiriman vendor
        $poInTransit = PurchaseOrder::with(['supplier:id,nama'])
            ->whereIn('stage_aktif', ['pengemasan', 'pengiriman'])
            ->latest('tanggal')
            ->limit(5)
            ->get();

        // Susun Action Queue
        $actionQueueItems = collect();
        foreach ($poPurchasingItems as $po) {
            $stageLabel = $po->stage_aktif ? (PoStage::STAGE_LABELS[$po->stage_aktif] ?? $po->stage_aktif) : ucfirst($po->status);
            $actionQueueItems->push((object) [
                'type' => 'PO',
                'nomor' => $po->no_po,
                'meta' => ($po->supplier?->nama ?? '-') . ' · ' . 'Rp ' . number_format($po->grand_total, 0, ',', '.'),
                'badge_label' => $po->is_terlambat ? 'Terlambat SLA' : $stageLabel,
                'badge_variant' => $po->is_terlambat ? 'danger' : 'info',
                'url' => route('purchasing.show', $po),
                'action_label' => 'Buka PO',
                'created_at' => $po->created_at,
            ]);
        }

        $kpis = [
            [
                'title' => 'Item Perlu Order (Lokal)',
                'value' => $orderSummary['lokal'],
                'subtitle' => 'Bahan Baku & Kemasan Lokal',
                'href' => route('analisa.index', ['tab' => 'lokal']),
                'footerText' => 'Analisa Lokal',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
            ],
            [
                'title' => 'Item Perlu Order (Impor)',
                'value' => $orderSummary['impor'],
                'subtitle' => 'Konsentrat Fragrance Impor',
                'href' => route('analisa.index', ['tab' => 'impor']),
                'footerText' => 'Analisa Impor',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>',
            ],
            [
                'title' => 'PO Dalam Proses',
                'value' => $poPurchasingCount,
                'subtitle' => 'Tahap Pengadaan Berjalan',
                'href' => route('purchasing.index'),
                'footerText' => 'Daftar PO',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>',
            ],
            [
                'title' => 'PO Terlambat SLA',
                'value' => $poOverdueCount,
                'subtitle' => 'Melebihi Waktu Benchmark',
                'href' => route('purchasing.index'),
                'footerText' => 'Audit SLA',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            ],
            [
                'title' => 'Total Komitmen Tagihan',
                'value' => 'Rp ' . number_format($totalKomitmenTagihan, 0, ',', '.'),
                'subtitle' => 'Liabilitas PO Aktif Belum Lunas',
                'href' => route('purchasing.index', ['tab' => 'ap']),
                'footerText' => 'Buka Hutang AP',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
            ],
        ];

        return [
            'kpis' => $kpis,
            'actionQueue' => [
                'title' => 'Antrean Tindak Lanjut Pengadaan',
                'subtitle' => 'Purchase Order yang memerlukan konfirmasi vendor, pembayaran termin/DP, atau rilis pesanan',
                'items' => $actionQueueItems->values(),
                'viewAllUrl' => route('purchasing.index'),
            ],
            'streamLeft' => [
                'title' => 'Monitoring Pengiriman Ekspedisi Vendor',
                'subtitle' => 'Pesanan dalam perjalanan menuju gudang penerima',
                'items' => $poInTransit,
                'viewAllUrl' => route('purchasing.index'),
            ],
            'streamRight' => [
                'title' => 'Daftar PO Aktif Berjalan',
                'subtitle' => 'Purchase order aktif dalam pengawasan purchasing',
                'items' => $poPurchasingItems->take(5),
                'viewAllUrl' => route('purchasing.index'),
            ],
            // Backwards compatibility legacy vars
            'orderSummary' => $orderSummary,
            'stats' => [
                'item_perlu_order' => $orderSummary['total'],
                'po_perlu_tindakan' => $poPurchasingCount,
                'rt_perlu_tindakan' => 0,
                'batch_aktif' => BatchProduksi::whereIn('status', ['rencana', 'release'])->count(),
                'batch_selesai' => BatchProduksi::where('status', 'selesai')->count(),
            ],
            'poTerbaru' => $poPurchasingItems->take(5),
            'rtTerbaru' => collect(),
        ];
    }

    /**
     * Dashboard Data Builder untuk peran Gudang
     */
    private function buildGudangData(User $user): array
    {
        $accessibleWhIds = $this->locationScope->getAccessibleWarehouseIds($user);

        // 1. Kedatangan PO Siap Unloading & Input Sistem
        $poUnloadingQuery = PurchaseOrder::query()
            ->whereNotIn('status', ['selesai', 'dibatalkan'])
            ->where(function ($q) {
                $q->whereIn('stage_aktif', ['pengiriman', 'unloading', 'input_sistem'])
                    ->orWhere('status', 'dikirim_ke_gudang');
            });

        $poUnloadingCount = (clone $poUnloadingQuery)->count();
        $poUnloadingItems = (clone $poUnloadingQuery)
            ->with(['supplier:id,nama'])
            ->latest('tanggal')
            ->limit(8)
            ->get();

        // 2. Menunggu Input Sistem
        $poInputSistemCount = PurchaseOrder::where('stage_aktif', 'input_sistem')
            ->whereNotIn('status', ['selesai', 'dibatalkan'])
            ->count();

        // 3. Transfer Perlu Dikirim (Draft)
        $trfKirimQuery = Transfer::query()->where('status', 'draft');
        if (! empty($accessibleWhIds)) {
            $trfKirimQuery->whereIn('gudang_asal_id', $accessibleWhIds);
        }
        $trfKirimCount = (clone $trfKirimQuery)->count();
        $trfKirimItems = (clone $trfKirimQuery)
            ->with(['gudangAsal:id,nama', 'gudangTujuan:id,nama'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        // 4. Transfer In-Transit Masuk / Aktif
        $trfMasukQuery = Transfer::query()->where('status', 'dikirim');
        if (! empty($accessibleWhIds)) {
            $trfMasukQuery->where(function ($q) use ($accessibleWhIds) {
                $q->whereIn('gudang_tujuan_id', $accessibleWhIds)
                    ->orWhereIn('gudang_asal_id', $accessibleWhIds);
            });
        }
        $trfMasukCount = (clone $trfMasukQuery)->count();
        $trfMasukItems = (clone $trfMasukQuery)
            ->with(['gudangAsal:id,nama', 'gudangTujuan:id,nama'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        // 5. Stok Menipis Lokal
        $stokMenipisQuery = Stok::query()->where('qty_saat_ini', '<=', 10);
        if (! empty($accessibleWhIds)) {
            $stokMenipisQuery->whereIn('gudang_id', $accessibleWhIds);
        }
        $stokMenipisCount = (clone $stokMenipisQuery)->count();

        // Defect log terkini
        $defectLogs = TransferItem::with(['produk:id,sku,nama,satuan', 'transfer.gudangTujuan:id,nama'])
            ->where('qty_rusak', '>', 0)
            ->latest('created_at')
            ->limit(5)
            ->get();

        // Susun Action Queue
        $actionQueueItems = collect();
        foreach ($poUnloadingItems as $po) {
            $stageLabel = $po->stage_aktif ? (PoStage::STAGE_LABELS[$po->stage_aktif] ?? ucwords(str_replace('_', ' ', $po->stage_aktif))) : 'Siap Unloading';
            $actionQueueItems->push((object) [
                'type' => 'PO Kedatangan',
                'nomor' => $po->no_po,
                'meta' => 'Supplier: ' . ($po->supplier?->nama ?? '-') . ' · ' . ($po->tanggal?->format('d M Y') ?? '-'),
                'badge_label' => $stageLabel,
                'badge_variant' => $po->stage_aktif === 'input_sistem' ? 'warning' : 'info',
                'url' => route('purchasing.show', $po),
                'action_label' => $po->stage_aktif === 'input_sistem' ? 'Posting Saldo' : 'Bongkar & QC',
                'created_at' => $po->created_at,
            ]);
        }
        foreach ($trfKirimItems as $trf) {
            $actionQueueItems->push((object) [
                'type' => 'Transfer Keluar',
                'nomor' => $trf->no_transaksi,
                'meta' => ($trf->gudangAsal?->nama ?? '-') . ' → ' . ($trf->gudangTujuan?->nama ?? '-'),
                'badge_label' => 'Draft Surat Jalan',
                'badge_variant' => 'warning',
                'url' => route('transfers.show', $trf),
                'action_label' => 'Kirim Barang',
                'created_at' => $trf->created_at,
            ]);
        }
        foreach ($trfMasukItems as $trf) {
            $actionQueueItems->push((object) [
                'type' => 'Transfer Masuk',
                'nomor' => $trf->no_transaksi,
                'meta' => ($trf->gudangAsal?->nama ?? '-') . ' → ' . ($trf->gudangTujuan?->nama ?? '-'),
                'badge_label' => 'Dalam Pengiriman',
                'badge_variant' => 'info',
                'url' => route('transfers.show', $trf),
                'action_label' => 'Terima Barang',
                'created_at' => $trf->created_at,
            ]);
        }

        $kpis = [
            [
                'title' => 'Kedatangan Siap Unloading',
                'value' => $poUnloadingCount,
                'subtitle' => 'Ekspedisi Tiba di Gudang',
                'href' => route('purchasing.index'),
                'footerText' => 'Bongkar Muat',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>',
            ],
            [
                'title' => 'Menunggu Input Sistem',
                'value' => $poInputSistemCount,
                'subtitle' => 'Selesai QC Belum Posting',
                'href' => route('purchasing.index'),
                'footerText' => 'Posting Saldo',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
            [
                'title' => 'Transfer Perlu Dikirim',
                'value' => $trfKirimCount,
                'subtitle' => 'Draft Muat & Surat Jalan',
                'href' => route('transfers.index', ['status' => 'draft']),
                'footerText' => 'Siapkan Kirim',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>',
            ],
            [
                'title' => 'Transfer In-Transit Masuk',
                'value' => $trfMasukCount,
                'subtitle' => 'Sedang Dalam Perjalanan',
                'href' => route('transfers.index', ['status' => 'dikirim']),
                'footerText' => 'Terima Mutasi',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
            ],
            [
                'title' => 'Stok Menipis Lokal',
                'value' => $stokMenipisCount,
                'subtitle' => 'Peringatan Saldo Rendah',
                'href' => route('stok.index'),
                'footerText' => 'Cek Fisik Stok',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
            ],
        ];

        return [
            'kpis' => $kpis,
            'actionQueue' => [
                'title' => 'Antrean Bongkar Muat & Dispatch Fisik',
                'subtitle' => 'Penerimaan kedatangan barang ekspedisi dan penyiapan surat jalan keluar',
                'items' => $actionQueueItems->values(),
                'viewAllUrl' => route('purchasing.index'),
            ],
            'streamLeft' => [
                'title' => 'Pengiriman Logistik Keluar (Draft)',
                'subtitle' => 'Dokumen transfer barang yang perlu diterbitkan fisik surat jalannya',
                'items' => $trfKirimItems,
                'viewAllUrl' => route('transfers.index'),
            ],
            'streamRight' => [
                'title' => 'Pencatatan Defect & Barang Rusak',
                'subtitle' => 'Riwayat kerusakan fisik yang terdata saat penerimaan barang',
                'items' => $defectLogs,
                'viewAllUrl' => route('transfers.index'),
            ],
            // Backwards compatibility legacy vars
            'orderSummary' => ['total' => 0, 'lokal' => 0, 'impor' => 0, 'fulfillment' => 0],
            'stats' => [
                'item_perlu_order' => 0,
                'po_perlu_tindakan' => $poUnloadingCount,
                'rt_perlu_tindakan' => $trfKirimCount + $trfMasukCount,
                'batch_aktif' => BatchProduksi::whereIn('status', ['rencana', 'release'])->count(),
                'batch_selesai' => BatchProduksi::where('status', 'selesai')->count(),
            ],
            'poTerbaru' => $poUnloadingItems,
            'rtTerbaru' => $trfKirimItems,
        ];
    }

    /**
     * Dashboard Data Builder untuk peran Operasional Lab
     */
    private function buildOperasionalData(User $user): array
    {
        $batchRencana = BatchProduksi::with(['produk.bom.bahan:id,nama,sku,satuan'])
            ->where('status', 'rencana')
            ->latest('tanggal')
            ->get();

        $batchRelease = BatchProduksi::with(['produk:id,sku,nama,satuan'])
            ->where('status', 'release')
            ->latest('tanggal')
            ->limit(5)
            ->get();

        // Kesiapan BOM
        $labGudang = Gudang::where('tipe', 'operasional')->first();
        $labGudangId = $labGudang?->id;

        $readyBatchCount = 0;
        $lackingBatchCount = 0;

        foreach ($batchRencana as $b) {
            $isReady = true;
            if ($b->produk && $b->produk->bom->isNotEmpty()) {
                foreach ($b->produk->bom as $bomItem) {
                    $needed = (float) $bomItem->qty_per_unit * (float) $b->qty_rencana;
                    $avail = $labGudangId ? $this->stokService->saldo($bomItem->bahan_id, $labGudangId) : 0;
                    if ($avail < $needed) {
                        $isReady = false;
                        break;
                    }
                }
            } else {
                $isReady = false;
            }

            $b->is_bom_ready = $isReady;
            if ($isReady) {
                $readyBatchCount++;
            } else {
                $lackingBatchCount++;
            }
        }

        // Permintaan bahan lab
        $stockRequests = StockRequest::with(['gudangAsal:id,nama', 'gudangTujuan:id,nama'])
            ->whereIn('status', ['draft', 'diajukan', 'disetujui'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        $batchSelesaiCount = BatchProduksi::where('status', 'selesai')->count();

        // Action Queue: Batch Rencana Siap Rilis
        $actionQueueItems = collect();
        foreach ($batchRencana as $b) {
            $actionQueueItems->push((object) [
                'type' => 'Batch Lab',
                'nomor' => $b->no_batch,
                'meta' => ($b->produk?->nama ?? '-') . ' · ' . number_format($b->qty_rencana, 0) . ' ' . ($b->produk?->satuan ?? 'pcs'),
                'badge_label' => $b->is_bom_ready ? 'Bahan Lengkap' : 'Kurang Bahan',
                'badge_variant' => $b->is_bom_ready ? 'success' : 'warning',
                'url' => route('batches.show', $b),
                'action_label' => 'Detail Batch',
                'created_at' => $b->created_at,
            ]);
        }

        $kpis = [
            [
                'title' => 'Batch Rencana',
                'value' => $batchRencana->count(),
                'subtitle' => 'Formulasi Menunggu Rilis',
                'href' => route('batches.index', ['status' => 'rencana']),
                'footerText' => 'Rencana Batch',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>',
            ],
            [
                'title' => 'Batch Rilis (In-Progress)',
                'value' => $batchRelease->count(),
                'subtitle' => 'Pencampuran & Maserasi',
                'href' => route('batches.index', ['status' => 'release']),
                'footerText' => 'Lantai Produksi',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>',
            ],
            [
                'title' => 'Kesiapan Bahan BOM',
                'value' => "{$readyBatchCount} Batch Siap",
                'subtitle' => "{$lackingBatchCount} Kurang Bahan",
                'href' => route('batches.index'),
                'footerText' => 'Alokasi Formula',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            ],
            [
                'title' => 'Permintaan Bahan Aktif',
                'value' => $stockRequests->count(),
                'subtitle' => 'Tiket Permintaan ke Gudang',
                'href' => route('requests.index'),
                'footerText' => 'Buka Permintaan',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
            ],
            [
                'title' => 'Batch Selesai Siap Kirim',
                'value' => $batchSelesaiCount,
                'subtitle' => 'Hasil Produksi Jadi',
                'href' => route('batches.index', ['status' => 'selesai']),
                'footerText' => 'Transfer ke Toko',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>',
            ],
        ];

        return [
            'kpis' => $kpis,
            'actionQueue' => [
                'title' => 'Antrean Kesiapan Batch & Alokasi Formula',
                'subtitle' => 'Batch rencana yang bahan formulanya telah siap untuk dirilis ke pencampuran laboratorium',
                'items' => $actionQueueItems->values(),
                'viewAllUrl' => route('batches.index'),
            ],
            'streamLeft' => [
                'title' => 'Batch Produksi Sedang Berjalan',
                'subtitle' => 'Pemantauan batch laboratorium berstatus rilis aktif',
                'items' => $batchRelease,
                'viewAllUrl' => route('batches.index'),
            ],
            'streamRight' => [
                'title' => 'Permintaan Bahan ke Gudang Pusat',
                'subtitle' => 'Status pengajuan bahan baku laboratorium',
                'items' => $stockRequests,
                'viewAllUrl' => route('requests.index'),
            ],
            // Backwards compatibility legacy vars
            'orderSummary' => ['total' => 0, 'lokal' => 0, 'impor' => 0, 'fulfillment' => 0],
            'stats' => [
                'item_perlu_order' => 0,
                'po_perlu_tindakan' => 0,
                'rt_perlu_tindakan' => $stockRequests->count(),
                'batch_aktif' => $batchRencana->count() + $batchRelease->count(),
                'batch_selesai' => $batchSelesaiCount,
            ],
            'poTerbaru' => collect(),
            'rtTerbaru' => $stockRequests,
        ];
    }

    /**
     * Dashboard Data Builder untuk peran Fulfillment
     */
    private function buildFulfillmentData(User $user): array
    {
        $ffGudangIds = Gudang::fulfillment()->pluck('id')->toArray();

        // 1. Total Saldo Produk Jadi
        $totalProdukJadiStock = Stok::whereIn('gudang_id', $ffGudangIds)->sum('qty_saat_ini');

        // 2. Transfer Masuk dari Lab
        $trfMasukLab = Transfer::with(['gudangAsal:id,nama', 'gudangTujuan:id,nama'])
            ->where('status', 'dikirim')
            ->where('jenis', 'kirim_produk_jadi')
            ->latest('waktu_dikirim')
            ->limit(5)
            ->get();

        // 3. Transfer Antar Toko
        $trfAntarToko = Transfer::with(['gudangAsal:id,nama', 'gudangTujuan:id,nama'])
            ->where('jenis', 'antar_fulfillment')
            ->whereIn('status', ['draft', 'dikirim'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        // 4. SKU Kritis Produk Jadi (Saldo <= 20)
        $skuKritisItems = Stok::with(['produk:id,sku,nama,satuan', 'gudang:id,nama,kode'])
            ->whereIn('gudang_id', $ffGudangIds)
            ->where('qty_saat_ini', '<=', 20)
            ->orderBy('qty_saat_ini', 'asc')
            ->limit(5)
            ->get();

        // 5. Batch Sedang Berproses di Lab (Supply Segera Tiba)
        $batchSegeraSelesaiCount = BatchProduksi::where('status', 'release')->count();

        // Action Queue: Transfer Masuk Siap Diterima
        $actionQueueItems = collect();
        foreach ($trfMasukLab as $trf) {
            $actionQueueItems->push((object) [
                'type' => 'Pasokan Lab',
                'nomor' => $trf->no_transaksi,
                'meta' => ($trf->gudangAsal?->nama ?? '-') . ' → ' . ($trf->gudangTujuan?->nama ?? '-'),
                'badge_label' => 'Dalam Perjalanan',
                'badge_variant' => 'info',
                'url' => route('transfers.show', $trf),
                'action_label' => 'Terima Produk',
                'created_at' => $trf->created_at,
            ]);
        }
        foreach ($trfAntarToko as $trf) {
            $actionQueueItems->push((object) [
                'type' => 'Antar Toko',
                'nomor' => $trf->no_transaksi,
                'meta' => ($trf->gudangAsal?->nama ?? '-') . ' → ' . ($trf->gudangTujuan?->nama ?? '-'),
                'badge_label' => ucfirst($trf->status),
                'badge_variant' => $trf->status === 'dikirim' ? 'info' : 'warning',
                'url' => route('transfers.show', $trf),
                'action_label' => 'Detail Transfer',
                'created_at' => $trf->created_at,
            ]);
        }

        $kpis = [
            [
                'title' => 'Total Saldo Produk Jadi',
                'value' => number_format($totalProdukJadiStock, 0, ',', '.') . ' Pcs',
                'subtitle' => 'Stok Fisik Hub Toko',
                'href' => route('stok.index'),
                'footerText' => 'Lihat Saldo Toko',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
            ],
            [
                'title' => 'Transfer Masuk dari Lab',
                'value' => $trfMasukLab->count(),
                'subtitle' => 'Pasokan Batch Selesai',
                'href' => route('transfers.index', ['status' => 'dikirim']),
                'footerText' => 'Penerimaan Toko',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>',
            ],
            [
                'title' => 'Transfer Antar Toko',
                'value' => $trfAntarToko->count(),
                'subtitle' => 'Mutasi Cabang & Hub',
                'href' => route('transfers.index'),
                'footerText' => 'Distribusi Hub',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
            ],
            [
                'title' => 'SKU Kritis Fast-Moving',
                'value' => $skuKritisItems->count(),
                'subtitle' => 'Mendekati Batas Buffer',
                'href' => route('analisa.index', ['tab' => 'fulfillment']),
                'footerText' => 'Rekomendasi Toko',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
            ],
            [
                'title' => 'Batch Segera Selesai',
                'value' => "{$batchSegeraSelesaiCount} Batch",
                'subtitle' => 'Sedang Diproses di Lab',
                'href' => route('batches.index', ['status' => 'release']),
                'footerText' => 'Pantau Lab',
                'icon' => '<svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>',
            ],
        ];

        return [
            'kpis' => $kpis,
            'actionQueue' => [
                'title' => 'Antrean Penerimaan Produk Jadi & Distribusi Toko',
                'subtitle' => 'Pengiriman produk jadi tiba dari lab dan pemenuhan mutasi cabang',
                'items' => $actionQueueItems->values(),
                'viewAllUrl' => route('transfers.index'),
            ],
            'streamLeft' => [
                'title' => 'Ketersediaan Produk Jadi Utama',
                'subtitle' => 'Stok produk dengan saldo menipis di gerai/hub fulfillment',
                'items' => $skuKritisItems,
                'viewAllUrl' => route('stok.index'),
            ],
            'streamRight' => [
                'title' => 'Pasokan Selesai dari Laboratorium',
                'subtitle' => 'Pengiriman produk jadi yang sedang dalam perjalanan menuju hub',
                'items' => $trfMasukLab,
                'viewAllUrl' => route('transfers.index'),
            ],
            // Backwards compatibility legacy vars
            'orderSummary' => ['total' => 0, 'lokal' => 0, 'impor' => 0, 'fulfillment' => 0],
            'stats' => [
                'item_perlu_order' => 0,
                'po_perlu_tindakan' => 0,
                'rt_perlu_tindakan' => $trfMasukLab->count() + $trfAntarToko->count(),
                'batch_aktif' => $batchSegeraSelesaiCount,
                'batch_selesai' => BatchProduksi::where('status', 'selesai')->count(),
            ],
            'poTerbaru' => collect(),
            'rtTerbaru' => $trfMasukLab,
        ];
    }

    /**
     * Dashboard Data Builder Fallback untuk peran default/kustom
     */
    private function buildDefaultData(User $user): array
    {
        return $this->buildOperasionalData($user);
    }

    /**
     * Helper untuk menandai PO yang mengalami keterlambatan SLA atau memiliki revisi aktif
     *
     * @param  Collection<int,PurchaseOrder>  $pos
     */
    private function markPoOverdueAndRevisi(Collection $pos): void
    {
        $pos->each(function ($po) {
            $isTerlambat = false;
            if ($po->stage_aktif && ! in_array($po->status, ['selesai', 'dibatalkan'], true)) {
                $currentStage = $po->stages->where('stage', $po->stage_aktif)->sortByDesc('putaran')->first();
                if ($currentStage && ! in_array($currentStage->status, ['selesai', 'dikembalikan'], true) && $currentStage->waktu_mulai) {
                    $benchmark = $po->supplier?->stageSummaries?->firstWhere('stage', $po->stage_aktif);
                    if ($benchmark && $benchmark->jumlah_transaksi > 0) {
                        $avgMinutes = ($benchmark->total_menit > 0)
                            ? ($benchmark->total_menit / $benchmark->jumlah_transaksi)
                            : ((float) $benchmark->average_hari * 1440);

                        if ($avgMinutes > 0) {
                            $elapsedMinutes = $currentStage->waktu_mulai->diffInMinutes(now());
                            if ($elapsedMinutes > $avgMinutes) {
                                $isTerlambat = true;
                            }
                        }
                    }
                }
            }
            $po->is_terlambat = $isTerlambat;
            $po->has_revisi_menunggu = $po->relationLoaded('revisis') && $po->revisis->isNotEmpty();
        });
    }
}
