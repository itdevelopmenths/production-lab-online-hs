<?php

namespace App\Http\Controllers;

use App\Models\BatchProduksi;
use App\Models\PoStage;
use App\Models\PurchaseOrder;
use App\Models\RequestTransfer;
use App\Models\StockRequest;
use App\Models\Transfer;
use App\Services\AnalisaService;

class DashboardController extends Controller
{
    public function __construct(private readonly AnalisaService $analisa)
    {
    }

    public function index()
    {
        $user = auth()->user()->loadMissing('roles');
        $roleName = $user->roleName();

        // 1. Ringkasan item berstatus ORDER dari Analisa Stok
        $orderSummary = $this->analisa->getItemPerluOrderSummary();

        // Peran / Wewenang
        $isManager = $user->hasRole('manager') || $user->can('purchasing.approve');
        $isGudang = $user->hasRole('gudang') || $user->can('purchasing.receive');
        $isPurchasing = $user->hasRole('purchasing') || $user->can('purchasing.create');
        $isOperasional = $user->hasRole('operasional');
        $isFulfillment = $user->hasRole('fulfillment');

        // 2. Kueri PO dual-aware (PRD v2.4 9-Stage & revisi vs Legacy)
        $poQuery = PurchaseOrder::query()->whereNotIn('status', ['selesai', 'dibatalkan']);

        if ($isManager) {
            $poQuery->where(function ($q) {
                $q->where('stage_aktif', 'approval')
                    ->orWhereHas('revisis', fn ($rq) => $rq->where('status', 'menunggu'))
                    ->orWhere('status', 'diajukan');
            });
        } elseif ($isGudang) {
            $poQuery->where(function ($q) {
                $q->whereIn('stage_aktif', ['pengiriman', 'unloading', 'input_sistem'])
                    ->orWhere('status', 'dikirim_ke_gudang');
            });
        } elseif ($isPurchasing) {
            $poQuery->where(function ($q) {
                $q->whereIn('stage_aktif', ['perencanaan', 'konfirmasi_supplier', 'pembayaran', 'po_terbit', 'pengemasan'])
                    ->orWhere('status', 'draft');
            });
        } else {
            $poQuery->where(function ($q) {
                $q->whereNotNull('stage_aktif')
                    ->orWhereIn('status', ['draft', 'diajukan', 'dikirim_ke_gudang']);
            });
        }

        $poPerluTindakanCount = (clone $poQuery)->count();

        // 3. PO Terbaru dengan eager loading bebas N+1
        $poTerbaru = (clone $poQuery)
            ->with([
                'supplier:id,nama',
                'supplier.stageSummaries',
                'stages',
                'revisis' => fn ($q) => $q->where('status', 'menunggu'),
            ])
            ->latest('tanggal')
            ->limit(5)
            ->get();

        $poTerbaru->each(function ($po) {
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
            $po->has_revisi_menunggu = $po->revisis->isNotEmpty();
        });

        // 4. Kueri Logistik (StockRequest, Transfer, and Legacy RequestTransfer)
        if ($isManager) {
            $reqStatuses = ['diajukan'];
            $trfStatuses = ['draft'];
            $legacyStatuses = ['diajukan'];
        } elseif ($isGudang) {
            $reqStatuses = ['disetujui'];
            $trfStatuses = ['draft', 'dikirim'];
            $legacyStatuses = ['disetujui', 'diproses', 'dikirim'];
        } elseif ($isFulfillment) {
            $reqStatuses = ['draft'];
            $trfStatuses = ['draft', 'dikirim'];
            $legacyStatuses = ['diproses', 'dikirim'];
        } elseif ($isOperasional) {
            $reqStatuses = ['draft'];
            $trfStatuses = ['draft', 'dikirim'];
            $legacyStatuses = ['draft', 'diproses'];
        } else {
            $reqStatuses = ['draft', 'diajukan', 'disetujui'];
            $trfStatuses = ['draft', 'dikirim'];
            $legacyStatuses = ['draft', 'diajukan', 'disetujui', 'diproses', 'dikirim'];
        }

        $stockRequestCount = StockRequest::whereIn('status', $reqStatuses)->count();
        $transferCount = Transfer::whereIn('status', $trfStatuses)->count();
        $legacyRtCount = RequestTransfer::whereIn('status', $legacyStatuses)->count();

        $rtPerluTindakanCount = $stockRequestCount + $transferCount + $legacyRtCount;

        // Ambil daftar logistik terbaru (gabungan)
        $stockRequests = StockRequest::with(['gudangAsal:id,nama', 'gudangTujuan:id,nama'])
            ->whereIn('status', $reqStatuses)
            ->latest('created_at')
            ->limit(5)
            ->get();

        $transfers = Transfer::with(['gudangAsal:id,nama', 'gudangTujuan:id,nama'])
            ->whereIn('status', $trfStatuses)
            ->latest('created_at')
            ->limit(5)
            ->get();

        $legacyRts = RequestTransfer::with(['gudangAsal:id,nama', 'gudangTujuan:id,nama'])
            ->whereIn('status', $legacyStatuses)
            ->latest('created_at')
            ->limit(5)
            ->get();

        $logistikItems = collect();

        foreach ($stockRequests as $sr) {
            $logistikItems->push((object) [
                'type' => 'request',
                'nomor' => $sr->no_request,
                'no_transaksi' => $sr->no_request,
                'jenis' => $sr->jenis,
                'label_jenis' => StockRequest::JENIS_LABELS[$sr->jenis] ?? ucwords(str_replace('_', ' ', $sr->jenis)),
                'gudang_asal_nama' => $sr->gudangAsal?->nama ?? '-',
                'gudang_tujuan_nama' => $sr->gudangTujuan?->nama ?? '-',
                'gudangAsal' => (object) ['nama' => $sr->gudangAsal?->nama ?? '-'],
                'gudangTujuan' => (object) ['nama' => $sr->gudangTujuan?->nama ?? '-'],
                'status' => $sr->status,
                'badge_label' => ucfirst($sr->status),
                'badge_variant' => match ($sr->status) {
                    'diajukan' => 'warning',
                    'disetujui' => 'primary',
                    'diproses' => 'info',
                    'selesai' => 'success',
                    default => 'neutral',
                },
                'url' => route('requests.show', $sr),
                'created_at' => $sr->created_at,
            ]);
        }

        foreach ($transfers as $tr) {
            $logistikItems->push((object) [
                'type' => 'transfer',
                'nomor' => $tr->no_transaksi,
                'no_transaksi' => $tr->no_transaksi,
                'jenis' => $tr->jenis,
                'label_jenis' => Transfer::JENIS_LABELS[$tr->jenis] ?? ucwords(str_replace('_', ' ', $tr->jenis)),
                'gudang_asal_nama' => $tr->gudangAsal?->nama ?? '-',
                'gudang_tujuan_nama' => $tr->gudangTujuan?->nama ?? '-',
                'gudangAsal' => (object) ['nama' => $tr->gudangAsal?->nama ?? '-'],
                'gudangTujuan' => (object) ['nama' => $tr->gudangTujuan?->nama ?? '-'],
                'status' => $tr->status,
                'badge_label' => ucfirst($tr->status),
                'badge_variant' => match ($tr->status) {
                    'dikirim' => 'warning',
                    'selesai' => 'success',
                    default => 'neutral',
                },
                'url' => route('transfers.show', $tr),
                'created_at' => $tr->created_at,
            ]);
        }

        foreach ($legacyRts as $rt) {
            $logistikItems->push((object) [
                'type' => 'legacy_rt',
                'nomor' => $rt->no_transaksi,
                'no_transaksi' => $rt->no_transaksi,
                'jenis' => $rt->jenis,
                'label_jenis' => ucwords(str_replace('_', ' ', $rt->jenis)),
                'gudang_asal_nama' => $rt->gudangAsal?->nama ?? '-',
                'gudang_tujuan_nama' => $rt->gudangTujuan?->nama ?? '-',
                'gudangAsal' => (object) ['nama' => $rt->gudangAsal?->nama ?? '-'],
                'gudangTujuan' => (object) ['nama' => $rt->gudangTujuan?->nama ?? '-'],
                'status' => $rt->status,
                'badge_label' => ucwords(str_replace('_', ' ', $rt->status)),
                'badge_variant' => match ($rt->status) {
                    'diajukan' => 'warning',
                    'disetujui' => 'primary',
                    'diproses' => 'info',
                    'dikirim' => 'warning',
                    'selesai' => 'success',
                    default => 'neutral',
                },
                'url' => route('rt.show', $rt),
                'created_at' => $rt->created_at,
            ]);
        }

        $rtTerbaru = $logistikItems->sortByDesc('created_at')->take(5)->values();

        // 5. Statistik ringkasan
        $stats = [
            'item_perlu_order' => $orderSummary['total'],
            'po_perlu_tindakan' => $poPerluTindakanCount,
            'rt_perlu_tindakan' => $rtPerluTindakanCount,
            'batch_aktif' => BatchProduksi::whereIn('status', ['rencana', 'release'])->count(),
            'batch_selesai' => BatchProduksi::where('status', 'selesai')->count(),
        ];

        return view('dashboard', compact('user', 'stats', 'orderSummary', 'poTerbaru', 'rtTerbaru'));
    }
}
