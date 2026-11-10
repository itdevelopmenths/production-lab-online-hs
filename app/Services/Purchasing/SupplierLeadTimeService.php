<?php

namespace App\Services\Purchasing;

use App\Models\PoLeadTime;
use App\Models\PoStage;
use App\Models\PurchaseOrder;
use App\Models\SupplierLtHistory;
use App\Models\SupplierLtStageSummary;
use App\Models\SupplierLtSummary;
use App\Models\SupplierProdukLtStageSummary;
use App\Models\SupplierProdukLtSummary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupplierLeadTimeService
{
    public const INTERNAL_STAGES = [
        PoStage::STAGE_1,
        PoStage::STAGE_2,
        PoStage::STAGE_4,
        PoStage::STAGE_5,
        PoStage::STAGE_8,
        PoStage::STAGE_9,
    ];

    public const SUPPLIER_STAGES = [
        PoStage::STAGE_3,
        PoStage::STAGE_6,
    ];

    public const LOGISTIK_STAGES = [
        PoStage::STAGE_7,
    ];

    /**
     * Konversi menit ke hari dengan toleransi 1 desimal half-up.
     * Digit ke-2 dan seterusnya: < 5 => 0, >= 5 => +0.1.
     */
    public static function minutesToDays(int|float $minutes): float
    {
        if ($minutes <= 0) {
            return 0.0;
        }

        return (float) round($minutes / 1440, 1, PHP_ROUND_HALF_UP);
    }

    /**
     * Konversi menit ke jam dengan toleransi 2 desimal half-up.
     */
    public static function minutesToHours(int|float $minutes): float
    {
        if ($minutes <= 0) {
            return 0.0;
        }

        return (float) round($minutes / 60, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * Catat Lead Time pada saat PO Selesai (Stage 9 Input Sistem).
     * Dijalankan dalam transaksi atomik dengan lockForUpdate.
     */
    public function recordCompletedPo(PurchaseOrder $po, ?int $actorUserId = null): PoLeadTime
    {
        return DB::transaction(function () use ($po, $actorUserId) {
            $po->loadMissing(['stages', 'items']);

            // 1. Hitung durasi per kategori
            $internalMenit = 0;
            $supplierMenit = 0;
            $logistikMenit = 0;
            $totalLtMenit = 0;

            $stageDurations = [];
            foreach (PoStage::STAGES as $order => $stageKey) {
                // Jumlahkan semua putaran untuk stage ini
                $durasi = (int) $po->stages->where('stage', $stageKey)->sum('durasi_menit');
                $stageDurations[$stageKey] = $durasi;
                $totalLtMenit += $durasi;

                if (in_array($stageKey, self::INTERNAL_STAGES, true)) {
                    $internalMenit += $durasi;
                } elseif (in_array($stageKey, self::SUPPLIER_STAGES, true)) {
                    $supplierMenit += $durasi;
                } elseif (in_array($stageKey, self::LOGISTIK_STAGES, true)) {
                    $logistikMenit += $durasi;
                }
            }

            $totalLtHari = self::minutesToDays($totalLtMenit);
            $waktuMulai = $po->stages->sortBy('waktu_mulai')->first()?->waktu_mulai ?? $po->created_at ?? now();
            $waktuSelesai = $po->stages->where('stage', PoStage::STAGE_9)->first()?->waktu_selesai ?? now();

            // 2. Simpan / perbarui PoLeadTime
            $poLt = PoLeadTime::updateOrCreate(
                ['po_id' => $po->id],
                [
                    'supplier_id' => $po->supplier_id,
                    'waktu_mulai' => $waktuMulai,
                    'waktu_selesai' => $waktuSelesai,
                    'total_lt_menit' => $totalLtMenit,
                    'total_lt_hari' => $totalLtHari,
                    'lt_internal_menit' => $internalMenit,
                    'lt_supplier_menit' => $supplierMenit,
                    'lt_logistik_menit' => $logistikMenit,
                    'is_valid' => true,
                    'alasan_tidak_valid' => null,
                    'divalidasi_oleh' => null,
                ]
            );

            // 3. Level Supplier Summary (lockForUpdate)
            $sSummary = SupplierLtSummary::where('supplier_id', $po->supplier_id)->lockForUpdate()->first();
            if (! $sSummary) {
                $sSummary = new SupplierLtSummary();
                $sSummary->supplier_id = $po->supplier_id;
                $sSummary->jumlah_transaksi = 0;
                $sSummary->total_lt_menit = 0;
                $sSummary->average_lt_hari = 0.0;
                $sSummary->max_lt_hari = 0.0;
            }

            $sPrevCount = (int) $sSummary->jumlah_transaksi;
            $sPrevTotalMenit = (int) $sSummary->total_lt_menit;
            $sPrevAvgHari = (float) $sSummary->average_lt_hari;
            $sPrevMaxHari = (float) $sSummary->max_lt_hari;

            $sNewCount = $sPrevCount + 1;
            $sNewTotalMenit = $sPrevTotalMenit + $totalLtMenit;
            $sNewAvgHari = self::minutesToDays($sNewTotalMenit / $sNewCount);
            $sNewMaxHari = max($sPrevMaxHari, $totalLtHari);

            $sSummary->jumlah_transaksi = $sNewCount;
            $sSummary->total_lt_menit = $sNewTotalMenit;
            $sSummary->average_lt_hari = $sNewAvgHari;
            $sSummary->max_lt_hari = $sNewMaxHari;
            $sSummary->diperbarui_pada = now();
            $sSummary->save();

            // History Level Supplier
            SupplierLtHistory::create([
                'supplier_id' => $po->supplier_id,
                'produk_id' => null,
                'po_id' => $po->id,
                'jenis_event' => 'transaksi_baru',
                'jumlah_sebelum' => $sPrevCount,
                'jumlah_sesudah' => $sNewCount,
                'total_menit_sebelum' => $sPrevTotalMenit,
                'total_menit_sesudah' => $sNewTotalMenit,
                'average_sebelum' => $sPrevAvgHari,
                'average_sesudah' => $sNewAvgHari,
                'max_sebelum' => $sPrevMaxHari,
                'max_sesudah' => $sNewMaxHari,
                'dicatat_oleh' => $actorUserId,
                'dicatat_pada' => now(),
            ]);

            // Supplier LT Stage Summary (9 stages)
            foreach ($stageDurations as $stageKey => $durasi) {
                $stageSum = SupplierLtStageSummary::where('supplier_id', $po->supplier_id)
                    ->where('stage', $stageKey)
                    ->lockForUpdate()
                    ->first();

                if (! $stageSum) {
                    $stageSum = new SupplierLtStageSummary();
                    $stageSum->supplier_id = $po->supplier_id;
                    $stageSum->stage = $stageKey;
                    $stageSum->jumlah_transaksi = 0;
                    $stageSum->total_menit = 0;
                    $stageSum->average_hari = 0.0;
                    $stageSum->max_hari = 0.0;
                }

                $stageNewCount = $stageSum->jumlah_transaksi + 1;
                $stageNewMenit = $stageSum->total_menit + $durasi;
                $stageDurasiHari = self::minutesToDays($durasi);

                $stageSum->jumlah_transaksi = $stageNewCount;
                $stageSum->total_menit = $stageNewMenit;
                $stageSum->average_hari = self::minutesToDays($stageNewMenit / $stageNewCount);
                $stageSum->max_hari = max((float) $stageSum->max_hari, $stageDurasiHari);
                $stageSum->save();
            }

            // 4. Level Supplier-Produk Summary (lockForUpdate per SKU)
            $distinctProductIds = $po->items->pluck('produk_id')->filter()->unique()->all();

            foreach ($distinctProductIds as $produkId) {
                $spSummary = SupplierProdukLtSummary::where('supplier_id', $po->supplier_id)
                    ->where('produk_id', $produkId)
                    ->lockForUpdate()
                    ->first();

                if (! $spSummary) {
                    $spSummary = new SupplierProdukLtSummary();
                    $spSummary->supplier_id = $po->supplier_id;
                    $spSummary->produk_id = $produkId;
                    $spSummary->jumlah_transaksi = 0;
                    $spSummary->total_lt_menit = 0;
                    $spSummary->average_lt_hari = 0.0;
                    $spSummary->max_lt_hari = 0.0;
                }

                $spPrevCount = (int) $spSummary->jumlah_transaksi;
                $spPrevTotalMenit = (int) $spSummary->total_lt_menit;
                $spPrevAvgHari = (float) $spSummary->average_lt_hari;
                $spPrevMaxHari = (float) $spSummary->max_lt_hari;

                $spNewCount = $spPrevCount + 1;
                $spNewTotalMenit = $spPrevTotalMenit + $totalLtMenit;
                $spNewAvgHari = self::minutesToDays($spNewTotalMenit / $spNewCount);
                $spNewMaxHari = max($spPrevMaxHari, $totalLtHari);

                $spSummary->jumlah_transaksi = $spNewCount;
                $spSummary->total_lt_menit = $spNewTotalMenit;
                $spSummary->average_lt_hari = $spNewAvgHari;
                $spSummary->max_lt_hari = $spNewMaxHari;
                $spSummary->diperbarui_pada = now();
                $spSummary->save();

                // History Level Supplier-Produk
                SupplierLtHistory::create([
                    'supplier_id' => $po->supplier_id,
                    'produk_id' => $produkId,
                    'po_id' => $po->id,
                    'jenis_event' => 'transaksi_baru',
                    'jumlah_sebelum' => $spPrevCount,
                    'jumlah_sesudah' => $spNewCount,
                    'total_menit_sebelum' => $spPrevTotalMenit,
                    'total_menit_sesudah' => $spNewTotalMenit,
                    'average_sebelum' => $spPrevAvgHari,
                    'average_sesudah' => $spNewAvgHari,
                    'max_sebelum' => $spPrevMaxHari,
                    'max_sesudah' => $spNewMaxHari,
                    'dicatat_oleh' => $actorUserId,
                    'dicatat_pada' => now(),
                ]);

                // Supplier-Produk Stage Summary
                foreach ($stageDurations as $stageKey => $durasi) {
                    $spStageSum = SupplierProdukLtStageSummary::where('supplier_id', $po->supplier_id)
                        ->where('produk_id', $produkId)
                        ->where('stage', $stageKey)
                        ->lockForUpdate()
                        ->first();

                    if (! $spStageSum) {
                        $spStageSum = new SupplierProdukLtStageSummary();
                        $spStageSum->supplier_id = $po->supplier_id;
                        $spStageSum->produk_id = $produkId;
                        $spStageSum->stage = $stageKey;
                        $spStageSum->jumlah_transaksi = 0;
                        $spStageSum->total_menit = 0;
                        $spStageSum->average_hari = 0.0;
                        $spStageSum->max_hari = 0.0;
                    }

                    $spStageNewCount = $spStageSum->jumlah_transaksi + 1;
                    $spStageNewMenit = $spStageSum->total_menit + $durasi;
                    $spStageDurasiHari = self::minutesToDays($durasi);

                    $spStageSum->jumlah_transaksi = $spStageNewCount;
                    $spStageSum->total_menit = $spStageNewMenit;
                    $spStageSum->average_hari = self::minutesToDays($spStageNewMenit / $spStageNewCount);
                    $spStageSum->max_hari = max((float) $spStageSum->max_hari, $spStageDurasiHari);
                    $spStageSum->save();
                }
            }

            app(\App\Services\AnalisaService::class)->refreshAffectedProductsFromPo($distinctProductIds, $actorUserId);

            return $poLt;
        });
    }

    /**
     * Invalidate data Lead Time transaksi PO (oleh Manager dengan alasan wajib).
     */
    public function invalidateTransaction(PoLeadTime $poLt, User $manager, string $alasan): void
    {
        if (trim($alasan) === '') {
            throw new \InvalidArgumentException('Alasan invalidasi lead time wajib diisi.');
        }

        DB::transaction(function () use ($poLt, $manager, $alasan) {
            $poLt->loadMissing(['purchaseOrder.items', 'purchaseOrder.stages']);

            if (! $poLt->is_valid) {
                return; // Sudah tidak valid
            }

            $poLt->is_valid = false;
            $poLt->alasan_tidak_valid = $alasan;
            $poLt->divalidasi_oleh = $manager->id;
            $poLt->save();

            $totalLtMenit = $poLt->total_lt_menit;

            // Hitung durasi per stage untuk PO yang diinvalidasi
            $poStages = $poLt->purchaseOrder->stages;
            $stageDurations = [];
            foreach (PoStage::STAGES as $stageKey) {
                $stageDurations[$stageKey] = (int) $poStages->where('stage', $stageKey)->sum('durasi_menit');
            }

            // 1. Kurangi dari SupplierLtSummary
            $sSummary = SupplierLtSummary::where('supplier_id', $poLt->supplier_id)->lockForUpdate()->first();
            if ($sSummary && $sSummary->jumlah_transaksi > 0) {
                $sPrevCount = (int) $sSummary->jumlah_transaksi;
                $sPrevTotalMenit = (int) $sSummary->total_lt_menit;
                $sPrevAvgHari = (float) $sSummary->average_lt_hari;
                $sPrevMaxHari = (float) $sSummary->max_lt_hari;

                $sNewCount = max(0, $sPrevCount - 1);
                $sNewTotalMenit = max(0, $sPrevTotalMenit - $totalLtMenit);
                $sNewAvgHari = $sNewCount > 0 ? self::minutesToDays($sNewTotalMenit / $sNewCount) : 0.0;

                // Cari max baru dari transaksi valid yang tersisa
                $sNewMaxHari = (float) (PoLeadTime::where('supplier_id', $poLt->supplier_id)
                    ->where('is_valid', true)
                    ->max('total_lt_hari') ?? 0.0);

                $sSummary->jumlah_transaksi = $sNewCount;
                $sSummary->total_lt_menit = $sNewTotalMenit;
                $sSummary->average_lt_hari = $sNewAvgHari;
                $sSummary->max_lt_hari = $sNewMaxHari;
                $sSummary->diperbarui_pada = now();
                $sSummary->save();

                SupplierLtHistory::create([
                    'supplier_id' => $poLt->supplier_id,
                    'produk_id' => null,
                    'po_id' => $poLt->po_id,
                    'jenis_event' => 'invalidasi',
                    'jumlah_sebelum' => $sPrevCount,
                    'jumlah_sesudah' => $sNewCount,
                    'total_menit_sebelum' => $sPrevTotalMenit,
                    'total_menit_sesudah' => $sNewTotalMenit,
                    'average_sebelum' => $sPrevAvgHari,
                    'average_sesudah' => $sNewAvgHari,
                    'max_sebelum' => $sPrevMaxHari,
                    'max_sesudah' => $sNewMaxHari,
                    'dicatat_oleh' => $manager->id,
                    'dicatat_pada' => now(),
                ]);
            }

            // 1b. Kurangi dari SupplierLtStageSummary
            $validPoIds = PoLeadTime::where('supplier_id', $poLt->supplier_id)
                ->where('is_valid', true)
                ->pluck('po_id');

            foreach ($stageDurations as $stageKey => $durasi) {
                $stageSum = SupplierLtStageSummary::where('supplier_id', $poLt->supplier_id)
                    ->where('stage', $stageKey)
                    ->lockForUpdate()
                    ->first();

                if ($stageSum && $stageSum->jumlah_transaksi > 0) {
                    $stNewCount = max(0, $stageSum->jumlah_transaksi - 1);
                    $stNewMenit = max(0, $stageSum->total_menit - $durasi);
                    $stNewAvg = $stNewCount > 0 ? self::minutesToDays($stNewMenit / $stNewCount) : 0.0;

                    $maxStageMenit = 0;
                    if ($validPoIds->isNotEmpty()) {
                        $maxStageMenit = (int) PoStage::whereIn('po_id', $validPoIds)
                            ->where('stage', $stageKey)
                            ->groupBy('po_id')
                            ->selectRaw('SUM(durasi_menit) as total_stage_menit')
                            ->pluck('total_stage_menit')
                            ->max();
                    }
                    $stNewMax = self::minutesToDays($maxStageMenit);

                    $stageSum->jumlah_transaksi = $stNewCount;
                    $stageSum->total_menit = $stNewMenit;
                    $stageSum->average_hari = $stNewAvg;
                    $stageSum->max_hari = $stNewMax;
                    $stageSum->save();
                }
            }

            // 2. Kurangi dari SupplierProdukLtSummary dan SupplierProdukLtStageSummary untuk tiap SKU
            $distinctProductIds = $poLt->purchaseOrder->items->pluck('produk_id')->filter()->unique()->all();
            foreach ($distinctProductIds as $produkId) {
                $spSummary = SupplierProdukLtSummary::where('supplier_id', $poLt->supplier_id)
                    ->where('produk_id', $produkId)
                    ->lockForUpdate()
                    ->first();

                if ($spSummary && $spSummary->jumlah_transaksi > 0) {
                    $spPrevCount = (int) $spSummary->jumlah_transaksi;
                    $spPrevTotalMenit = (int) $spSummary->total_lt_menit;
                    $spPrevAvgHari = (float) $spSummary->average_lt_hari;
                    $spPrevMaxHari = (float) $spSummary->max_lt_hari;

                    $spNewCount = max(0, $spPrevCount - 1);
                    $spNewTotalMenit = max(0, $spPrevTotalMenit - $totalLtMenit);
                    $spNewAvgHari = $spNewCount > 0 ? self::minutesToDays($spNewTotalMenit / $spNewCount) : 0.0;

                    $validPoIdsForProduk = PurchaseOrder::where('supplier_id', $poLt->supplier_id)
                        ->whereHas('items', fn ($q) => $q->where('produk_id', $produkId))
                        ->whereHas('leadTime', fn ($q) => $q->where('is_valid', true))
                        ->pluck('id');

                    $spNewMaxHari = (float) (PoLeadTime::whereIn('po_id', $validPoIdsForProduk)
                        ->where('is_valid', true)
                        ->max('total_lt_hari') ?? 0.0);

                    $spSummary->jumlah_transaksi = $spNewCount;
                    $spSummary->total_lt_menit = $spNewTotalMenit;
                    $spSummary->average_lt_hari = $spNewAvgHari;
                    $spSummary->max_lt_hari = $spNewMaxHari;
                    $spSummary->diperbarui_pada = now();
                    $spSummary->save();

                    SupplierLtHistory::create([
                        'supplier_id' => $poLt->supplier_id,
                        'produk_id' => $produkId,
                        'po_id' => $poLt->po_id,
                        'jenis_event' => 'invalidasi',
                        'jumlah_sebelum' => $spPrevCount,
                        'jumlah_sesudah' => $spNewCount,
                        'total_menit_sebelum' => $spPrevTotalMenit,
                        'total_menit_sesudah' => $spNewTotalMenit,
                        'average_sebelum' => $spPrevAvgHari,
                        'average_sesudah' => $spNewAvgHari,
                        'max_sebelum' => $spPrevMaxHari,
                        'max_sesudah' => $spNewMaxHari,
                        'dicatat_oleh' => $manager->id,
                        'dicatat_pada' => now(),
                    ]);

                    // SupplierProdukLtStageSummary
                    foreach ($stageDurations as $stageKey => $durasi) {
                        $spStageSum = SupplierProdukLtStageSummary::where('supplier_id', $poLt->supplier_id)
                            ->where('produk_id', $produkId)
                            ->where('stage', $stageKey)
                            ->lockForUpdate()
                            ->first();

                        if ($spStageSum && $spStageSum->jumlah_transaksi > 0) {
                            $spStNewCount = max(0, $spStageSum->jumlah_transaksi - 1);
                            $spStNewMenit = max(0, $spStageSum->total_menit - $durasi);
                            $spStNewAvg = $spStNewCount > 0 ? self::minutesToDays($spStNewMenit / $spStNewCount) : 0.0;

                            $maxSpStageMenit = 0;
                            if ($validPoIdsForProduk->isNotEmpty()) {
                                $maxSpStageMenit = (int) PoStage::whereIn('po_id', $validPoIdsForProduk)
                                    ->where('stage', $stageKey)
                                    ->groupBy('po_id')
                                    ->selectRaw('SUM(durasi_menit) as total_stage_menit')
                                    ->pluck('total_stage_menit')
                                    ->max();
                            }
                            $spStNewMax = self::minutesToDays($maxSpStageMenit);

                            $spStageSum->jumlah_transaksi = $spStNewCount;
                            $spStageSum->total_menit = $spStNewMenit;
                            $spStageSum->average_hari = $spStNewAvg;
                            $spStageSum->max_hari = $spStNewMax;
                            $spStageSum->save();
                        }
                    }
                }
            }

            app(\App\Services\AnalisaService::class)->refreshAffectedProductsFromPo($distinctProductIds, $manager->id);
        });
    }
}

