<?php

namespace App\Services;

use App\Models\AnalisaFulfillmentInput;
use App\Models\AnalisaImpor;
use App\Models\AnalisaImporMeta;
use App\Models\AnalisaLokal;
use App\Models\AnalisaLokalInput;
use App\Models\KlasifikasiAbc;
use App\Models\LeadTimeImpor;
use App\Models\LeadTimeLokal;
use App\Models\LeadTimeLokalStage;
use App\Models\LeadTimeStage;
use App\Models\PoStage;
use App\Models\Produk;
use App\Models\PurchaseOrderItem;
use App\Models\RekomendasiOrderLokal;
use App\Models\RiwayatAnalisa;
use App\Models\Stok;
use App\Models\Supplier;
use App\Models\SupplierProdukLtStageSummary;
use App\Models\SupplierProdukLtSummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Replikasi rumus Analisa Stok dari 3 file Excel acuan (PRD Bab 6):
 * Bahan Lokal, Bahan Impor, Produk Jadi (Fulfillment).
 * Sumber tunggal Batas Minimum & Target Stock bagi seluruh sistem.
 */
class AnalisaService
{
    /**
     * Pemetaan nama stage Purchasing (PoStage) ke kolom tabel 9 tahap Analisa (LeadTimeLokalStage).
     *
     * @var array<string, string>
     */
    public const STAGE_KEY_MAP = [
        PoStage::STAGE_1 => 'perencanaan',
        PoStage::STAGE_2 => 'approval',
        PoStage::STAGE_3 => 'supplier_confirm',
        PoStage::STAGE_4 => 'payment',
        PoStage::STAGE_5 => 'po',
        PoStage::STAGE_6 => 'pengiriman',
        PoStage::STAGE_7 => 'unloading',
        PoStage::STAGE_8 => 'input',
    ];

    /** @var array<int, string> */
    public const IMPOR_PROFILES = ['impor', 'bahan_impor'];

    private ?DemandAduService $demandSvc = null;

    public static function isImporProfile(?string $profilAnalisa): bool
    {
        return in_array($profilAnalisa, self::IMPOR_PROFILES, true);
    }

    /**
     * Pembulatan hari lead time dengan toleransi strictly 1 angka desimal half-up.
     * Digit 2+ bernilai < 5 menjadi 0, >= 5 menjadi +0.1 (user override).
     */
    public static function roundLeadTimeDays(float $days): float
    {
        return round($days, 1, PHP_ROUND_HALF_UP);
    }

    /**
     * Resolusi Lead Time Lokal berdasarkan Produk & Supplier Terpilih (TC-16, user override).
     * Jika transaksi PO selesai >= 1 pada supplier_produk_lt_summary, gunakan data empiris otomatis (1 desimal half-up).
     * Jika belum ada data historis, fallback ke setting manual lead_time_lokal_stage.
     */
    public function resolveSupplierProdukLeadTimeLokal(int $produkId, ?int $supplierId = null): array
    {
        $produk = Produk::with(['activeSuppliers', 'supplier'])->find($produkId);
        if (! $produk) {
            return [
                'supplier_id' => null,
                'sumber_lt' => 'manual',
                'total_average_lead_time' => 0.0,
                'total_max_lead_time' => 0.0,
                'tambahan_buffer_hari' => 0.0,
                'safety_stock' => 0.0,
            ];
        }

        if (! $supplierId) {
            $existingAnalisa = AnalisaLokal::where('produk_id', $produkId)->first();
            $supplierId = $existingAnalisa?->supplier_terpilih_id 
                ?? $produk->supplier_id 
                ?? $produk->activeSuppliers->first()?->id;
        }

        $summary = null;
        if ($supplierId) {
            $summary = SupplierProdukLtSummary::where('supplier_id', $supplierId)
                ->where('produk_id', $produkId)
                ->first();
        }

        $existingLt = LeadTimeLokal::where('produk_id', $produkId)->first();
        $buffer = $existingLt && $existingLt->tambahan_buffer_hari !== null
            ? (float) $existingLt->tambahan_buffer_hari
            : (float) (Schema::hasTable('lead_time_stage') ? (DB::table('lead_time_stage')->where('produk_id', $produkId)->sum('tambahan_buffer_hari') ?? 0) : 0);

        $trxCount = (int) ($summary->jumlah_transaksi ?? $summary->total_po_selesai ?? 0);
        if ($summary && $trxCount >= 1) {
            $totalAvg = self::roundLeadTimeDays((float) ($summary->average_lt_hari ?? $summary->average_lt_days ?? 0));
            $totalMax = self::roundLeadTimeDays((float) ($summary->max_lt_hari ?? $summary->max_lt_days ?? 0));
            $sumberLt = 'otomatis';
        } else {
            $avgStage = LeadTimeLokalStage::where('produk_id', $produkId)->where('skenario', 'average')->first();
            $maxStage = LeadTimeLokalStage::where('produk_id', $produkId)->where('skenario', 'max')->first();
            $totalAvg = $avgStage ? $avgStage->totalHari() : (float) (LeadTimeLokal::where('produk_id', $produkId)->value('total_average_lead_time') ?? 0);
            $totalMax = $maxStage ? $maxStage->totalHari() : (float) (LeadTimeLokal::where('produk_id', $produkId)->value('total_max_lead_time') ?? 0);
            $sumberLt = 'manual';
        }

        $safetyStock = max(0.0, self::roundLeadTimeDays(($totalMax - $totalAvg) + $buffer));

        return [
            'supplier_id' => $supplierId,
            'sumber_lt' => $sumberLt,
            'total_average_lead_time' => self::roundLeadTimeDays($totalAvg),
            'total_max_lead_time' => self::roundLeadTimeDays($totalMax),
            'tambahan_buffer_hari' => self::roundLeadTimeDays($buffer),
            'safety_stock' => $safetyStock,
        ];
    }

    /**
     * Format satu skenario (average/max) dari model manual LeadTimeLokalStage.
     *
     * @return array<string, float|int|null>
     */
    public function formatManualStageScenario(?LeadTimeLokalStage $stage): array
    {
        $row = ['id' => $stage?->id];
        $sum = 0.0;

        foreach (LeadTimeLokalStage::STAGE_COLUMNS as $col) {
            $val = self::roundLeadTimeDays((float) ($stage?->{$col} ?? 0));
            $row[$col] = $val;
            $sum += $val;
        }

        $row['total'] = $stage ? self::roundLeadTimeDays((float) $stage->totalHari()) : self::roundLeadTimeDays($sum);

        return $row;
    }

    /**
     * Format satu skenario (average_hari / max_hari) dari ringkasan 8 stage Purchasing.
     *
     * @param  Collection<int, SupplierProdukLtStageSummary>  $empiricalRows
     * @return array<string, float|null>
     */
    public function formatEmpiricalStageScenario(Collection $empiricalRows, string $metricColumn): array
    {
        $byStage = $empiricalRows->keyBy('stage');
        $row = ['id' => null];
        $sum = 0.0;

        foreach (self::STAGE_KEY_MAP as $poStageKey => $analisaCol) {
            $val = self::roundLeadTimeDays((float) ($byStage->get($poStageKey)?->{$metricColumn} ?? 0));
            $row[$analisaCol] = $val;
            $sum += $val;
        }

        // PO arsip yang masih tercatat di tahap pengemasan dilebur ke pengiriman.
        $legacyPengemasan = self::roundLeadTimeDays((float) ($byStage->get(PoStage::LEGACY_STAGE_PENGEMASAN)?->{$metricColumn} ?? 0));
        if ($legacyPengemasan > 0) {
            $row['pengiriman'] = self::roundLeadTimeDays((float) $row['pengiriman'] + $legacyPengemasan);
            $sum += $legacyPengemasan;
        }

        $row['total'] = self::roundLeadTimeDays($sum);

        return $row;
    }

    /**
     * Resolusi rincian 8 tahap Lead Time untuk Tab 4 Analisa Lokal:
     * Prioritaskan ringkasan empiris PO (jumlah_transaksi >= 1) per supplier-produk,
     * dan fallback ke konfigurasi manual LeadTimeLokalStage tanpa menimpa baseline manual.
     *
     * @param  Collection<int, LeadTimeLokalStage>  $manualStages
     * @param  Collection<int, SupplierProdukLtStageSummary>  $empiricalStages
     * @return array<string, mixed>
     */
    public function resolveLokalStageBreakdown(Collection $manualStages, Collection $empiricalStages): array
    {
        $manualAvg = $this->formatManualStageScenario($manualStages->firstWhere('skenario', 'average'));
        $manualMax = $this->formatManualStageScenario($manualStages->firstWhere('skenario', 'max'));

        $activeEmpirical = $empiricalStages->filter(fn ($row) => (int) ($row->jumlah_transaksi ?? 0) >= 1);
        if ($activeEmpirical->isNotEmpty()) {
            return [
                'sumber_lt' => 'otomatis',
                'jumlah_transaksi' => (int) $activeEmpirical->max('jumlah_transaksi'),
                'average' => $this->formatEmpiricalStageScenario($activeEmpirical, 'average_hari'),
                'max' => $this->formatEmpiricalStageScenario($activeEmpirical, 'max_hari'),
                'manual_average' => $manualAvg,
                'manual_max' => $manualMax,
            ];
        }

        return [
            'sumber_lt' => 'manual',
            'jumlah_transaksi' => 0,
            'average' => $manualAvg,
            'max' => $manualMax,
            'manual_average' => $manualAvg,
            'manual_max' => $manualMax,
        ];
    }

    /**
     * Sinkronkan working data Analisa yang sudah ada setelah pencatatan / invalidasi Lead Time PO.
     * Hanya memperbarui produk yang sudah terinisiasi di working table Analisa agar tidak menimbulkan side-effect.
     *
     * @param  array<int, int|string>  $produkIds
     */
    public function refreshAffectedProductsFromPo(array $produkIds, ?int $userId = null): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $produkIds), fn ($id) => $id > 0)));
        if (empty($ids)) {
            return;
        }

        $products = Produk::whereIn('id', $ids)->where('is_active', true)->get();
        if ($products->isEmpty()) {
            return;
        }

        $imporIds = AnalisaImpor::whereIn('produk_id', $ids)->pluck('produk_id')->flip();
        $lokalIds = AnalisaLokal::whereIn('produk_id', $ids)->pluck('produk_id')->flip();
        $rekLokalIds = RekomendasiOrderLokal::whereIn('produk_id', $ids)->pluck('produk_id')->flip();

        foreach ($products as $p) {
            $isImpor = self::isImporProfile($p->profil_analisa) || $imporIds->has($p->id);
            if ($isImpor) {
                if ($imporIds->has($p->id)) {
                    $this->generateImpor($p->id, $userId);
                }
                continue;
            }

            if (in_array($p->tipe, ['bahan', 'kemas', 'bahan_baku', 'kemasan'], true)
                && ($lokalIds->has($p->id) || $rekLokalIds->has($p->id))) {
                $this->generateLokal($p->id, $userId);
            }
        }
    }

    /**
     * Resolusi Lead Time Impor berdasarkan Produk & Supplier Terpilih (TC-16, user override).
     */
    public function resolveSupplierProdukLeadTimeImpor(int $produkId, ?int $supplierId = null): array
    {
        $produk = Produk::with(['activeSuppliers', 'supplier'])->find($produkId);
        if (! $produk) {
            return [
                'supplier_id' => null,
                'sumber_lt' => 'manual',
                'lead_time_average' => 45.0,
                'lead_time_max' => 60.0,
            ];
        }

        if (! $supplierId) {
            $existingAnalisa = AnalisaImpor::where('produk_id', $produkId)->first();
            $supplierId = $existingAnalisa?->supplier_terpilih_id 
                ?? $produk->supplier_id 
                ?? $produk->activeSuppliers->first()?->id;
        }

        $summary = null;
        if ($supplierId) {
            $summary = SupplierProdukLtSummary::where('supplier_id', $supplierId)
                ->where('produk_id', $produkId)
                ->first();
        }

        $trxCount = (int) ($summary->jumlah_transaksi ?? $summary->total_po_selesai ?? 0);
        if ($summary && $trxCount >= 1) {
            $ltAvg = self::roundLeadTimeDays((float) ($summary->average_lt_hari ?? $summary->average_lt_days ?? 0));
            $ltMax = self::roundLeadTimeDays((float) ($summary->max_lt_hari ?? $summary->max_lt_days ?? 0));
            $sumberLt = 'otomatis';
        } else {
            $ltImpor = LeadTimeImpor::where('produk_id', $produkId)->first();
            $meta = AnalisaImporMeta::where('produk_id', $produkId)->first();
            $ltAvg = (float) ($ltImpor ? $ltImpor->lead_time_average : ($meta?->lead_time_average ?? 45));
            $ltMax = (float) ($ltImpor ? $ltImpor->lead_time_max : ($meta?->lead_time_max ?? 60));
            $sumberLt = 'manual';
        }

        return [
            'supplier_id' => $supplierId,
            'sumber_lt' => $sumberLt,
            'lead_time_average' => self::roundLeadTimeDays($ltAvg),
            'lead_time_max' => self::roundLeadTimeDays($ltMax),
        ];
    }

    /**
     * Kumpulkan seluruh kombinasi supplier per produk secara bulk (tanpa N+1):
     * gabungan relasi aktif `produk_supplier`, supplier terpilih/utama, serta seluruh
     * supplier yang pernah menyuplai produk di `supplier_produk_lt_summary` atau PO selesai.
     *
     * @param  array<int, int>  $produkIds
     * @param  array<int, int|null>  $selectedSupplierByProduk
     * @param  array<int, int|null>  $primarySupplierByProduk
     * @return array{
     *     by_produk: array<int, array<int, array{supplier_id: int|null, supplier_nama: string, supplier_kategori: string, relasi_status: string, is_active_supplier: bool}>>,
     *     lt_summaries: Collection<string, SupplierProdukLtSummary>
     * }
     */
    public function getProductSuppliersWithSummaries(
        array $produkIds,
        array $selectedSupplierByProduk = [],
        array $primarySupplierByProduk = []
    ): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $produkIds), fn ($id) => $id > 0)));
        if (empty($ids)) {
            return ['by_produk' => [], 'lt_summaries' => collect()];
        }

        // 1. Relasi master produk_supplier
        $pivotRows = DB::table('produk_supplier')
            ->whereIn('produk_id', $ids)
            ->get(['produk_id', 'supplier_id', 'status']);

        $pivotStatusMap = [];
        $activePivotByProduk = [];
        foreach ($pivotRows as $pr) {
            $pid = (int) $pr->produk_id;
            $sid = (int) $pr->supplier_id;
            $st = (string) ($pr->status ?: 'aktif');
            $pivotStatusMap[$pid . ':' . $sid] = $st;
            if ($st !== 'nonaktif') {
                $activePivotByProduk[$pid][$sid] = $st;
            }
        }

        // 2. Ringkasan empiris LT per supplier-produk (semua supplier yang punya transaksi)
        $ltSummaries = SupplierProdukLtSummary::whereIn('produk_id', $ids)->get();
        $ltSummaryMap = $ltSummaries->keyBy(fn ($row) => ((int) $row->supplier_id) . ':' . ((int) $row->produk_id));

        $historySuppliersByProduk = [];
        foreach ($ltSummaries as $row) {
            if ((int) ($row->jumlah_transaksi ?? 0) >= 1) {
                $historySuppliersByProduk[(int) $row->produk_id][(int) $row->supplier_id] = true;
            }
        }

        // 3. Riwayat PO selesai (jaga-jaga bila ada PO selesai tanpa baris summary)
        $completedPoPairs = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.po_id')
            ->whereIn('poi.produk_id', $ids)
            ->where('po.status', 'selesai')
            ->whereNotNull('po.supplier_id')
            ->select('poi.produk_id', 'po.supplier_id')
            ->distinct()
            ->get();

        foreach ($completedPoPairs as $pair) {
            $historySuppliersByProduk[(int) $pair->produk_id][(int) $pair->supplier_id] = true;
        }

        // 4. Gabungkan seluruh supplier ID unik per produk
        $supplierIdsByProduk = [];
        $allSupplierIds = [];
        foreach ($ids as $pid) {
            $selectedSid = ! empty($selectedSupplierByProduk[$pid]) ? (int) $selectedSupplierByProduk[$pid] : null;
            $primarySid = ! empty($primarySupplierByProduk[$pid]) ? (int) $primarySupplierByProduk[$pid] : null;

            $collected = [];

            if ($selectedSid) {
                $collected[$selectedSid] = $pivotStatusMap[$pid . ':' . $selectedSid] ?? 'aktif';
            }

            foreach (($activePivotByProduk[$pid] ?? []) as $sid => $st) {
                if (! isset($collected[$sid])) {
                    $collected[$sid] = $st;
                }
            }

            if ($primarySid && ! isset($collected[$primarySid])) {
                $primaryPivotStatus = $pivotStatusMap[$pid . ':' . $primarySid] ?? null;
                if ($primaryPivotStatus !== 'nonaktif') {
                    $collected[$primarySid] = $primaryPivotStatus ?? 'aktif';
                }
            }

            foreach (($historySuppliersByProduk[$pid] ?? []) as $sid => $_) {
                if (! isset($collected[$sid])) {
                    $collected[$sid] = $pivotStatusMap[$pid . ':' . $sid] ?? 'riwayat_po';
                }
            }

            $supplierIdsByProduk[$pid] = $collected;
            foreach (array_keys($collected) as $sid) {
                $allSupplierIds[$sid] = true;
            }
        }

        $suppliersMap = empty($allSupplierIds)
            ? collect()
            : Supplier::whereIn('id', array_keys($allSupplierIds))
                ->get(['id', 'nama', 'kategori', 'is_active'])
                ->keyBy('id');

        $byProduk = [];
        foreach ($ids as $pid) {
            $selectedSid = ! empty($selectedSupplierByProduk[$pid]) ? (int) $selectedSupplierByProduk[$pid] : null;
            $entries = [];

            foreach ($supplierIdsByProduk[$pid] as $sid => $relStatus) {
                $sup = $suppliersMap->get($sid);
                if (! $sup) {
                    continue;
                }
                $entries[] = [
                    'supplier_id' => (int) $sup->id,
                    'supplier_nama' => (string) $sup->nama,
                    'supplier_kategori' => (string) ($sup->kategori ?? '-'),
                    'relasi_status' => (string) $relStatus,
                    'is_active_supplier' => $selectedSid !== null && (int) $sup->id === $selectedSid,
                ];
            }

            // Urutkan: supplier aktif/terpilih di atas, lalu alfabet nama supplier
            usort($entries, function (array $a, array $b) {
                if ($a['is_active_supplier'] !== $b['is_active_supplier']) {
                    return $a['is_active_supplier'] ? -1 : 1;
                }

                return strcmp($a['supplier_nama'], $b['supplier_nama']);
            });

            // Jika belum ada supplier sama sekali, tetap tampilkan 1 baris produk
            if (empty($entries)) {
                $entries[] = [
                    'supplier_id' => null,
                    'supplier_nama' => '-',
                    'supplier_kategori' => '-',
                    'relasi_status' => 'belum_ada',
                    'is_active_supplier' => true,
                ];
            }

            $byProduk[$pid] = $entries;
        }

        return [
            'by_produk' => $byProduk,
            'lt_summaries' => $ltSummaryMap,
        ];
    }

    /**
     * Susun baris Working Data Analisa (ADU) Lokal untuk SELURUH kombinasi supplier-produk.
     * ADU dihitung murni per produk (terjual_rata_rata_4bulan / 30), tidak bergantung pada supplier;
     * setiap baris supplier memakai Lead Time & Safety Stock masing-masing.
     *
     * @param  Collection<int, RekomendasiOrderLokal>  $rekomendasi
     * @param  Collection<int, AnalisaLokal>  $analisaMap
     * @param  Collection<int, LeadTimeLokal>  $leadTimeMap
     * @param  Collection<int, Collection<int, LeadTimeLokalStage>>  $stagesMap
     * @param  array<int, float>|Collection<int, float>  $actualStockMap
     * @param  array<int, float>  $hppMap
     * @return array<int, array<string, mixed>>
     */
    public function buildLokalSupplierAduRows(
        Collection $rekomendasi,
        Collection $analisaMap,
        Collection $leadTimeMap,
        Collection $stagesMap,
        $actualStockMap,
        array $hppMap
    ): array {
        $produkIds = $rekomendasi->pluck('produk_id')->map(fn ($id) => (int) $id)->all();
        if (empty($produkIds)) {
            return [];
        }

        $selectedByProduk = [];
        $primaryByProduk = [];
        foreach ($rekomendasi as $rek) {
            $pid = (int) $rek->produk_id;
            $al = $analisaMap->get($pid);
            $primarySid = $rek->produk?->supplier_id ? (int) $rek->produk->supplier_id : null;
            $selectedByProduk[$pid] = $al?->supplier_terpilih_id ? (int) $al->supplier_terpilih_id : $primarySid;
            $primaryByProduk[$pid] = $primarySid;
        }

        $supplierData = $this->getProductSuppliersWithSummaries($produkIds, $selectedByProduk, $primaryByProduk);
        $byProduk = $supplierData['by_produk'];
        $ltSummaries = $supplierData['lt_summaries'];

        $rows = [];
        foreach ($rekomendasi as $rek) {
            $pid = (int) $rek->produk_id;
            $al = $analisaMap->get($pid);
            $lt = $leadTimeMap->get($pid);

            // ADU murni per produk (tidak bergantung supplier)
            $terjual = (float) ($al?->terjual_rata_rata_4bulan ?? 0);
            $aduRaw = $terjual / 30;
            $reviewPeriod = (int) ($al?->review_period ?? 15);
            if ($reviewPeriod <= 0) {
                $reviewPeriod = 15;
            }
            $buffer = self::roundLeadTimeDays((float) ($lt?->tambahan_buffer_hari ?? 0));

            // Fallback manual LT per produk dari stage manual atau lead_time_lokal
            $prodStages = $stagesMap->get($pid) ?? collect();
            $avgStage = $prodStages->firstWhere('skenario', 'average');
            $maxStage = $prodStages->firstWhere('skenario', 'max');
            $manualAvgLt = $avgStage ? (float) $avgStage->totalHari() : (float) ($lt?->total_average_lead_time ?? $al?->total_average_lead_time ?? 0);
            $manualMaxLt = $maxStage ? (float) $maxStage->totalHari() : (float) ($lt?->total_max_lead_time ?? 0);

            $supplierEntries = $byProduk[$pid] ?? [];
            foreach ($supplierEntries as $entry) {
                $sid = $entry['supplier_id'];
                $summary = $sid ? $ltSummaries->get($sid . ':' . $pid) : null;
                $trxCount = $summary ? (int) ($summary->jumlah_transaksi ?? 0) : 0;

                if ($summary && $trxCount >= 1) {
                    $avgLt = self::roundLeadTimeDays((float) ($summary->average_lt_hari ?? 0));
                    $maxLt = self::roundLeadTimeDays((float) ($summary->max_lt_hari ?? 0));
                    $sumberLt = 'otomatis';
                } else {
                    $avgLt = self::roundLeadTimeDays($manualAvgLt);
                    $maxLt = self::roundLeadTimeDays($manualMaxLt);
                    $sumberLt = 'manual';
                }

                $safetyStock = max(0.0, self::roundLeadTimeDays(($maxLt - $avgLt) + $buffer));
                $batasMinimum = round($aduRaw * ($avgLt + $safetyStock));
                $targetStock = round($aduRaw * ($avgLt + $safetyStock + $reviewPeriod));

                $rows[] = [
                    'row_key' => $pid . '_' . ($sid ?? 'none'),
                    'id' => $rek->id,
                    'produk_id' => $pid,
                    'sku' => $rek->produk?->sku,
                    'nama' => $rek->produk?->nama,
                    'satuan' => $rek->produk?->satuan,
                    'satuan_order_moq' => (float) ($rek->produk?->satuan_order_moq ?: 1),
                    'faktor_konversi' => (float) ($rek->produk?->faktor_konversi ?: 1),
                    'supplier_id' => $sid,
                    'supplier_nama' => $entry['supplier_nama'],
                    'supplier_kategori' => $entry['supplier_kategori'],
                    'relasi_status' => $entry['relasi_status'],
                    'is_active_supplier' => $entry['is_active_supplier'],
                    'sumber_lt' => $sumberLt,
                    'total_po_selesai' => $trxCount,
                    'total_avg_lead_time' => $avgLt,
                    'total_max_lead_time' => $maxLt,
                    'tambahan_buffer_hari' => $buffer,
                    'safety_stock' => $safetyStock,
                    'terjual_rata_rata_4bulan' => (float) round($terjual),
                    'adu' => (float) round($aduRaw),
                    'adu_exact' => round($aduRaw, 4),
                    'review_period' => $reviewPeriod,
                    'batas_minimum' => (float) $batasMinimum,
                    'target_stock' => (float) $targetStock,
                    'stok_saat_ini' => (float) round($rek->stok_saat_ini),
                    'akan_datang' => (float) round($rek->akan_datang),
                    'harga_per_satuan' => (float) round($rek->harga_ml_pcs),
                    'current_hpp' => (float) ($hppMap[$pid] ?? $rek->produk?->harga_hpp ?? 0),
                    'current_stok_aktual' => (float) ($actualStockMap[$pid] ?? 0),
                    'current_terjual_sistem' => (float) round($terjual),
                    'generated_at' => $rek->generated_at?->format('d/m/Y H:i'),
                ];
            }
        }

        return $rows;
    }

    /**
     * Susun baris Working Data Analisa (ADU) Impor untuk SELURUH kombinasi supplier-produk.
     * ADU dihitung murni per produk (out / jumlah_hari_periode), tidak bergantung pada supplier;
     * setiap baris supplier memakai Lead Time, Buffer Days, Safety Stock, Min Stock, & Target Stock masing-masing.
     *
     * @param  Collection<int, AnalisaImpor>  $imporList
     * @param  Collection<int, LeadTimeImpor>  $ltMap
     * @param  array<int, float>|Collection<int, float>  $actualStockMap
     * @param  array<int, float>  $hppMap
     * @param  array<int, float>|Collection<int, float>  $inboundPoMap
     * @return array<int, array<string, mixed>>
     */
    public function buildImporSupplierAduRows(
        Collection $imporList,
        Collection $ltMap,
        $actualStockMap,
        array $hppMap,
        $inboundPoMap
    ): array {
        $produkIds = $imporList->pluck('produk_id')->map(fn ($id) => (int) $id)->all();
        if (empty($produkIds)) {
            return [];
        }

        $selectedByProduk = [];
        $primaryByProduk = [];
        foreach ($imporList as $ai) {
            $pid = (int) $ai->produk_id;
            $primarySid = $ai->produk?->supplier_id ? (int) $ai->produk->supplier_id : null;
            $selectedByProduk[$pid] = $ai->supplier_terpilih_id ? (int) $ai->supplier_terpilih_id : $primarySid;
            $primaryByProduk[$pid] = $primarySid;
        }

        $supplierData = $this->getProductSuppliersWithSummaries($produkIds, $selectedByProduk, $primaryByProduk);
        $byProduk = $supplierData['by_produk'];
        $ltSummaries = $supplierData['lt_summaries'];

        $rows = [];
        foreach ($imporList as $ai) {
            $pid = (int) $ai->produk_id;
            $lt = $ltMap->get($pid);

            $out = (float) ($ai->out ?? 0);
            $jumlahHari = (int) ($ai->jumlah_hari_periode ?: 122);
            if ($jumlahHari <= 0) {
                $jumlahHari = 122;
            }
            // ADU murni per produk (tidak bergantung supplier)
            $aduRaw = $jumlahHari > 0 ? ($out / $jumlahHari) : 0.0;

            $reviewPeriod = (int) ($ai->review_period ?: 30);
            if ($reviewPeriod <= 0) {
                $reviewPeriod = 30;
            }
            $tambahanHari = (float) ($ai->tambahan_buffer_hari ?? 0);

            $fallbackAvgLt = self::roundLeadTimeDays((float) ($lt?->lead_time_average ?? $ai->lead_time ?? 45));
            $fallbackMaxLt = self::roundLeadTimeDays((float) ($lt?->lead_time_max ?? 60));

            $supplierEntries = $byProduk[$pid] ?? [];
            foreach ($supplierEntries as $entry) {
                $sid = $entry['supplier_id'];
                $summary = $sid ? $ltSummaries->get($sid . ':' . $pid) : null;
                $trxCount = $summary ? (int) ($summary->jumlah_transaksi ?? 0) : 0;

                if ($summary && $trxCount >= 1) {
                    $avgLt = self::roundLeadTimeDays((float) ($summary->average_lt_hari ?? 0));
                    $maxLt = self::roundLeadTimeDays((float) ($summary->max_lt_hari ?? 0));
                    $sumberLt = 'otomatis';
                } else {
                    $avgLt = $fallbackAvgLt;
                    $maxLt = $fallbackMaxLt;
                    $sumberLt = 'manual';
                }

                $bufferDays = self::roundLeadTimeDays(max(0.0, $maxLt - $avgLt) + $tambahanHari);
                // ADU ETA bergantung lead time supplier (pivot komposisi baru selama menunggu barang tiba).
                $aduEtaSupplier = self::hitungAduEta($aduRaw, (float) ($ai->adu_pivot ?? 0), $avgLt);
                $safetyStock = round($aduEtaSupplier * $bufferDays);
                $minimumStock = round($aduEtaSupplier * $avgLt);
                $targetStock = (int) floor($aduEtaSupplier * ($avgLt + $bufferDays + $reviewPeriod) + 0.0001);

                $rows[] = [
                    'row_key' => $pid . '_' . ($sid ?? 'none'),
                    'id' => (string) $ai->id,
                    'analisa_impor_id' => $ai->id,
                    'produk_id' => $pid,
                    'sku' => $ai->produk?->sku,
                    'nama' => $ai->produk?->nama,
                    'satuan' => $ai->produk?->satuan,
                    'satuan_order_moq' => $ai->produk?->satuan_order_moq,
                    'faktor_konversi' => (float) ($ai->produk?->faktor_konversi ?: 1),
                    'supplier_id' => $sid,
                    'supplier_nama' => $entry['supplier_nama'],
                    'supplier_kategori' => $entry['supplier_kategori'],
                    'relasi_status' => $entry['relasi_status'],
                    'is_active_supplier' => $entry['is_active_supplier'],
                    'sumber_lt' => $sumberLt,
                    'total_po_selesai' => $trxCount,
                    'periode_mulai' => $ai->periode_mulai?->format('Y-m-d'),
                    'periode_akhir' => $ai->periode_akhir?->format('Y-m-d'),
                    'jumlah_hari_periode' => $jumlahHari,
                    'out' => (float) round($out),
                    'adu' => (float) round($aduRaw, 2),
                    'adu_base' => round($aduRaw, 4),
                    'adu_pivot' => round((float) ($ai->adu_pivot ?? 0), 4),
                    'adu_eta' => round($aduEtaSupplier, 4),
                    'review_period' => $reviewPeriod,
                    'klasifikasi_abc' => $ai->klasifikasi_abc,
                    'klasifikasi_abc_id' => $ai->klasifikasi_abc_id,
                    'klasifikasi_abc_nama' => $ai->klasifikasiAbc?->nama ?? ($ai->klasifikasi_abc_id ? strtoupper($ai->klasifikasi_abc) : 'Default (Belum Diset)'),
                    'tambahan_buffer_hari' => (int) round($tambahanHari),
                    'lead_time_average' => $avgLt,
                    'lead_time_max' => $maxLt,
                    'buffer_days' => $bufferDays,
                    'safety_stock' => (float) $safetyStock,
                    'minimum_stock' => (float) $minimumStock,
                    'target_stock' => (float) $targetStock,
                    'stok_saat_ini' => (float) round($ai->stok_saat_ini),
                    'inbound_before_eta' => (float) round($ai->inbound_before_eta),
                    'harga_per_satuan' => (float) round($ai->harga_per_satuan),
                    'current_hpp' => (float) ($hppMap[$pid] ?? $ai->produk?->harga_hpp ?? 0),
                    'current_stok_aktual' => (float) ($actualStockMap[$pid] ?? 0),
                    'current_inbound' => (float) ($inboundPoMap[$pid] ?? 0),
                    'empirical_avg_lt' => $trxCount >= 1 ? $avgLt : ($lt?->lead_time_average !== null ? (float) $lt->lead_time_average : null),
                    'empirical_max_lt' => $trxCount >= 1 ? $maxLt : ($lt?->lead_time_max !== null ? (float) $lt->lead_time_max : null),
                    'generated_at' => $ai->generated_at?->format('d/m/Y H:i'),
                ];
            }
        }

        return $rows;
    }

    /**
     * Perbandingan multi-supplier untuk satu produk (Bab 5, perbandingan vendor).
     * ADU murni per produk (tidak bergantung supplier), sedangkan Lead Time & Buffer per supplier.
     */
    public function compareSuppliersForProduct(Produk $produk): array
    {
        $currentLokal = AnalisaLokal::where('produk_id', $produk->id)->first();
        $currentImpor = AnalisaImpor::where('produk_id', $produk->id)->first();
        $currentSupplierId = $currentLokal?->supplier_terpilih_id ?? $currentImpor?->supplier_terpilih_id ?? $produk->supplier_id;

        $supplierData = $this->getProductSuppliersWithSummaries(
            [$produk->id],
            [$produk->id => $currentSupplierId ? (int) $currentSupplierId : null],
            [$produk->id => $produk->supplier_id ? (int) $produk->supplier_id : null]
        );
        $entries = array_values(array_filter(
            $supplierData['by_produk'][$produk->id] ?? [],
            fn (array $e) => ! empty($e['supplier_id'])
        ));
        $ltSummaries = $supplierData['lt_summaries'];

        $isImpor = self::isImporProfile($produk->profil_analisa) || $currentImpor !== null;

        $adu = 0.0;
        $reviewPeriod = 15;
        $tambahanAbc = 0.0;

        if ($isImpor) {
            $meta = AnalisaImporMeta::where('produk_id', $produk->id)->first();
            // ADU murni per produk dari permintaan produksi 4 bulan, tidak bergantung pada supplier
            $adu = $this->pusatAdu($produk->id);
            $reviewPeriod = (int) ($currentImpor?->review_period ?? $meta?->review_period ?? 30);
            $tambahanAbc = (float) ($currentImpor?->tambahan_buffer_hari ?? 0);
        } else {
            $inputLokal = AnalisaLokalInput::where('produk_id', $produk->id)->first();
            $adu = (float) ($currentLokal?->adu ?? $this->pusatAduLokal($produk->id));
            $reviewPeriod = (int) ($currentLokal?->review_period ?? $inputLokal?->review_period ?? 15);
        }
        if ($reviewPeriod <= 0) {
            $reviewPeriod = $isImpor ? 30 : 15;
        }

        $result = [];
        foreach ($entries as $entry) {
            $sid = (int) $entry['supplier_id'];
            $summary = $ltSummaries->get($sid . ':' . $produk->id);
            $trxCount = $summary ? (int) ($summary->jumlah_transaksi ?? 0) : 0;

            if ($isImpor) {
                $resolvedLt = $this->resolveSupplierProdukLeadTimeImpor($produk->id, $sid);
                $avgLt = $summary && $trxCount >= 1
                    ? self::roundLeadTimeDays((float) ($summary->average_lt_hari ?? 0))
                    : $resolvedLt['lead_time_average'];
                $maxLt = $summary && $trxCount >= 1
                    ? self::roundLeadTimeDays((float) ($summary->max_lt_hari ?? 0))
                    : $resolvedLt['lead_time_max'];

                $simBufferDays = self::roundLeadTimeDays(max(0.0, $maxLt - $avgLt) + $tambahanAbc);
                $simBatasMin = round($adu * $avgLt);
                $simTargetStock = (int) floor($adu * ($avgLt + $simBufferDays + $reviewPeriod) + 0.0001);
            } else {
                $resolvedLt = $this->resolveSupplierProdukLeadTimeLokal($produk->id, $sid);
                $avgLt = $summary && $trxCount >= 1
                    ? self::roundLeadTimeDays((float) ($summary->average_lt_hari ?? 0))
                    : $resolvedLt['total_average_lead_time'];
                $maxLt = $summary && $trxCount >= 1
                    ? self::roundLeadTimeDays((float) ($summary->max_lt_hari ?? 0))
                    : $resolvedLt['total_max_lead_time'];
                $safetyStock = $resolvedLt['safety_stock'];

                $simBatasMin = round($adu * ($avgLt + $safetyStock));
                $simTargetStock = round($adu * ($avgLt + $safetyStock + $reviewPeriod));
            }

            $sumberLt = $trxCount > 0 ? 'otomatis' : 'manual';
            $sumberLtLabel = $trxCount > 0 ? 'Riwayat Empiris PO' : 'Manual (Fallback)';

            $harga = (float) ($produk->harga_hpp ?: 0);
            $minOrder = (float) ($produk->satuan_order_moq ?: 1);

            $result[] = [
                'supplier_id' => $sid,
                'supplier_nama' => $entry['supplier_nama'],
                'is_current' => (int) $sid === (int) $currentSupplierId,
                'adu' => round($adu, 4),
                'harga_terakhir' => $harga,
                'min_order_qty' => $minOrder,
                'avg_lt_days' => $avgLt,
                'average_lt_days' => $avgLt,
                'max_lt_days' => $maxLt,
                'total_po_selesai' => $trxCount,
                'sumber_lt' => $sumberLt,
                'sumber_lt_label' => $sumberLtLabel,
                'is_fallback' => $trxCount === 0,
                'status' => $entry['relasi_status'],
                'batas_minimum' => (float) $simBatasMin,
                'target_stock' => (float) $simTargetStock,
                'simulasi_batas_minimum' => (float) $simBatasMin,
                'simulasi_target_stock' => (float) $simTargetStock,
            ];
        }

        return $result;
    }

    /**
     * Pilih Supplier untuk Produk & Recalculate (TC-31).
     * Strictly maintains 1 active row per produk_id in working table.
     */
    public function selectSupplierForProduct(int $produkId, int $supplierId, ?int $userId = null): array
    {
        $produk = Produk::findOrFail($produkId);
        $isImpor = self::isImporProfile($produk->profil_analisa) || AnalisaImpor::where('produk_id', $produkId)->exists();

        if ($isImpor) {
            $lt = $this->resolveSupplierProdukLeadTimeImpor($produkId, $supplierId);
            LeadTimeImpor::updateOrCreate(
                ['produk_id' => $produkId],
                [
                    'lead_time_average' => $lt['lead_time_average'],
                    'lead_time_max' => $lt['lead_time_max'],
                ]
            );
            AnalisaImpor::where('produk_id', $produkId)->update([
                'supplier_terpilih_id' => $supplierId,
                'sumber_lt' => $lt['sumber_lt'],
            ]);

            return $this->generateImpor($produkId, $userId);
        } else {
            $lt = $this->resolveSupplierProdukLeadTimeLokal($produkId, $supplierId);
            LeadTimeLokal::updateOrCreate(
                ['produk_id' => $produkId],
                [
                    'total_average_lead_time' => $lt['total_average_lead_time'],
                    'total_max_lead_time' => $lt['total_max_lead_time'],
                    'safety_stock' => $lt['safety_stock'],
                ]
            );
            AnalisaLokal::where('produk_id', $produkId)->update([
                'supplier_terpilih_id' => $supplierId,
                'sumber_lt' => $lt['sumber_lt'],
            ]);

            return $this->generateLokal($produkId, $userId);
        }
    }

    /**
     * Engine Generate Analisa Lokal:
     * Menghitung dan menyimpan hasil ke 4 tabel data kerja:
     * 1. lead_time_lokal_stage (rincian 9 tahap lead time per skenario)
     * 2. lead_time_lokal (ringkasan total avg, max, buffer, safety stock)
     * 3. analisa_lokal (working data: adu, batas min, target stock)
     * 4. rekomendasi_order_lokal (working data: stok saat ini, akan datang, selisih, rumus moq, rekomendasi order dalam satuan beli, nominal)
     *
     * @param  int|null  $produkId  Jika null, generate untuk seluruh produk bahan lokal aktif
     * @param  int|null  $userId    User ID pembuat generate
     * @return array{generated_count: int, timestamp: string, items: array}
     */
    /**
     * Stok Saat Ini untuk analisa bahan: saldo fisik di gudang bertipe bahan_baku saja (GD-PUSAT dst.).
     * Stok bahan di Lab/Operasional tidak dihitung: sudah dialokasikan untuk produksi dan
     * pemakaiannya sudah menjadi sumber ADU, kekurangannya diisi lewat Request/Transfer.
     */
    public function stokGudangBahanBaku(int $produkId): float
    {
        return (float) Stok::where('produk_id', $produkId)
            ->whereIn('gudang_id', \App\Models\Gudang::where('tipe', 'bahan_baku')->select('id'))
            ->sum('qty_saat_ini');
    }

    /**
     * Akan Datang ke Gudang Bahan Baku (satuan dasar):
     *  - PO yang sudah disetujui Manager & belum diposting (draft / menunggu approval tidak dihitung),
     *  - transfer berstatus dikirim (in-transit) menuju gudang bahan baku, mis. retur bahan dari Lab.
     */
    public function akanDatangGudangBahanBaku(int $produkId): float
    {
        $gudangBahanBaku = \App\Models\Gudang::where('tipe', 'bahan_baku')->select('id');

        $po = (float) PurchaseOrderItem::where('produk_id', $produkId)
            ->whereHas('purchaseOrder', fn ($q) => $q->disetujuiBelumDiterima()
                ->where(fn ($g) => $g->whereNull('purchase_orders.gudang_id')
                    ->orWhereIn('purchase_orders.gudang_id', $gudangBahanBaku)))
            ->sum('qty');

        $transfer = (float) DB::table('transfer_items as ti')
            ->join('transfers as t', 't.id', '=', 'ti.transfer_id')
            ->where('t.status', 'dikirim')
            ->where('ti.produk_id', $produkId)
            ->whereIn('t.gudang_tujuan_id', \App\Models\Gudang::where('tipe', 'bahan_baku')->select('id'))
            ->sum('ti.qty_dikirim');

        return $po + $transfer;
    }

    /**
     * @param  array<int, array{stok_saat_ini?: float, akan_datang?: float}>  $overrides  koreksi manual sekali pakai per produk
     */
    public function generateLokal(?int $produkId = null, ?int $userId = null, array $overrides = []): array
    {
        $query = Produk::query()->where('is_active', true);
        if ($produkId) {
            $query->where('id', $produkId);
        } else {
            $query->whereIn('tipe', ['bahan', 'kemas'])
                // Keanggotaan eksplisit: SKU baru tidak otomatis masuk; daftarkan lewat Add SKU.
                ->where('profil_analisa', 'lokal');
        }

        $products = $query->orderBy('nama')->get();
        $generated = [];
        $now = now();
        // ADU Gudang Pusat otomatis dari permintaan bahan baku Gudang Produksi (4 bulan terakhir)
        $demandMap = $this->demand()->aduBahanPusat($products->pluck('id')->map(fn ($id) => (int) $id)->all());

        foreach ($products as $p) {
            // 1. Stage Lead Time (9 stages per scenario)
            $avgStage = LeadTimeLokalStage::where('produk_id', $p->id)->where('skenario', 'average')->first();
            $maxStage = LeadTimeLokalStage::where('produk_id', $p->id)->where('skenario', 'max')->first();

            // Jika belum ada di tabel baru, cek fallback dari lead_time_stage lama
            if (! $avgStage && ! $maxStage && Schema::hasTable('lead_time_stage')) {
                $oldAvg = LeadTimeStage::where('produk_id', $p->id)->where('skenario', 'average')->get()->keyBy('tahap');
                $oldMax = LeadTimeStage::where('produk_id', $p->id)->where('skenario', 'max')->get()->keyBy('tahap');
                if ($oldAvg->isNotEmpty() || $oldMax->isNotEmpty()) {
                    $avgStage = LeadTimeLokalStage::create([
                        'produk_id' => $p->id,
                        'skenario' => 'average',
                        'perencanaan' => (int) ($oldAvg['perencanaan']->jumlah_hari ?? 0),
                        'approval' => (int) ($oldAvg['approval']->jumlah_hari ?? 0),
                        'supplier_confirm' => (int) ($oldAvg['supplier_confirm']->jumlah_hari ?? 0),
                        'payment' => (int) ($oldAvg['payment']->jumlah_hari ?? 0),
                        'po' => (int) ($oldAvg['po']->jumlah_hari ?? 0),
                        'pengiriman' => (int) (($oldAvg['pengiriman']->jumlah_hari ?? 0) + ($oldAvg['pengemasan']->jumlah_hari ?? 0)),
                        'unloading' => (int) ($oldAvg['unloading']->jumlah_hari ?? 0),
                        'input' => (int) ($oldAvg['input']->jumlah_hari ?? 0),
                    ]);
                    $maxStage = LeadTimeLokalStage::create([
                        'produk_id' => $p->id,
                        'skenario' => 'max',
                        'perencanaan' => (int) ($oldMax['perencanaan']->jumlah_hari ?? 0),
                        'approval' => (int) ($oldMax['approval']->jumlah_hari ?? 0),
                        'supplier_confirm' => (int) ($oldMax['supplier_confirm']->jumlah_hari ?? 0),
                        'payment' => (int) ($oldMax['payment']->jumlah_hari ?? 0),
                        'po' => (int) ($oldMax['po']->jumlah_hari ?? 0),
                        'pengiriman' => (int) (($oldMax['pengiriman']->jumlah_hari ?? 0) + ($oldMax['pengemasan']->jumlah_hari ?? 0)),
                        'unloading' => (int) ($oldMax['unloading']->jumlah_hari ?? 0),
                        'input' => (int) ($oldMax['input']->jumlah_hari ?? 0),
                    ]);
                }
            }

            // 2. Summary Lead Time & Safety Stock via multi-supplier resolution
            $existingAnalisa = AnalisaLokal::where('produk_id', $p->id)->first();
            $ltData = $this->resolveSupplierProdukLeadTimeLokal($p->id, $existingAnalisa?->supplier_terpilih_id);
            $totalAvg = $ltData['total_average_lead_time'];
            $totalMax = $ltData['total_max_lead_time'];
            $buffer = $ltData['tambahan_buffer_hari'];
            $safetyStock = $ltData['safety_stock'];
            $supplierTerpilihId = $ltData['supplier_id'];
            $sumberLt = $ltData['sumber_lt'];

            $ltLokal = LeadTimeLokal::firstOrNew(['produk_id' => $p->id]);
            $ltLokal->fill([
                'total_average_lead_time' => $totalAvg,
                'total_max_lead_time' => $totalMax,
                'tambahan_buffer_hari' => $buffer,
                'safety_stock' => $safetyStock,
            ])->save();

            // 3. Analisa Lokal (Working Table)
            $existingInput = AnalisaLokalInput::where('produk_id', $p->id)->first();

            // ADU lokal sesuai Excel: (pemakaian 4 bulan ÷ 4) ÷ 30
            $adu = (float) ($demandMap[(int) $p->id]['adu_lokal'] ?? 0.0);
            $terjual = $adu * 30; // ekuivalen rata-rata per bulan (ADU otomatis dari permintaan produksi)
            $reviewPeriod = (int) ($existingInput?->review_period ?? $existingAnalisa?->review_period ?? 15);
            if ($reviewPeriod <= 0) {
                $reviewPeriod = 15;
            }

            $batasMinimum = $adu * ($totalAvg + $safetyStock);
            $targetStock = $adu * ($totalAvg + $safetyStock + $reviewPeriod);

            $analisaLokal = AnalisaLokal::updateOrCreate(
                ['produk_id' => $p->id],
                [
                    'supplier_terpilih_id' => $supplierTerpilihId,
                    'sumber_lt' => $sumberLt,
                    'total_average_lead_time' => $totalAvg,
                    'safety_stock' => $safetyStock,
                    'terjual_rata_rata_4bulan' => $terjual,
                    'adu' => $adu,
                    'review_period' => $reviewPeriod,
                    'batas_minimum' => $batasMinimum,
                    'target_stock' => $targetStock,
                    'generated_at' => $now,
                    'generated_by' => $userId ?? auth()->id(),
                ]
            );

            // 4. Rekomendasi Order Lokal
            // Stok & akan datang selalu live dari Gudang Bahan Baku. Koreksi manual (dari modal Input Manual)
            // hanya berlaku untuk perhitungan ini; Checking/Generate berikutnya kembali membaca data live.
            $override = $overrides[(int) $p->id] ?? [];
            $stokSaatIni = array_key_exists('stok_saat_ini', $override)
                ? (float) $override['stok_saat_ini']
                : $this->stokGudangBahanBaku((int) $p->id);
            $akanDatang = array_key_exists('akan_datang', $override)
                ? (float) $override['akan_datang']
                : $this->akanDatangGudangBahanBaku((int) $p->id);
            // Kolom stok_saat_ini / akan_datang lama di analisa_lokal_input sengaja diabaikan (tidak lagi dipakai).

            // Sesuai Excel Analisa Lokal: ORDER bila Stok + Akan Datang ≤ Target Stock; qty menutup sampai Target.
            $tersedia = $stokSaatIni + $akanDatang;
            $isOrder = $targetStock > 0 && $tersedia <= $targetStock;
            $selisih = $tersedia - $targetStock;
            $status = $isOrder ? 'order' : 'tidak';

            $moq = (float) ($p->satuan_order_moq ?: 1);
            $rumusMoq = $isOrder ? $this->bulatkanMoq(abs($selisih), $moq) : 0.0;

            $faktor = (float) ($p->faktor_konversi ?: 1);
            $rekomendasiOrder = ($faktor > 0) ? round($rumusMoq / $faktor) : $rumusMoq;

            $hargaSatuan = (float) ($existingInput?->harga_per_satuan ?: ($p->harga_hpp ?: 0));
            $totalNominal = round($rumusMoq * $hargaSatuan);

            $rekLokal = RekomendasiOrderLokal::updateOrCreate(
                ['produk_id' => $p->id],
                [
                    'batas_minimum' => $batasMinimum,
                    'target_stock' => $targetStock,
                    'stok_saat_ini' => $stokSaatIni,
                    'akan_datang' => $akanDatang,
                    'selisih' => $selisih,
                    'rumus_moq' => $rumusMoq,
                    'status' => $status,
                    'rekomendasi_order' => $rekomendasiOrder,
                    'harga_ml_pcs' => $hargaSatuan,
                    'total_nominal_order' => $totalNominal,
                    'generated_at' => $now,
                    'generated_by' => $userId ?? auth()->id(),
                ]
            );

            $generated[] = [
                'produk_id' => $p->id,
                'sku' => $p->sku,
                'nama' => $p->nama,
                'satuan' => $p->satuan,
                'lead_time' => $ltLokal,
                'analisa' => $analisaLokal,
                'rekomendasi' => $rekLokal,
            ];
        }

        return [
            'generated_count' => count($generated),
            'timestamp' => $now->toIso8601String(),
            'items' => $generated,
        ];
    }

    /**
     * Profil Bahan Lokal — hitung satu baris analisa untuk satu produk (kompatibilitas lama).
     *
     * @return array<string,mixed>
     */
    public function lokal(AnalisaLokalInput $input): array
    {
        $moq = (float) ($input->produk->satuan_order_moq ?? 1);

        $ltLokal = LeadTimeLokal::where('produk_id', $input->produk_id)->first();
        if ($ltLokal) {
            $totalAvg = (float) $ltLokal->total_average_lead_time;
            $totalMax = (float) $ltLokal->total_max_lead_time;
            $safetyStockHari = (float) $ltLokal->safety_stock;
        } else {
            $totalAvg = (float) LeadTimeStage::where('produk_id', $input->produk_id)
                ->where('skenario', 'average')->sum('jumlah_hari');
            $totalMax = (float) LeadTimeStage::where('produk_id', $input->produk_id)
                ->where('skenario', 'max')->sum('jumlah_hari');
            $tambahanBuffer = (float) LeadTimeStage::where('produk_id', $input->produk_id)
                ->sum('tambahan_buffer_hari');
            $safetyStockHari = max(0, $totalMax - $totalAvg) + $tambahanBuffer;
        }

        $adu = $this->pusatAduLokal((int) $input->produk_id);
        // Desimal penuh seperti Excel (konsisten dengan generateLokal); pembulatan hanya di qty order (MOQ).
        $batasMinimum = $adu * ($totalAvg + $safetyStockHari);
        $targetStock = $adu * ($totalAvg + $safetyStockHari + (int) $input->review_period);

        // Sesuai Excel Analisa Lokal: ORDER bila Stok + Akan Datang ≤ Target Stock.
        // Stok & akan datang live dari Gudang Bahan Baku. Nilai tersimpan di kolom input lama diabaikan;
        // hanya koreksi in-memory (atribut diubah tapi belum disimpan) yang dipakai, mis. simulasi.
        $stok = $input->isDirty('stok_saat_ini') && $input->stok_saat_ini !== null
            ? (float) $input->stok_saat_ini
            : $this->stokGudangBahanBaku((int) $input->produk_id);
        $akan = $input->isDirty('akan_datang') && $input->akan_datang !== null
            ? (float) $input->akan_datang
            : $this->akanDatangGudangBahanBaku((int) $input->produk_id);
        $tersedia = $stok + $akan;
        $isOrder = $targetStock > 0 && $tersedia <= $targetStock;
        $selisih = $tersedia - $targetStock;
        $qtyOrder = $isOrder ? $this->bulatkanMoq(abs($selisih), $moq) : 0;

        return [
            'adu' => $adu,
            'total_avg_lead_time' => $totalAvg,
            'total_max_lead_time' => $totalMax,
            'safety_stock_hari' => $safetyStockHari,
            'batas_minimum' => $batasMinimum,
            'target_stock' => $targetStock,
            'tersedia' => $tersedia,
            'selisih' => $selisih,
            'status' => $isOrder ? 'order' : 'tidak',
            'qty_order' => $qtyOrder,
            'total_nominal_order' => round($qtyOrder * (float) $input->harga_per_satuan),
        ];
    }

    /**
     * Profil Bahan Impor — hitung per varian lalu agregasi qty order.
     *
     * @return array<string,mixed>
     */
    public function impor(AnalisaImporMeta $meta): array
    {
        $moq = (float) ($meta->produk->satuan_order_moq ?? 1);
        $ltAvg = (float) $meta->lead_time_average;
        $ltMax = (float) $meta->lead_time_max;
        $reviewPeriod = (int) ($meta->review_period ?: 30);
        $bufferDays = max(0, $ltMax - $ltAvg) + $meta->tambahanHariAbc();

        // Demand otomatis dari permintaan bahan baku Gudang Produksi (jendela 4 bulan)
        $jumlahHari = $this->demand()->window()['days'];
        $outTotal = (float) $this->pusatDemand((int) $meta->produk_id)['total'];

        $varianHasil = [];
        $totalQtyOrder = 0.0;
        $totalSelisih = 0.0;

        $hasVarianTable = \Illuminate\Support\Facades\Schema::hasTable('analisa_impor_varian');
        $varians = $hasVarianTable ? $meta->varian : collect();

        if ($varians->isNotEmpty()) {
            foreach ($varians as $v) {
                $out = round((float) $v->persentase_distribusi * $outTotal);
                $aduBase = round($jumlahHari > 0 ? ($out / $jumlahHari) : 0);
                $distribusi = (float) $v->persentase_distribusi;

                // ADU murni per produk/varian (tidak bergantung pada supplier / lead time)
                $aduEta = $aduBase;

                $safetyStock = round($aduEta * $bufferDays);
                $minimumStock = round($aduEta * $ltAvg);
                $targetStock = round($aduEta * ($ltAvg + $bufferDays + $reviewPeriod));
                $proyeksi = round((float) $v->stok_saat_ini + (float) $v->inbound_before_eta - $minimumStock);
                $selisih = round($proyeksi - $targetStock);
                $isPo = $selisih < 0;
                $qtyOrder = $isPo ? round($this->bulatkanMoq(abs($selisih), $moq)) : 0;

                $totalQtyOrder += $qtyOrder;
                $totalSelisih += $selisih;

                $varianHasil[] = [
                    'nama_varian' => $v->nama_varian,
                    'persentase' => $distribusi,
                    'out' => $out,
                    'adu_base' => $aduBase,
                    'adu_eta' => $aduEta,
                    'adu' => $aduEta,
                    'stok' => round((float) $v->stok_saat_ini),
                    'buffer_days' => $bufferDays,
                    'safety_stock' => $safetyStock,
                    'minimum_stock' => $minimumStock,
                    'target_stock' => $targetStock,
                    'stok_saat_ini' => round((float) $v->stok_saat_ini),
                    'inbound' => round((float) $v->inbound_before_eta),
                    'proyeksi' => $proyeksi,
                    'selisih' => $selisih,
                    'status' => $isPo ? 'po' : 'tidak',
                    'qty_order' => $qtyOrder,
                ];
            }
        } else {
            $out = round($outTotal);
            $aduBase = round($jumlahHari > 0 ? ($out / $jumlahHari) : 0);
            $aduEta = $aduBase;
            $safetyStock = round($aduEta * $bufferDays);
            $minimumStock = round($aduEta * $ltAvg);
            $targetStock = round($aduEta * ($ltAvg + $bufferDays + $reviewPeriod));

            $stokDb = $this->stokGudangBahanBaku((int) $meta->produk_id);
            $inboundDb = $this->akanDatangGudangBahanBaku((int) $meta->produk_id);

            $proyeksi = round($stokDb + $inboundDb - $minimumStock);
            $selisih = round($proyeksi - $targetStock);
            $isPo = $selisih < 0;
            $qtyOrder = $isPo ? round($this->bulatkanMoq(abs($selisih), $moq)) : 0;

            $totalQtyOrder = $qtyOrder;
            $totalSelisih = $selisih;

            $varianHasil[] = [
                'nama_varian' => $meta->produk?->nama ?? 'Default',
                'persentase' => 1.0,
                'out' => $out,
                'adu_base' => $aduBase,
                'adu_eta' => $aduEta,
                'adu' => $aduEta,
                'stok' => round($stokDb),
                'buffer_days' => $bufferDays,
                'safety_stock' => $safetyStock,
                'minimum_stock' => $minimumStock,
                'target_stock' => $targetStock,
                'stok_saat_ini' => round($stokDb),
                'inbound' => round($inboundDb),
                'proyeksi' => $proyeksi,
                'selisih' => $selisih,
                'status' => $isPo ? 'po' : 'tidak',
                'qty_order' => $qtyOrder,
            ];
        }

        return [
            'buffer_days' => $bufferDays,
            'varian' => $varianHasil,
            'total_qty_order' => $totalQtyOrder,
            'total_selisih' => $totalSelisih,
            'status' => $totalQtyOrder > 0 ? 'po' : 'tidak',
            'total_nominal_order' => $totalQtyOrder * (float) $meta->harga_per_satuan,
        ];
    }

    /**
     * Engine Generate Analisa Impor:
     * Menghitung dan menyimpan hasil ke 2 tabel data kerja:
     * 1. lead_time_impor (lead_time_average, lead_time_max)
     * 2. analisa_impor (working data: out, adu_base, adu_eta, buffer_days, safety_stock, minimum_stock, target_stock, proyeksi, qty_order, po, status, nominal, varian_detail)
     *
     * @param  int|null  $produkId  Jika null, generate untuk seluruh produk bahan impor
     * @param  int|null  $userId    User ID pembuat generate
     * @return array{generated_count: int, timestamp: string, items: array}
     */
    /**
     * Total out 4 bulan per grup pivot impor: Σ pemakaian seluruh SKU yang memakai grup_pivot yang sama.
     *
     * @return array<string, float>
     */
    public function imporGrupPivotOutTotals(): array
    {
        $grupMap = AnalisaImpor::whereNotNull('grup_pivot')->where('grup_pivot', '!=', '')
            ->pluck('grup_pivot', 'produk_id');
        if ($grupMap->isEmpty()) {
            return [];
        }

        $demand = $this->demand()->aduBahanPusat($grupMap->keys()->map(fn ($id) => (int) $id)->all());
        $totals = [];
        foreach ($grupMap as $pid => $grup) {
            $key = self::normalizeGrupPivot($grup);
            $totals[$key] = ($totals[$key] ?? 0.0) + (float) ($demand[(int) $pid]['total'] ?? 0.0);
        }

        return $totals;
    }

    public static function normalizeGrupPivot(?string $grup): string
    {
        return strtolower(trim((string) $grup));
    }

    /**
     * ADU Pivot (komposisi baru) = % pivot × total out grup ÷ jumlah hari. 0 bila SKU tidak ikut grup pivot.
     *
     * @param  array<string, float>  $grupOutTotals
     */
    public static function hitungAduPivot(?string $grup, ?float $persen, array $grupOutTotals, int $jumlahHari): float
    {
        if ($persen === null || $persen <= 0 || $jumlahHari <= 0 || trim((string) $grup) === '') {
            return 0.0;
        }

        return $persen * (float) ($grupOutTotals[self::normalizeGrupPivot($grup)] ?? 0.0) / $jumlahHari;
    }

    /** ADU ETA = ADU Base + ADU Pivot × Lead Time / 30 (Excel Analisa Import, sheet P50). */
    public static function hitungAduEta(float $aduBase, float $aduPivot, float $ltAvg): float
    {
        return $aduBase + ($aduPivot * $ltAvg / 30);
    }

    /**
     * @param  array<int, array{stok_saat_ini?: float, inbound_before_eta?: float}>  $overrides  koreksi manual sekali pakai per produk
     */
    public function generateImpor(?int $produkId = null, ?int $userId = null, array $overrides = []): array
    {
        $query = Produk::query()
            ->where('is_active', true)
            ->whereIn('tipe', ['bahan', 'kemas']);

        if ($produkId) {
            $query->where('id', $produkId);
        } else {
            // Keanggotaan eksplisit (profil_analisa impor). SKU lama ber-profil NULL sudah di-backfill migrasi.
            $query->whereIn('profil_analisa', self::IMPOR_PROFILES);
        }

        $products = $query->orderBy('nama')->get();

        $generated = [];
        $now = now();
        // Total out 4 bulan per grup pivot (mis. seluruh warna botol P50) untuk ADU Pivot komposisi baru.
        $grupOutTotals = $this->imporGrupPivotOutTotals();

        foreach ($products as $p) {
            $meta = AnalisaImporMeta::where('produk_id', $p->id)->first();
            $existingAnalisa = AnalisaImpor::where('produk_id', $p->id)->first();

            $ltData = $this->resolveSupplierProdukLeadTimeImpor($p->id, $existingAnalisa?->supplier_terpilih_id);
            $ltAvg = $ltData['lead_time_average'];
            $ltMax = $ltData['lead_time_max'];
            $supplierTerpilihId = $ltData['supplier_id'];
            $sumberLt = $ltData['sumber_lt'];

            $reviewPeriod = (int) ($existingAnalisa?->review_period ?? $meta?->review_period ?? 30);
            if ($reviewPeriod <= 0) {
                $reviewPeriod = 30;
            }

            // Master Klasifikasi ABC Resolution (pertahankan null saat SKU dilepas murni)
            $abcRecord = null;
            if ($existingAnalisa) {
                if ($existingAnalisa->klasifikasi_abc_id) {
                    $abcRecord = KlasifikasiAbc::find($existingAnalisa->klasifikasi_abc_id);
                } elseif ($p->klasifikasi_abc_id) {
                    $abcRecord = KlasifikasiAbc::find($p->klasifikasi_abc_id);
                }
            } else {
                if ($p->klasifikasi_abc_id) {
                    $abcRecord = KlasifikasiAbc::find($p->klasifikasi_abc_id);
                } elseif ($meta?->klasifikasi_abc_id) {
                    $abcRecord = KlasifikasiAbc::find($meta->klasifikasi_abc_id);
                } elseif (! empty($meta?->klasifikasi_abc)) {
                    $abcRecord = KlasifikasiAbc::where('kode', strtolower((string) $meta->klasifikasi_abc))->first();
                }
            }

            $klasifikasiAbc = $abcRecord ? $abcRecord->kode : 'c';
            $klasifikasiAbcId = $abcRecord?->id;
            $tambahanHari = $abcRecord ? (float) $abcRecord->tambahan_buffer_hari : 0.0;
            $bufferDays = self::roundLeadTimeDays(max(0.0, $ltMax - $ltAvg) + $tambahanHari);

            // 1. Simpan LeadTimeImpor
            $ltImpor = LeadTimeImpor::firstOrNew(['produk_id' => $p->id]);
            $ltImpor->fill([
                'lead_time_average' => $ltAvg,
                'lead_time_max' => $ltMax,
            ])->save();

            // 2. Data Out, Periode, & Harga
            $demandImpor = $this->pusatDemand((int) $p->id);
            $out = (float) $demandImpor['total'];
            $harga = (float) ($existingAnalisa?->harga_per_satuan ?? $meta?->harga_per_satuan ?? $p->harga_hpp ?? 0);
            $moq = (float) ($p->satuan_order_moq ?: 1);

            // Jendela 4 bulan terakhir (otomatis); ADU = total permintaan / jumlah hari jendela
            $win = $this->demand()->window();
            $periodeMulai = $win['start']->toDateString();
            $periodeAkhir = $win['end']->toDateString();
            $jumlahHari = $win['days'];

            // ADU Base = out produk ini / jumlah hari (komposisi saat ini, Excel "Pivot 3").
            $aduBase = $jumlahHari > 0 ? ($out / $jumlahHari) : 0;
            // ADU Pivot = % komposisi baru × total out grup / jumlah hari (Excel "Pivot 4").
            $grupPivot = $existingAnalisa?->grup_pivot;
            $persenPivot = $existingAnalisa?->persentase_pivot !== null ? (float) $existingAnalisa->persentase_pivot : null;
            $aduPivot = self::hitungAduPivot($grupPivot, $persenPivot, $grupOutTotals, $jumlahHari);
            // ADU ETA = ADU Base + ADU Pivot × Lead Time / 30 (laju kebutuhan saat barang tiba).
            $aduEta = self::hitungAduEta($aduBase, $aduPivot, $ltAvg);

            $totalSafetyStock = round($aduEta * $bufferDays);
            $totalMinStock = round($aduEta * $ltAvg);
            $totalTargetStock = (int) floor($aduEta * ($ltAvg + $bufferDays + $reviewPeriod) + 0.0001);

            // Stok & inbound selalu live dari Gudang Bahan Baku; koreksi manual hanya untuk perhitungan ini.
            $override = $overrides[(int) $p->id] ?? [];
            $totalStok = round(array_key_exists('stok_saat_ini', $override)
                ? (float) $override['stok_saat_ini']
                : $this->stokGudangBahanBaku((int) $p->id));
            $totalInbound = round(array_key_exists('inbound_before_eta', $override)
                ? (float) $override['inbound_before_eta']
                : $this->akanDatangGudangBahanBaku((int) $p->id));

            $totalProyeksi = round($totalStok + $totalInbound - $totalMinStock);
            $selisih = round($totalProyeksi - $totalTargetStock);
            $isPo = $selisih < 0;
            $totalQtyOrder = $isPo ? abs($selisih) : 0;
            $totalPo = $isPo ? round($this->bulatkanMoq($totalQtyOrder, $moq)) : 0;

            $status = $totalPo > 0 ? 'po' : 'tidak';
            $totalNominal = round($totalPo * $harga);

            $analisaImpor = AnalisaImpor::updateOrCreate(
                ['produk_id' => $p->id],
                [
                    'supplier_terpilih_id' => $supplierTerpilihId,
                    'sumber_lt' => $sumberLt,
                    'out' => round($out),
                    'periode_mulai' => $periodeMulai,
                    'periode_akhir' => $periodeAkhir,
                    'jumlah_hari_periode' => $jumlahHari,
                    'adu_base' => $aduBase,
                    'adu_eta' => $aduEta,
                    'adu_pivot' => $aduPivot,
                    'lead_time' => $ltAvg,
                    'review_period' => $reviewPeriod,
                    'klasifikasi_abc' => $klasifikasiAbc,
                    'klasifikasi_abc_id' => $klasifikasiAbcId,
                    'tambahan_buffer_hari' => $tambahanHari,
                    'buffer_days' => self::roundLeadTimeDays($bufferDays),
                    'safety_stock' => round($totalSafetyStock),
                    'minimum_stock' => round($totalMinStock),
                    'target_stock' => round($totalTargetStock),
                    'stok_saat_ini' => round($totalStok),
                    'inbound_before_eta' => round($totalInbound),
                    'proyeksi' => round($totalProyeksi),
                    'qty_order' => round($totalQtyOrder),
                    'po' => round($totalPo),
                    'status' => $status,
                    'harga_per_satuan' => round($harga),
                    'total_nominal_order' => round($totalNominal),
                    'generated_at' => $now,
                    'generated_by' => $userId ?? auth()->id(),
                ]
            );

            $generated[] = [
                'produk_id' => $p->id,
                'sku' => $p->sku,
                'nama' => $p->nama,
                'po' => $totalPo,
                'status' => $status,
                'total_nominal' => $totalNominal,
            ];
        }

        return [
            'generated_count' => count($generated),
            'timestamp' => $now->format('d/m/Y H:i'),
            'items' => $generated,
        ];
    }

    /**
     * Finalisasi (Locking) Data Kerja Analisa ke Tabel Riwayat Analisa
     * Mengunci salinan snapshot dengan batch session ID unik.
     *
     * @param  string  $tipe  'bahan_lokal' | 'bahan_impor' | 'produk_jadi_fulfillment' | 'all'
     * @param  int|null  $userId  User ID yang memfinalisasi
     * @return array{session_id: string, count: int, tipe: string}
     */
    public function finalisasi(string $tipe, ?int $userId = null): array
    {
        $sessionId = sprintf('SNAP-%s-%s', date('Ymd-His'), strtoupper(Str::random(4)));
        $count = 0;
        $actorId = $userId ?? auth()->id();
        $today = now()->toDateString();

        // Arsipkan angka terbaru: hitung ulang lokal/impor dulu (data kerja bisa tertinggal sejak terakhir dibuka).
        if ($tipe === 'bahan_lokal' || $tipe === 'all') {
            $this->generateLokal(null, $actorId);
        }
        if ($tipe === 'bahan_impor' || $tipe === 'all') {
            $this->generateImpor(null, $actorId);
        }

        DB::transaction(function () use ($tipe, $sessionId, $actorId, $today, &$count) {
            // 1. Finalisasi Bahan Lokal
            if ($tipe === 'bahan_lokal' || $tipe === 'all') {
                $lokalIds = $this->lokalEligibleProdukIds();
                $rekomendasiList = empty($lokalIds)
                    ? collect()
                    : RekomendasiOrderLokal::with('produk.supplier')->whereIn('produk_id', $lokalIds)->get();
                $analisaMap = empty($lokalIds)
                    ? collect()
                    : AnalisaLokal::with('supplierTerpilih')->whereIn('produk_id', $lokalIds)->get()->keyBy('produk_id');
                $ltMap = empty($lokalIds)
                    ? collect()
                    : LeadTimeLokal::whereIn('produk_id', $lokalIds)->get()->keyBy('produk_id');

                foreach ($rekomendasiList as $rek) {
                    $an = $analisaMap->get($rek->produk_id);
                    $lt = $ltMap->get($rek->produk_id);
                    $supId = $an?->supplier_terpilih_id ?? $rek->produk?->supplier_id;
                    $supNama = $an?->supplierTerpilih?->nama ?? $rek->produk?->supplier?->nama ?? '-';
                    $avgLt = $an?->total_average_lead_time !== null ? (float) $an->total_average_lead_time : null;
                    $maxLt = $lt?->total_max_lead_time !== null ? (float) $lt->total_max_lead_time : null;
                    $sumber = $an?->sumber_lt ?? 'manual';

                    RiwayatAnalisa::create([
                        'session_id' => $sessionId,
                        'tanggal' => $today,
                        'tipe' => 'bahan_lokal',
                        'item_label' => $rek->produk?->sku ?? '-',
                        'supplier_id' => $supId,
                        'average_lt_dipakai' => $avgLt,
                        'max_lt_dipakai' => $maxLt,
                        'sumber_lt' => $sumber,
                        'batas_minimum' => (float) $rek->batas_minimum,
                        'target_stock' => (float) $rek->target_stock,
                        'status' => $rek->status,
                        'qty_order' => (float) ($rek->rekomendasi_order ?: $rek->rumus_moq),
                        'dicatat_oleh' => $actorId,
                        'is_locked' => true,
                        'detail_payload' => [
                            'nama' => $rek->produk?->nama,
                            'satuan' => $rek->produk?->satuan,
                            'supplier_id' => $supId,
                            'supplier_nama' => $supNama,
                            'sumber_lt' => $sumber,
                            'average_lt' => $avgLt,
                            'max_lt' => $maxLt,
                            'stok_saat_ini' => (float) $rek->stok_saat_ini,
                            'akan_datang' => (float) $rek->akan_datang,
                            'selisih' => (float) $rek->selisih,
                            'rumus_moq' => (float) $rek->rumus_moq,
                            'rekomendasi_order' => (float) $rek->rekomendasi_order,
                            'harga_per_satuan' => (float) $rek->harga_ml_pcs,
                            'total_nominal' => (float) $rek->total_nominal_order,
                        ],
                    ]);
                    $count++;
                }
            }

            // 2. Finalisasi Bahan Impor
            if ($tipe === 'bahan_impor' || $tipe === 'all') {
                $imporIds = $this->imporEligibleProdukIds();
                $analisaImporList = empty($imporIds)
                    ? collect()
                    : AnalisaImpor::with(['produk.supplier', 'supplierTerpilih'])->whereIn('produk_id', $imporIds)->get();
                $ltImporMap = empty($imporIds)
                    ? collect()
                    : LeadTimeImpor::whereIn('produk_id', $imporIds)->get()->keyBy('produk_id');

                foreach ($analisaImporList as $ai) {
                    $ltImp = $ltImporMap->get($ai->produk_id);
                    $supId = $ai->supplier_terpilih_id ?? $ai->produk?->supplier_id;
                    $supNama = $ai->supplierTerpilih?->nama ?? $ai->produk?->supplier?->nama ?? '-';
                    $avgLt = (float) $ai->lead_time;
                    $maxLt = $ltImp?->lead_time_max !== null ? (float) $ltImp->lead_time_max : null;
                    $sumber = $ai->sumber_lt ?? 'manual';

                    RiwayatAnalisa::create([
                        'session_id' => $sessionId,
                        'tanggal' => $today,
                        'tipe' => 'bahan_impor',
                        'item_label' => $ai->produk?->sku ?? '-',
                        'supplier_id' => $supId,
                        'average_lt_dipakai' => $avgLt,
                        'max_lt_dipakai' => $maxLt,
                        'sumber_lt' => $sumber,
                        'batas_minimum' => (float) $ai->minimum_stock,
                        'target_stock' => (float) $ai->target_stock,
                        'status' => $ai->status,
                        'qty_order' => (float) ($ai->po ?: $ai->qty_order),
                        'dicatat_oleh' => $actorId,
                        'is_locked' => true,
                        'detail_payload' => [
                            'nama' => $ai->produk?->nama,
                            'supplier_id' => $supId,
                            'supplier_nama' => $supNama,
                            'sumber_lt' => $sumber,
                            'average_lt' => $avgLt,
                            'max_lt' => $maxLt,
                            'klasifikasi_abc' => $ai->klasifikasi_abc,
                            'buffer_days' => (float) $ai->buffer_days,
                            'safety_stock' => (float) $ai->safety_stock,
                            'stok_saat_ini' => (float) $ai->stok_saat_ini,
                            'inbound' => (float) $ai->inbound_before_eta,
                            'proyeksi' => (float) $ai->proyeksi,
                            'po' => (float) $ai->po,
                            'total_nominal' => (float) $ai->total_nominal_order,
                            'varian_detail' => $ai->varian_detail,
                        ],
                    ]);
                    $count++;
                }
            }

            // 3. Finalisasi Produk Jadi Produksi (kebutuhan produksi di Gudang Produksi)
            if ($tipe === 'produk_jadi_produksi' || $tipe === 'all') {
                foreach ($this->produksi() as $h) {
                    RiwayatAnalisa::create([
                        'session_id' => $sessionId,
                        'tanggal' => $today,
                        'tipe' => 'produk_jadi_produksi',
                        'item_label' => $h['sku'] ?? '-',
                        'average_lt_dipakai' => (float) $h['lead_time_average'],
                        'max_lt_dipakai' => (float) $h['lead_time_max'],
                        'sumber_lt' => 'batch_produksi',
                        'batas_minimum' => (float) $h['batas_minimum'],
                        'target_stock' => (float) $h['target_stock'],
                        'status' => $h['status'],
                        'qty_order' => (float) $h['qty_produksi'],
                        'dicatat_oleh' => $actorId,
                        'is_locked' => true,
                        'detail_payload' => [
                            'nama' => $h['nama'],
                            'gudang_id' => $h['gudang_id'],
                            'gudang_nama' => $h['gudang_nama'],
                            'adu' => (float) $h['adu'],
                            'average_lt' => (float) $h['lead_time_average'],
                            'max_lt' => (float) $h['lead_time_max'],
                            'jumlah_batch_selesai' => (int) $h['jumlah_batch_selesai'],
                            'safety_stock_hari' => (float) $h['safety_stock_hari'],
                            'review_period' => (int) $h['review_period'],
                            'stok_saat_ini' => (float) $h['stok_saat_ini'],
                            'akan_datang' => (float) $h['akan_datang'],
                            'selisih' => (float) $h['selisih'],
                            'qty_produksi' => (float) $h['qty_produksi'],
                        ],
                    ]);
                    $count++;
                }
            }

            // 4. Finalisasi Produk Jadi Fulfillment
            if ($tipe === 'produk_jadi_fulfillment' || $tipe === 'all') {
                foreach ($this->fulfillmentRows() as $h) {
                    RiwayatAnalisa::create([
                        'session_id' => $sessionId,
                        'tanggal' => $today,
                        'tipe' => 'produk_jadi_fulfillment',
                        'item_label' => $h['sku'] ?? '-',
                        'batas_minimum' => (float) $h['batas_minimum_total'],
                        'target_stock' => (float) $h['target_stock_total'],
                        'status' => $h['status'],
                        'qty_order' => (float) $h['qty_order'],
                        'dicatat_oleh' => $actorId,
                        'is_locked' => true,
                        'detail_payload' => [
                            'nama' => $h['nama'],
                            'stok_total' => (float) $h['stok_total'],
                            'akan_datang' => (float) $h['akan_datang'],
                            'per_gudang' => $h['per_gudang'],
                        ],
                    ]);
                    $count++;
                }
            }
        });

        return [
            'session_id' => $sessionId,
            'count' => $count,
            'tipe' => $tipe,
        ];
    }

    /**
     * Profil Produk Jadi (Fulfillment) — per gudang lalu agregasi level ALL.
     *
     * @param  \Illuminate\Support\Collection<int,AnalisaFulfillmentInput>  $rows  baris per gudang untuk satu produk jadi
     * @return array<string,mixed>
     */
    public function fulfillment($rows, float $moq = 1, ?array $live = null): array
    {
        $rows = collect($rows);
        $live ??= $this->fulfillmentLiveStock(
            $rows->pluck('produk_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
            $rows->pluck('gudang_id')->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        $perGudang = [];
        $aduTotal = 0.0;
        $batasMinTotal = 0.0;
        $targetTotal = 0.0;
        $stokTotal = 0.0;
        $akanDatang = 0.0;

        foreach ($rows as $r) {
            $adu = (float) $r->terjual_rata_rata_4bulan / 30; // tanpa pembulatan, sesuai Excel
            $batasMin = $adu * ((int) $r->lead_time_distribusi + (int) $r->buffer_distribusi);
            $target = $adu * ((int) $r->lead_time_distribusi + (int) $r->buffer_distribusi + (int) $r->review_period);

            // Stok & Akan Datang diambil live dari ledger stok, transfer in-transit, dan rencana batch aktif.
            $key = "{$r->produk_id}_{$r->gudang_id}";
            $stokGudang = (float) ($live['stok'][$key] ?? 0.0);
            $akanGudang = (float) ($live['akan_datang'][$key] ?? 0.0);

            $aduTotal += $adu;
            $batasMinTotal += $batasMin;
            $targetTotal += $target;
            $stokTotal += $stokGudang;
            $akanDatang += $akanGudang;

            $perGudang[] = [
                'gudang_id' => (int) $r->gudang_id,
                'gudang' => $r->gudang?->nama,
                'terjual_rata_rata_4bulan' => (float) $r->terjual_rata_rata_4bulan,
                'lead_time_distribusi' => (int) $r->lead_time_distribusi,
                'buffer_distribusi' => (int) $r->buffer_distribusi,
                'review_period' => (int) $r->review_period,
                'adu' => $adu,
                'batas_minimum' => $batasMin,
                'target_stock' => $target,
                'stok_saat_ini' => $stokGudang,
                'akan_datang' => $akanGudang,
            ];
        }

        $stokAll = $stokTotal + $akanDatang;
        $isOrder = $stokAll <= $targetTotal;
        $selisih = $stokAll - $targetTotal;
        $qtyOrder = $isOrder ? $this->bulatkanMoq(abs($selisih), $moq) : 0;

        return [
            'per_gudang' => $perGudang,
            'adu_total' => $aduTotal,
            'batas_minimum_total' => $batasMinTotal,
            'target_stock_total' => $targetTotal,
            'stok_total' => $stokTotal,
            'akan_datang' => $akanDatang,
            'stok_all' => $stokAll,
            'selisih' => $selisih,
            'status' => $isOrder ? 'order' : 'tidak',
            // Sinyal kebutuhan produksi tambahan (bukan PO ke supplier).
            'qty_order' => $qtyOrder,
        ];
    }

    /**
     * Seluruh baris Analisa Produk Jadi Fulfillment (satu baris per produk, level ALL),
     * dengan stok & akan datang live dihitung sekali untuk semua produk.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fulfillmentRows(): array
    {
        $inputs = AnalisaFulfillmentInput::with(['produk:id,sku,nama,satuan,satuan_order_moq', 'gudang:id,nama'])->get();
        if ($inputs->isEmpty()) {
            return [];
        }

        $live = $this->fulfillmentLiveStock(
            $inputs->pluck('produk_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
            $inputs->pluck('gudang_id')->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        $out = [];
        foreach ($inputs->groupBy('produk_id') as $produkId => $rows) {
            $produk = $rows->first()->produk;
            $moq = (float) ($produk?->satuan_order_moq ?: 1);

            $out[] = array_merge([
                'produk_id' => (int) $produkId,
                'sku' => $produk?->sku,
                'nama' => $produk?->nama,
                'satuan' => $produk?->satuan,
                'moq' => $moq,
            ], $this->fulfillment($rows, $moq, $live));
        }

        usort($out, fn ($a, $b) => strcmp((string) $a['nama'], (string) $b['nama']));

        return $out;
    }

    /**
     * Stok live per produk_gudang untuk Analisa Fulfillment:
     *  - stok: saldo fisik di tabel stok.
     *  - akan_datang: transfer berstatus dikirim (in-transit) menuju gudang tersebut
     *    + rencana batch produksi aktif (rencana/release) dengan tujuan gudang tersebut.
     *
     * @param  array<int, int>  $produkIds
     * @param  array<int, int>  $gudangIds
     * @return array{stok: array<string, float>, akan_datang: array<string, float>}
     */
    public function fulfillmentLiveStock(array $produkIds, array $gudangIds): array
    {
        $stok = [];
        $akan = [];
        if (empty($produkIds) || empty($gudangIds)) {
            return ['stok' => $stok, 'akan_datang' => $akan];
        }

        $add = function (array &$map, $produkId, $gudangId, $qty): void {
            $key = (int) $produkId . '_' . (int) $gudangId;
            $map[$key] = ($map[$key] ?? 0.0) + (float) $qty;
        };

        foreach (Stok::whereIn('produk_id', $produkIds)->whereIn('gudang_id', $gudangIds)
            ->get(['produk_id', 'gudang_id', 'qty_saat_ini']) as $st) {
            $add($stok, $st->produk_id, $st->gudang_id, $st->qty_saat_ini);
        }

        foreach (DB::table('transfer_items as ti')
            ->join('transfers as t', 't.id', '=', 'ti.transfer_id')
            ->where('t.status', 'dikirim')
            ->whereIn('ti.produk_id', $produkIds)
            ->whereIn('t.gudang_tujuan_id', $gudangIds)
            ->groupBy('ti.produk_id', 't.gudang_tujuan_id')
            ->get(['ti.produk_id', 't.gudang_tujuan_id as gudang_id', DB::raw('SUM(ti.qty_dikirim) as total')]) as $r) {
            $add($akan, $r->produk_id, $r->gudang_id, $r->total);
        }

        $gudangOutput = 'COALESCE(bpo.gudang_tujuan_rencana_id, bp.gudang_tujuan_rencana_id)';
        foreach (DB::table('batch_produksi_outputs as bpo')
            ->join('batch_produksi as bp', 'bp.id', '=', 'bpo.batch_id')
            ->whereIn('bp.status', ['rencana', 'release'])
            ->whereIn('bpo.produk_id', $produkIds)
            ->whereIn(DB::raw($gudangOutput), $gudangIds)
            ->groupBy('bpo.produk_id', DB::raw($gudangOutput))
            ->get(['bpo.produk_id', DB::raw("{$gudangOutput} as gudang_id"), DB::raw('SUM(bpo.qty_rencana) as total')]) as $r) {
            $add($akan, $r->produk_id, $r->gudang_id, $r->total);
        }

        // Batch legacy (single-product) yang belum punya baris output.
        foreach (DB::table('batch_produksi as bp')
            ->whereIn('bp.status', ['rencana', 'release'])
            ->whereIn('bp.produk_id', $produkIds)
            ->whereIn('bp.gudang_tujuan_rencana_id', $gudangIds)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('batch_produksi_outputs as bpo')->whereColumn('bpo.batch_id', 'bp.id');
            })
            ->groupBy('bp.produk_id', 'bp.gudang_tujuan_rencana_id')
            ->get(['bp.produk_id', 'bp.gudang_tujuan_rencana_id as gudang_id', DB::raw('SUM(bp.qty_rencana) as total')]) as $r) {
            $add($akan, $r->produk_id, $r->gudang_id, $r->total);
        }

        return ['stok' => $stok, 'akan_datang' => $akan];
    }

    /**
     * Simpan parameter Analisa Fulfillment satu produk jadi untuk sekumpulan gudang fulfillment.
     * Gudang yang tidak dikirim akan dihapus dari analisa produk tersebut.
     *
     * @param  array<int, array{gudang_id:int, terjual_rata_rata_4bulan:float|int, lead_time_distribusi:int, buffer_distribusi:int, review_period:int}>  $gudangRows
     */
    public function saveFulfillmentInputs(Produk $produk, array $gudangRows): void
    {
        if ($produk->tipe !== 'produk_jadi') {
            throw ValidationException::withMessages(['produk_id' => 'Analisa Fulfillment hanya untuk produk jadi.']);
        }
        if (empty($gudangRows)) {
            throw ValidationException::withMessages(['gudang' => 'Pilih minimal satu gudang fulfillment.']);
        }

        $gudangIds = array_map(fn ($g) => (int) $g['gudang_id'], $gudangRows);
        $validGudang = \App\Models\Gudang::whereIn('id', $gudangIds)
            ->whereIn('tipe', \App\Models\Gudang::TIPE_FULFILLMENT)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count(array_diff($gudangIds, $validGudang)) > 0) {
            throw ValidationException::withMessages(['gudang' => 'Gudang yang dipilih harus bertipe Fulfillment.']);
        }

        DB::transaction(function () use ($produk, $gudangRows, $gudangIds) {
            AnalisaFulfillmentInput::where('produk_id', $produk->id)->whereNotIn('gudang_id', $gudangIds)->delete();

            foreach ($gudangRows as $g) {
                AnalisaFulfillmentInput::updateOrCreate(
                    ['produk_id' => $produk->id, 'gudang_id' => (int) $g['gudang_id']],
                    [
                        'terjual_rata_rata_4bulan' => (float) $g['terjual_rata_rata_4bulan'],
                        'lead_time_distribusi' => (int) $g['lead_time_distribusi'],
                        'buffer_distribusi' => (int) $g['buffer_distribusi'],
                        'review_period' => (int) $g['review_period'],
                    ]
                );
            }
        });
    }

    private function demand(): DemandAduService
    {
        return $this->demandSvc ??= app(DemandAduService::class);
    }

    /**
     * Demand Gudang Pusat (bahan baku) satu produk: permintaan bahan baku dari Gudang Produksi
     * selama 4 bulan terakhir. ADU = total / jumlah hari jendela.
     *
     * @return array{total: float, adu: float}
     */
    public function pusatDemand(int $produkId): array
    {
        return $this->demand()->aduBahanPusat([$produkId])[$produkId] ?? ['total' => 0.0, 'adu' => 0.0];
    }

    private function pusatAdu(int $produkId): float
    {
        return (float) $this->pusatDemand($produkId)['adu'];
    }

    /** ADU bahan lokal sesuai Excel: (pemakaian 4 bulan ÷ 4) ÷ 30. */
    private function pusatAduLokal(int $produkId): float
    {
        return (float) ($this->demand()->aduBahanPusat([$produkId])[$produkId]['adu_lokal'] ?? 0.0);
    }

    /** Bulatkan ke atas ke kelipatan MOQ. */
    private function bulatkanMoq(float $qty, float $moq): float
    {
        if ($moq <= 0) {
            return ceil($qty);
        }

        return ceil($qty / $moq) * $moq;
    }

    /**
     * Peta Batas Minimum per produk dan per kombinasi produk_gudang
     * yang dihitung langsung dari modul Analisa Stok (Lokal, Impor, Fulfillment).
     *
     * @return array{by_product: array<int, float>, by_product_gudang: array<string, float>}
     */
    public function getBatasMinimumMap(): array
    {
        $byProduct = [];
        $byProductGudang = [];
        $lokalIds = $this->lokalEligibleProdukIds();
        $imporIds = $this->imporEligibleProdukIds();

        // 1. Profil Bahan Lokal (prioritaskan tabel analisa_lokal tersimpan)
        $analisaLokalList = empty($lokalIds) ? collect() : AnalisaLokal::whereIn('produk_id', $lokalIds)->get();
        if ($analisaLokalList->isNotEmpty()) {
            foreach ($analisaLokalList as $al) {
                $byProduct[$al->produk_id] = (float) $al->batas_minimum;
            }
        } elseif (! empty($lokalIds)) {
            $lokalInputs = AnalisaLokalInput::with('produk')->whereIn('produk_id', $lokalIds)->get();
            foreach ($lokalInputs as $input) {
                $hasil = $this->lokal($input);
                $min = (float) ($hasil['batas_minimum'] ?? 0);
                $byProduct[$input->produk_id] = $min;
            }
        }

        // 2. Profil Bahan Impor
        $analisaImporList = empty($imporIds) ? collect() : AnalisaImpor::whereIn('produk_id', $imporIds)->get();
        if ($analisaImporList->isNotEmpty()) {
            foreach ($analisaImporList as $ai) {
                $byProduct[$ai->produk_id] = (float) $ai->minimum_stock;
            }
        } elseif (! empty($imporIds) && AnalisaImporMeta::whereIn('produk_id', $imporIds)->exists()) {
            $imporMetas = AnalisaImporMeta::with('produk')->whereIn('produk_id', $imporIds)->get();
            foreach ($imporMetas as $meta) {
                $hasil = $this->impor($meta);
                $minTotal = 0.0;
                foreach ($hasil['varian'] as $v) {
                    $minTotal += (float) ($v['minimum_stock'] ?? 0);
                }
                $byProduct[$meta->produk_id] = $minTotal;
            }
        }

        // 3. Profil Produk Jadi (Fulfillment)
        $ffInputs = AnalisaFulfillmentInput::all();
        foreach ($ffInputs as $r) {
            $adu = (float) $r->terjual_rata_rata_4bulan / 30; // tanpa pembulatan, sesuai Excel
            $batasMin = $adu * ((int) $r->lead_time_distribusi + (int) $r->buffer_distribusi);
            $key = "{$r->produk_id}_{$r->gudang_id}";
            $byProductGudang[$key] = $batasMin;

            $byProduct[$r->produk_id] = ($byProduct[$r->produk_id] ?? 0) + $batasMin;
        }

        // 4. Profil Barang Jadi di Gudang Produksi (ADU dari permintaan produk jadi Fulfillment)
        foreach ($this->produksi() as $row) {
            $key = "{$row['produk_id']}_{$row['gudang_id']}";
            $byProductGudang[$key] = (float) $row['batas_minimum'];
        }

        return [
            'by_product' => $byProduct,
            'by_product_gudang' => $byProductGudang,
            // Batas minimum bahan (lokal/impor) hanya berlaku untuk Gudang Pusat (bahan baku).
            'pusat_gudang_ids' => \App\Models\Gudang::query()->where('tipe', 'bahan_baku')->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    /**
     * Review period (hari) default analisa barang jadi di Gudang Produksi.
     */
    public const PRODUKSI_REVIEW_PERIOD = 15;

    /** Jendela ADU (bulan) untuk menampilkan total permintaan produk jadi 4 bulan. */
    public const BULAN_JENDELA_ADU = 4;

    /**
     * Statistik lead time produksi per produk jadi dari batch selesai:
     * durasi = waktu rencana batch dibuat -> waktu batch diselesaikan (hari, 1 desimal half-up).
     *
     * @param  array<int, int>  $produkIds
     * @return array<int, array{avg: float, max: float, count: int}>
     */
    public function batchLeadTimeStats(array $produkIds): array
    {
        if (empty($produkIds)) {
            return [];
        }

        $rows = [];

        if (Schema::hasTable('batch_produksi_outputs')) {
            foreach (DB::table('batch_produksi_outputs as bpo')
                ->join('batch_produksi as bp', 'bp.id', '=', 'bpo.batch_id')
                ->where('bp.status', 'selesai')
                ->whereNotNull('bp.waktu_selesai')
                ->whereIn('bpo.produk_id', $produkIds)
                ->get(['bpo.produk_id', 'bp.created_at', 'bp.waktu_selesai']) as $r) {
                $rows[] = $r;
            }
        }

        foreach (DB::table('batch_produksi as bp')
            ->where('bp.status', 'selesai')
            ->whereNotNull('bp.waktu_selesai')
            ->whereNotNull('bp.produk_id')
            ->whereIn('bp.produk_id', $produkIds)
            ->when(Schema::hasTable('batch_produksi_outputs'), function ($q) {
                $q->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))->from('batch_produksi_outputs as bpo')->whereColumn('bpo.batch_id', 'bp.id');
                });
            })
            ->get(['bp.produk_id', 'bp.created_at', 'bp.waktu_selesai']) as $r) {
            $rows[] = $r;
        }

        $perProduk = [];
        foreach ($rows as $r) {
            $menit = max(0, (int) round((\Carbon\Carbon::parse($r->waktu_selesai)->timestamp - \Carbon\Carbon::parse($r->created_at)->timestamp) / 60));
            $perProduk[(int) $r->produk_id][] = $menit;
        }

        $out = [];
        foreach ($perProduk as $pid => $list) {
            $out[$pid] = [
                'avg' => self::roundLeadTimeDays((array_sum($list) / count($list)) / 1440),
                'max' => self::roundLeadTimeDays(max($list) / 1440),
                'count' => count($list),
            ];
        }

        return $out;
    }

    /**
     * Gudang Produksi tempat produk jadi diproduksi: gudang operasional batch terakhir produk tsb,
     * fallback gudang operasional aktif pertama.
     *
     * @param  array<int, int>  $produkIds
     * @return array<int, int>  produk_id => gudang_id
     */
    private function gudangProduksiMap(array $produkIds): array
    {
        $map = [];
        foreach (DB::table('batch_produksi as bp')
            ->leftJoin('batch_produksi_outputs as bpo', 'bpo.batch_id', '=', 'bp.id')
            ->whereNotNull('bp.gudang_operasional_id')
            ->where('bp.status', '!=', 'dibatalkan')
            ->where(function ($q) use ($produkIds) {
                $q->whereIn('bp.produk_id', $produkIds)->orWhereIn('bpo.produk_id', $produkIds);
            })
            ->orderByDesc('bp.created_at')
            ->get(['bp.produk_id', 'bpo.produk_id as output_produk_id', 'bp.gudang_operasional_id']) as $r) {
            foreach ([$r->output_produk_id, $r->produk_id] as $pid) {
                if ($pid !== null && in_array((int) $pid, $produkIds, true) && ! isset($map[(int) $pid])) {
                    $map[(int) $pid] = (int) $r->gudang_operasional_id;
                }
            }
        }

        $default = \App\Models\Gudang::active()->where('tipe', 'operasional')->orderBy('id')->value('id');
        foreach ($produkIds as $pid) {
            if (! isset($map[$pid]) && $default) {
                $map[$pid] = (int) $default;
            }
        }

        return $map;
    }

    /**
     * Analisa barang jadi di Gudang Produksi (operasional).
     * ADU = Σ ADU penjualan seluruh Gudang Fulfillment (dari Analisa Fulfillment).
     * Lead time = rata-rata dan maksimum durasi batch (rencana -> selesai); safety = max - avg.
     * ORDER (perlu produksi) bila Stok + Rencana Batch ≤ Target; qty menutup sampai Target.
     *
     * @return array<int, array<string, mixed>>
     */
    public function produksi(): array
    {
        $aduPj = $this->demand()->aduProdukJadi();
        if (empty($aduPj)) {
            return [];
        }

        $gudangMapProduk = $this->gudangProduksiMap(array_map('intval', array_keys($aduPj)));
        $demand = [];
        foreach ($aduPj as $pid => $d) {
            $gid = $gudangMapProduk[(int) $pid] ?? null;
            if ($gid === null) {
                continue;
            }
            $demand["{$pid}_{$gid}"] = [
                'produk_id' => (int) $pid,
                'gudang_id' => $gid,
                'total' => (float) $d['terjual_bulanan'] * self::BULAN_JENDELA_ADU,
                'adu' => (float) $d['adu'],
            ];
        }
        if (empty($demand)) {
            return [];
        }

        $produkIds = array_values(array_unique(array_map(fn ($d) => (int) $d['produk_id'], $demand)));
        $gudangIds = array_values(array_unique(array_map(fn ($d) => (int) $d['gudang_id'], $demand)));

        $produkMap = Produk::whereIn('id', $produkIds)->get()->keyBy('id');
        $gudangMap = \App\Models\Gudang::whereIn('id', $gudangIds)->get()->keyBy('id');
        $ltStats = $this->batchLeadTimeStats($produkIds);

        $stokMap = [];
        foreach (Stok::whereIn('produk_id', $produkIds)->whereIn('gudang_id', $gudangIds)->get(['produk_id', 'gudang_id', 'qty_saat_ini']) as $st) {
            $stokMap["{$st->produk_id}_{$st->gudang_id}"] = (float) $st->qty_saat_ini;
        }

        // Rencana batch aktif di gudang produksi = produk jadi yang akan datang
        $akanDatang = [];
        foreach (DB::table('batch_produksi_outputs as bpo')
            ->join('batch_produksi as bp', 'bp.id', '=', 'bpo.batch_id')
            ->whereIn('bp.status', ['rencana', 'release'])
            ->whereIn('bpo.produk_id', $produkIds)
            ->whereIn('bp.gudang_operasional_id', $gudangIds)
            ->groupBy('bpo.produk_id', 'bp.gudang_operasional_id')
            ->get(['bpo.produk_id', 'bp.gudang_operasional_id as gudang_id', DB::raw('SUM(bpo.qty_rencana) as total')]) as $r) {
            $akanDatang["{$r->produk_id}_{$r->gudang_id}"] = (float) $r->total;
        }
        // Batch legacy (single-product) tanpa baris output.
        foreach (DB::table('batch_produksi as bp')
            ->whereIn('bp.status', ['rencana', 'release'])
            ->whereIn('bp.produk_id', $produkIds)
            ->whereIn('bp.gudang_operasional_id', $gudangIds)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('batch_produksi_outputs as bpo')->whereColumn('bpo.batch_id', 'bp.id');
            })
            ->groupBy('bp.produk_id', 'bp.gudang_operasional_id')
            ->get(['bp.produk_id', 'bp.gudang_operasional_id as gudang_id', DB::raw('SUM(bp.qty_rencana) as total')]) as $r) {
            $key = "{$r->produk_id}_{$r->gudang_id}";
            $akanDatang[$key] = ($akanDatang[$key] ?? 0.0) + (float) $r->total;
        }

        $rows = [];
        foreach ($demand as $key => $d) {
            $pid = (int) $d['produk_id'];
            $gid = (int) $d['gudang_id'];
            $produk = $produkMap->get($pid);

            $avgLt = (float) ($ltStats[$pid]['avg'] ?? 0.0);
            $maxLt = (float) ($ltStats[$pid]['max'] ?? 0.0);
            $safety = max(0.0, self::roundLeadTimeDays($maxLt - $avgLt));
            $adu = (float) $d['adu'];

            $batasMinimum = round($adu * ($avgLt + $safety));
            $target = round($adu * ($avgLt + $safety + self::PRODUKSI_REVIEW_PERIOD));
            $stok = (float) ($stokMap[$key] ?? 0.0);
            $akan = (float) ($akanDatang[$key] ?? 0.0);
            $tersedia = $stok + $akan;
            $selisih = $tersedia - $target;
            $isOrder = $target > 0 && $tersedia <= $target;
            $moq = (float) ($produk?->satuan_order_moq ?: 1);

            $rows[] = [
                'produk_id' => $pid,
                'sku' => $produk?->sku,
                'nama' => $produk?->nama,
                'gudang_id' => $gid,
                'gudang_nama' => $gudangMap->get($gid)?->nama,
                'total_permintaan_4bulan' => (float) $d['total'],
                'adu' => round($adu, 4),
                'lead_time_average' => $avgLt,
                'lead_time_max' => $maxLt,
                'jumlah_batch_selesai' => (int) ($ltStats[$pid]['count'] ?? 0),
                'safety_stock_hari' => $safety,
                'review_period' => self::PRODUKSI_REVIEW_PERIOD,
                'batas_minimum' => (float) $batasMinimum,
                'target_stock' => (float) $target,
                'stok_saat_ini' => $stok,
                'akan_datang' => $akan,
                'selisih' => round($selisih, 2),
                'status' => $isOrder ? 'order' : 'tidak',
                'qty_produksi' => $isOrder ? $this->bulatkanMoq(abs($selisih), $moq) : 0.0,
            ];
        }

        usort($rows, fn ($a, $b) => strcmp((string) $a['nama'], (string) $b['nama']));

        return $rows;
    }

    /**
     * Ambil Batas Minimum spesifik untuk stok suatu produk di gudang tertentu.
     */
    public function getBatasMinimumForStok(int $produkId, ?int $gudangId = null, ?array $map = null): float
    {
        $map = $map ?? $this->getBatasMinimumMap();
        if ($gudangId === null) {
            return (float) ($map['by_product'][$produkId] ?? 0.0);
        }

        if (isset($map['by_product_gudang']["{$produkId}_{$gudangId}"])) {
            return (float) $map['by_product_gudang']["{$produkId}_{$gudangId}"];
        }

        // Batas minimum bahan (lokal/impor) hanya berlaku untuk Gudang Pusat (bahan baku);
        // gudang lain tanpa baris analisa sendiri tidak punya batas minimum.
        if (in_array($gudangId, $map['pusat_gudang_ids'] ?? [], true)) {
            return (float) ($map['by_product'][$produkId] ?? 0.0);
        }

        return 0.0;
    }

    /**
     * Hitung ringkasan jumlah item yang menyentuh status ORDER / PO.
     *
     * @return array{total: int, lokal: int, impor: int, fulfillment: int}
     */
    public function getItemPerluOrderSummary(): array
    {
        $lokalIds = $this->lokalEligibleProdukIds();
        $imporIds = $this->imporEligibleProdukIds();

        $lokalOrder = empty($lokalIds)
            ? 0
            : RekomendasiOrderLokal::whereIn('produk_id', $lokalIds)->where('status', 'order')->count();
        if ($lokalOrder === 0 && ! empty($lokalIds) && AnalisaLokalInput::whereIn('produk_id', $lokalIds)->exists()) {
            $lokalOrder = AnalisaLokalInput::with('produk')->whereIn('produk_id', $lokalIds)->get()
                ->filter(fn ($i) => $this->lokal($i)['status'] === 'order')
                ->count();
        }

        $imporOrder = empty($imporIds)
            ? 0
            : AnalisaImpor::whereIn('produk_id', $imporIds)->where('status', 'po')->count();
        if ($imporOrder === 0 && ! empty($imporIds) && AnalisaImporMeta::whereIn('produk_id', $imporIds)->exists()) {
            $imporOrder = AnalisaImporMeta::with('produk')->whereIn('produk_id', $imporIds)->get()
                ->filter(fn ($m) => $this->impor($m)['status'] === 'po')
                ->count();
        }

        $ffOrder = collect($this->fulfillmentRows())
            ->filter(fn ($row) => $row['status'] === 'order')
            ->count();

        return [
            'total' => $lokalOrder + $imporOrder + $ffOrder,
            'lokal' => $lokalOrder,
            'impor' => $imporOrder,
            'fulfillment' => $ffOrder,
        ];
    }

    /**
     * @return array<int, int>
     */
    public function lokalEligibleProdukIds(): array
    {
        return Produk::query()
            ->where('is_active', true)
            ->whereIn('tipe', ['bahan', 'kemas'])
            // Keanggotaan eksplisit: hanya SKU yang didaftarkan (Add SKU) ke profil lokal.
            ->where('profil_analisa', 'lokal')
            ->pluck('id')
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function imporEligibleProdukIds(): array
    {
        return Produk::query()
            ->where('is_active', true)
            ->whereIn('tipe', ['bahan', 'kemas'])
            // Keanggotaan eksplisit: hanya SKU yang didaftarkan (Add SKU) ke profil impor.
            ->whereIn('profil_analisa', self::IMPOR_PROFILES)
            ->pluck('id')
            ->all();
    }

    /**
     * Bersihkan working table profil lama agar satu SKU tidak muncul ganda setelah berpindah profil/tipe.
     */
    public function cleanupWorkingTablesForProfile(Produk $produk, ?string $targetProfil): void
    {
        if ($produk->tipe === 'produk_jadi' || ! $produk->is_active) {
            AnalisaLokal::where('produk_id', $produk->id)->delete();
            RekomendasiOrderLokal::where('produk_id', $produk->id)->delete();
            LeadTimeLokal::where('produk_id', $produk->id)->delete();
            AnalisaImpor::where('produk_id', $produk->id)->delete();
            LeadTimeImpor::where('produk_id', $produk->id)->delete();

            return;
        }

        if (self::isImporProfile($targetProfil)) {
            AnalisaLokal::where('produk_id', $produk->id)->delete();
            RekomendasiOrderLokal::where('produk_id', $produk->id)->delete();
            LeadTimeLokal::where('produk_id', $produk->id)->delete();

            return;
        }

        AnalisaImpor::where('produk_id', $produk->id)->delete();
        LeadTimeImpor::where('produk_id', $produk->id)->delete();
    }

    /**
     * Sinkronisasi 2 arah Klasifikasi ABC antara Master Produk, analisa_impor, dan analisa_impor_meta.
     *
     * @param  array{promote_to_impor?: bool, ensure_analisa_row?: bool, user_id?: int|null}  $options
     */
    public function syncProdukKlasifikasiAbc(Produk $produk, ?int $klasifikasiAbcId, array $options = []): void
    {
        if ($produk->tipe === 'produk_jadi') {
            throw ValidationException::withMessages([
                'produk_id' => 'Produk Jadi tidak dapat ditetapkan ke Klasifikasi ABC.',
            ]);
        }

        $promoteToImpor = (bool) ($options['promote_to_impor'] ?? false);
        $ensureAnalisaRow = (bool) ($options['ensure_analisa_row'] ?? false);
        $userId = $options['user_id'] ?? auth()->id();

        DB::transaction(function () use ($produk, $klasifikasiAbcId, $promoteToImpor, $ensureAnalisaRow, $userId) {
            if ($promoteToImpor && ! self::isImporProfile($produk->profil_analisa)) {
                $produk->profil_analisa = 'impor';
                $this->cleanupWorkingTablesForProfile($produk, 'impor');
            }

            $abc = $klasifikasiAbcId ? KlasifikasiAbc::findOrFail($klasifikasiAbcId) : null;

            $produk->klasifikasi_abc_id = $abc?->id;
            $produk->save();

            $meta = AnalisaImporMeta::where('produk_id', $produk->id)->first();
            if ($meta) {
                $meta->klasifikasi_abc_id = $abc?->id;
                $meta->klasifikasi_abc = $abc?->kode ?? 'c';
                $meta->save();
            }

            $existingAnalisa = AnalisaImpor::where('produk_id', $produk->id)->first();
            if ($existingAnalisa) {
                $existingAnalisa->klasifikasi_abc_id = $abc?->id;
                $existingAnalisa->klasifikasi_abc = $abc?->kode ?? 'c';
                $existingAnalisa->tambahan_buffer_hari = $abc ? (int) $abc->tambahan_buffer_hari : 0;
                $existingAnalisa->save();

                $this->generateImpor($produk->id, $userId);
            } elseif ($ensureAnalisaRow) {
                $this->generateImpor($produk->id, $userId);
            }
        });
    }

    /**
     * Tetapkan banyak SKU sekaligus ke suatu Klasifikasi ABC.
     *
     * @param  array<int, int|string>  $produkIds
     */
    public function assignKlasifikasiAbcToSkus(int $klasifikasiAbcId, array $produkIds, bool $ensureAnalisaRow = false, ?int $userId = null): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $produkIds), fn ($id) => $id > 0)));
        if (empty($ids)) {
            return 0;
        }

        $abc = KlasifikasiAbc::findOrFail($klasifikasiAbcId);
        $products = Produk::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereIn('tipe', ['bahan', 'kemas'])
            ->get();

        if ($products->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'produk_ids' => 'Semua SKU yang dipilih harus berstatus aktif dan bertipe Bahan Baku atau Kemasan.',
            ]);
        }

        $updatedCount = 0;
        DB::transaction(function () use ($products, $abc, $ensureAnalisaRow, $userId, &$updatedCount) {
            foreach ($products as $produk) {
                $this->syncProdukKlasifikasiAbc($produk, $abc->id, [
                    'promote_to_impor' => true,
                    'ensure_analisa_row' => $ensureAnalisaRow,
                    'user_id' => $userId,
                ]);
                $updatedCount++;
            }
        });

        return $updatedCount;
    }

    /**
     * Tambahkan / pindahkan SKU langsung ke Analisa Lokal atau Analisa Impor (dengan Klasifikasi ABC).
     *
     * @param  array<string, mixed>  $initialParams
     */
    public function addSkuToAnalisa(Produk $produk, string $targetProfil, ?int $klasifikasiAbcId = null, array $initialParams = [], ?int $userId = null): void
    {
        if (! $produk->is_active || ! in_array($produk->tipe, ['bahan', 'kemas'], true)) {
            throw ValidationException::withMessages([
                'produk_id' => 'Hanya SKU Bahan Baku atau Kemasan yang aktif yang dapat ditambahkan ke Analisa Stok.',
            ]);
        }

        if (! in_array($targetProfil, ['lokal', 'impor'], true)) {
            throw ValidationException::withMessages([
                'target_profil' => 'Profil analisa tujuan harus Lokal atau Impor.',
            ]);
        }

        DB::transaction(function () use ($produk, $targetProfil, $klasifikasiAbcId, $initialParams, $userId) {
            if ($targetProfil === 'lokal') {
                $produk->profil_analisa = 'lokal';
                $produk->klasifikasi_abc_id = null;
                $produk->save();

                $this->cleanupWorkingTablesForProfile($produk, 'lokal');

                $hasLokalInput = array_key_exists('terjual_rata_rata_4bulan', $initialParams)
                    || array_key_exists('review_period', $initialParams)
                    || array_key_exists('harga_per_satuan', $initialParams);
                if ($hasLokalInput) {
                    $input = AnalisaLokalInput::firstOrNew(['produk_id' => $produk->id]);
                    if (array_key_exists('terjual_rata_rata_4bulan', $initialParams) && $initialParams['terjual_rata_rata_4bulan'] !== null) {
                        $input->terjual_rata_rata_4bulan = (float) $initialParams['terjual_rata_rata_4bulan'];
                    }
                    if (array_key_exists('review_period', $initialParams) && $initialParams['review_period'] !== null) {
                        $input->review_period = (int) $initialParams['review_period'];
                    }
                    if (array_key_exists('harga_per_satuan', $initialParams) && $initialParams['harga_per_satuan'] !== null) {
                        $input->harga_per_satuan = (float) $initialParams['harga_per_satuan'];
                    }
                    $input->save();
                }

                if (array_key_exists('tambahan_buffer_hari', $initialParams) && $initialParams['tambahan_buffer_hari'] !== null) {
                    LeadTimeLokal::updateOrCreate(
                        ['produk_id' => $produk->id],
                        ['tambahan_buffer_hari' => self::roundLeadTimeDays((float) $initialParams['tambahan_buffer_hari'])]
                    );
                }

                $this->generateLokal($produk->id, $userId);

                return;
            }

            if (array_key_exists('lead_time_average', $initialParams) || array_key_exists('lead_time_max', $initialParams)) {
                $lt = LeadTimeImpor::firstOrNew(['produk_id' => $produk->id]);
                if (array_key_exists('lead_time_average', $initialParams) && $initialParams['lead_time_average'] !== null) {
                    $lt->lead_time_average = (float) $initialParams['lead_time_average'];
                }
                if (array_key_exists('lead_time_max', $initialParams) && $initialParams['lead_time_max'] !== null) {
                    $lt->lead_time_max = (float) $initialParams['lead_time_max'];
                }
                $lt->save();
            }

            // Generate/sync row first so stok_saat_ini & inbound_before_eta terisi dari DB jika baris baru
            $this->syncProdukKlasifikasiAbc($produk, $klasifikasiAbcId, [
                'promote_to_impor' => true,
                'ensure_analisa_row' => true,
                'user_id' => $userId,
            ]);

            $hasImporParams = array_key_exists('out', $initialParams)
                || array_key_exists('review_period', $initialParams)
                || array_key_exists('harga_per_satuan', $initialParams);

            if ($hasImporParams) {
                $ai = AnalisaImpor::where('produk_id', $produk->id)->first();
                if ($ai) {
                    if (array_key_exists('out', $initialParams) && $initialParams['out'] !== null) {
                        $ai->out = (float) $initialParams['out'];
                    }
                    if (array_key_exists('review_period', $initialParams) && $initialParams['review_period'] !== null) {
                        $ai->review_period = (int) $initialParams['review_period'];
                    }
                    if (array_key_exists('harga_per_satuan', $initialParams) && $initialParams['harga_per_satuan'] !== null) {
                        $ai->harga_per_satuan = (float) $initialParams['harga_per_satuan'];
                    }
                    $ai->save();
                    $this->generateImpor($produk->id, $userId);
                }
            }
        });
    }
}
