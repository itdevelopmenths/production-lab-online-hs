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
        PoStage::STAGE_6 => 'pengemasan',
        PoStage::STAGE_7 => 'pengiriman',
        PoStage::STAGE_8 => 'unloading',
        PoStage::STAGE_9 => 'input',
    ];

    /** @var array<int, string> */
    public const IMPOR_PROFILES = ['impor', 'bahan_impor'];

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
     * Format satu skenario (average_hari / max_hari) dari ringkasan 9 stage Purchasing.
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

        $row['total'] = self::roundLeadTimeDays($sum);

        return $row;
    }

    /**
     * Resolusi rincian 9 tahap Lead Time untuk Tab 4 Analisa Lokal:
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
     * Perbandingan multi-supplier untuk satu produk (Bab 5, perbandingan vendor).
     */
    public function compareSuppliersForProduct(Produk $produk): array
    {
        $currentLokal = AnalisaLokal::where('produk_id', $produk->id)->first();
        $currentImpor = AnalisaImpor::where('produk_id', $produk->id)->first();
        $currentSupplierId = $currentLokal?->supplier_terpilih_id ?? $currentImpor?->supplier_terpilih_id ?? $produk->supplier_id;

        // Saring hanya relasi produk_supplier berstatus 'aktif'
        $suppliers = $produk->activeSuppliers()->withPivot(['status'])->get();

        if ($suppliers->isEmpty() && $produk->supplier) {
            $pivotStatus = DB::table('produk_supplier')
                ->where('produk_id', $produk->id)
                ->where('supplier_id', $produk->supplier_id)
                ->value('status');
            if ($pivotStatus !== 'nonaktif') {
                $suppliers = collect([$produk->supplier]);
            }
        }

        $isImpor = self::isImporProfile($produk->profil_analisa) || AnalisaImpor::where('produk_id', $produk->id)->exists();

        $adu = 0.0;
        $reviewPeriod = 15;
        $bufferDays = 0.0;

        if ($isImpor) {
            $meta = AnalisaImporMeta::where('produk_id', $produk->id)->first();
            $adu = (float) ($currentImpor?->adu_eta ?? $currentImpor?->adu_base ?? 0);
            if ($adu <= 0 && $meta) {
                $out = (float) $meta->out_total_4bulan;
                $pMulai = $meta->periode_mulai?->toDateString() ?? now()->subMonths(4)->toDateString();
                $pAkhir = $meta->periode_akhir?->toDateString() ?? now()->toDateString();
                $jHari = \Carbon\Carbon::parse($pMulai)->diffInDays(\Carbon\Carbon::parse($pAkhir)) + 1;
                $adu = $jHari > 0 ? ($out / $jHari) : 0;
            }
            $reviewPeriod = (int) ($currentImpor?->review_period ?? $meta?->review_period ?? 30);
            $bufferDays = (float) ($currentImpor?->buffer_days ?? 0);
        } else {
            $inputLokal = AnalisaLokalInput::where('produk_id', $produk->id)->first();
            $adu = (float) ($currentLokal?->adu ?? (($inputLokal?->terjual_rata_rata_4bulan ?? 0) / 30));
            $reviewPeriod = (int) ($currentLokal?->review_period ?? $inputLokal?->review_period ?? 15);
            $existingLt = LeadTimeLokal::where('produk_id', $produk->id)->first();
            $bufferDays = $existingLt && $existingLt->tambahan_buffer_hari !== null
                ? (float) $existingLt->tambahan_buffer_hari
                : (float) (Schema::hasTable('lead_time_stage') ? (DB::table('lead_time_stage')->where('produk_id', $produk->id)->sum('tambahan_buffer_hari') ?? 0) : 0);
        }
        if ($reviewPeriod <= 0) {
            $reviewPeriod = $isImpor ? 30 : 15;
        }

        $result = [];
        foreach ($suppliers as $s) {
            $pivot = $s->pivot ?? null;
            $summary = SupplierProdukLtSummary::where('supplier_id', $s->id)
                ->where('produk_id', $produk->id)
                ->first();

            $trxCount = $summary ? (int) ($summary->jumlah_transaksi ?? $summary->total_po_selesai ?? 0) : 0;

            if ($isImpor) {
                $resolvedLt = $this->resolveSupplierProdukLeadTimeImpor($produk->id, $s->id);
                $avgLt = $summary && $trxCount >= 1
                    ? self::roundLeadTimeDays((float) ($summary->average_lt_hari ?? $summary->average_lt_days ?? 0))
                    : $resolvedLt['lead_time_average'];
                $maxLt = $summary && $trxCount >= 1
                    ? self::roundLeadTimeDays((float) ($summary->max_lt_hari ?? $summary->max_lt_days ?? 0))
                    : $resolvedLt['lead_time_max'];

                $simBatasMin = round($adu * $avgLt);
                $simTargetStock = (int) floor($adu * ($avgLt + $bufferDays + $reviewPeriod) + 0.0001);
            } else {
                $resolvedLt = $this->resolveSupplierProdukLeadTimeLokal($produk->id, $s->id);
                $avgLt = $summary && $trxCount >= 1
                    ? self::roundLeadTimeDays((float) ($summary->average_lt_hari ?? $summary->average_lt_days ?? 0))
                    : $resolvedLt['total_average_lead_time'];
                $maxLt = $summary && $trxCount >= 1
                    ? self::roundLeadTimeDays((float) ($summary->max_lt_hari ?? $summary->max_lt_days ?? 0))
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
                'supplier_id' => $s->id,
                'supplier_nama' => $s->nama,
                'is_current' => (int) $s->id === (int) $currentSupplierId,
                'harga_terakhir' => $harga,
                'min_order_qty' => $minOrder,
                'avg_lt_days' => $avgLt,
                'average_lt_days' => $avgLt,
                'max_lt_days' => $maxLt,
                'total_po_selesai' => $trxCount,
                'sumber_lt' => $sumberLt,
                'sumber_lt_label' => $sumberLtLabel,
                'is_fallback' => $trxCount === 0,
                'status' => $pivot ? $pivot->status : 'aktif',
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
    public function generateLokal(?int $produkId = null, ?int $userId = null): array
    {
        $query = Produk::query()->where('is_active', true);
        if ($produkId) {
            $query->where('id', $produkId);
        } else {
            $query->whereIn('tipe', ['bahan', 'kemas'])
                ->where(function ($q) {
                    $q->where('profil_analisa', 'lokal')
                      ->orWhereNull('profil_analisa');
                });
        }

        $products = $query->orderBy('nama')->get();
        $generated = [];
        $now = now();

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
                        'pengemasan' => (int) ($oldAvg['pengemasan']->jumlah_hari ?? 0),
                        'pengiriman' => (int) ($oldAvg['pengiriman']->jumlah_hari ?? 0),
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
                        'pengemasan' => (int) ($oldMax['pengemasan']->jumlah_hari ?? 0),
                        'pengiriman' => (int) ($oldMax['pengiriman']->jumlah_hari ?? 0),
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

            $terjual = (float) ($existingInput?->terjual_rata_rata_4bulan ?? $existingAnalisa?->terjual_rata_rata_4bulan ?? 0);
            $reviewPeriod = (int) ($existingInput?->review_period ?? $existingAnalisa?->review_period ?? 15);
            if ($reviewPeriod <= 0) {
                $reviewPeriod = 15;
            }

            $adu = $terjual / 30;
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
            $stokDb = (float) Stok::where('produk_id', $p->id)->sum('qty_saat_ini');
            $stokSaatIni = ($existingInput && $existingInput->stok_saat_ini !== null)
                ? (float) $existingInput->stok_saat_ini
                : $stokDb;

            $akanDatangDb = (float) PurchaseOrderItem::where('produk_id', $p->id)
                ->whereHas('purchaseOrder', function ($q) {
                    $q->whereIn('status', ['draft', 'berjalan', 'diajukan', 'disetujui', 'diproses_supplier', 'dikirim_ke_gudang', 'parsial'])
                      ->whereNotIn('status', ['selesai', 'dibatalkan']);
                })->sum('qty');
            $akanDatang = ($existingInput && $existingInput->akan_datang !== null)
                ? (float) $existingInput->akan_datang
                : $akanDatangDb;

            $tersedia = $stokSaatIni + $akanDatang;
            $isOrder = $tersedia <= $batasMinimum;
            $selisih = $tersedia - $batasMinimum;
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

        $adu = (float) $input->terjual_rata_rata_4bulan / 30;
        $batasMinimum = round($adu * ($totalAvg + $safetyStockHari));
        $targetStock = round($adu * ($totalAvg + $safetyStockHari + (int) $input->review_period));

        $tersedia = (float) $input->stok_saat_ini + (float) $input->akan_datang;
        $isOrder = $tersedia <= $batasMinimum;
        $selisih = round($tersedia - $batasMinimum);
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

        $periodeMulai = $meta->periode_mulai?->toDateString();
        $periodeAkhir = $meta->periode_akhir?->toDateString();
        $jumlahHari = ($periodeMulai && $periodeAkhir)
            ? (\Carbon\Carbon::parse($periodeMulai)->diffInDays(\Carbon\Carbon::parse($periodeAkhir)) + 1)
            : ($meta->jumlah_hari_periode ?: 122);
        if ($jumlahHari <= 0) {
            $jumlahHari = 122;
        }

        $varianHasil = [];
        $totalQtyOrder = 0.0;
        $totalSelisih = 0.0;

        $hasVarianTable = \Illuminate\Support\Facades\Schema::hasTable('analisa_impor_varian');
        $varians = $hasVarianTable ? $meta->varian : collect();

        if ($varians->isNotEmpty()) {
            foreach ($varians as $v) {
                $out = round((float) $v->persentase_distribusi * (float) $meta->out_total_4bulan);
                $aduBase = round($jumlahHari > 0 ? ($out / $jumlahHari) : 0);
                $distribusi = (float) $v->persentase_distribusi;

                // ADU ETA proporsional per varian
                $aduEta = round($aduBase + ($reviewPeriod > 0 ? ($distribusi * ($ltAvg / $reviewPeriod)) : 0));

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
            $out = round((float) $meta->out_total_4bulan);
            $aduBase = round($jumlahHari > 0 ? ($out / $jumlahHari) : 0);
            $aduEta = round($aduBase + ($reviewPeriod > 0 ? ($ltAvg / $reviewPeriod) : 0));
            $safetyStock = round($aduEta * $bufferDays);
            $minimumStock = round($aduEta * $ltAvg);
            $targetStock = round($aduEta * ($ltAvg + $bufferDays + $reviewPeriod));

            $stokDb = (float) Stok::where('produk_id', $meta->produk_id)->sum('qty_saat_ini');
            $inboundDb = (float) PurchaseOrderItem::where('produk_id', $meta->produk_id)
                ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', ['draft', 'berjalan', 'diajukan', 'disetujui', 'diproses_supplier', 'dikirim_ke_gudang', 'parsial'])->whereNotIn('status', ['selesai', 'dibatalkan']))
                ->sum('qty');

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
    public function generateImpor(?int $produkId = null, ?int $userId = null): array
    {
        $query = Produk::query()
            ->where('is_active', true)
            ->whereIn('tipe', ['bahan', 'kemas']);

        if ($produkId) {
            $query->where('id', $produkId);
        } else {
            $metaIds = AnalisaImporMeta::pluck('produk_id');
            $query->where(function ($q) use ($metaIds) {
                $q->whereIn('profil_analisa', self::IMPOR_PROFILES)
                  ->orWhere(function ($legacy) use ($metaIds) {
                      $legacy->whereNull('profil_analisa')
                          ->where(function ($sub) use ($metaIds) {
                              $sub->whereHas('leadTimeImpor')
                                  ->orWhereHas('analisaImpor');
                              if ($metaIds->isNotEmpty()) {
                                  $sub->orWhereIn('id', $metaIds);
                              }
                          });
                  });
            });
        }

        $products = $query->orderBy('nama')->get();

        $generated = [];
        $now = now();

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
            $out = (float) ($existingAnalisa?->out ?? $meta?->out_total_4bulan ?? 0);
            $harga = (float) ($existingAnalisa?->harga_per_satuan ?? $meta?->harga_per_satuan ?? $p->harga_hpp ?? 0);
            $moq = (float) ($p->satuan_order_moq ?: 1);

            $periodeMulai = $existingAnalisa?->periode_mulai?->toDateString()
                ?? $meta?->periode_mulai?->toDateString()
                ?? now()->subMonths(4)->toDateString();
            $periodeAkhir = $existingAnalisa?->periode_akhir?->toDateString()
                ?? $meta?->periode_akhir?->toDateString()
                ?? now()->toDateString();
            $jumlahHari = \Carbon\Carbon::parse($periodeMulai)->diffInDays(\Carbon\Carbon::parse($periodeAkhir)) + 1;
            if ($jumlahHari <= 0) {
                $jumlahHari = 122;
            }

            // Hitung ADU Base & ADU ETA Produk Master dengan presisi desimal riil seperti Excel
            $aduBase = $jumlahHari > 0 ? ($out / $jumlahHari) : 0;
            $aduEta = $aduBase + ($reviewPeriod > 0 ? ($ltAvg / $reviewPeriod) : 0);

            $totalSafetyStock = round($aduEta * $bufferDays);
            $totalMinStock = round($aduEta * $ltAvg);
            $totalTargetStock = (int) floor($aduEta * ($ltAvg + $bufferDays + $reviewPeriod) + 0.0001);

            $stokDb = (float) Stok::where('produk_id', $p->id)->sum('qty_saat_ini');
            $totalStok = round($existingAnalisa ? (float) $existingAnalisa->stok_saat_ini : $stokDb);

            $akanDatangDb = (float) PurchaseOrderItem::where('produk_id', $p->id)
                ->whereHas('purchaseOrder', function ($q) {
                    $q->whereIn('status', ['draft', 'berjalan', 'diajukan', 'disetujui', 'diproses_supplier', 'dikirim_ke_gudang', 'parsial'])
                      ->whereNotIn('status', ['selesai', 'dibatalkan']);
                })->sum('qty');
            $totalInbound = round($existingAnalisa ? (float) $existingAnalisa->inbound_before_eta : $akanDatangDb);

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

            // 3. Finalisasi Produk Jadi Fulfillment
            if ($tipe === 'produk_jadi_fulfillment' || $tipe === 'all') {
                $grouped = AnalisaFulfillmentInput::with(['produk', 'gudang'])->get()->groupBy('produk_id');
                foreach ($grouped as $rows) {
                    $produk = $rows->first()->produk;
                    $h = $this->fulfillment($rows, (float) ($produk?->satuan_order_moq ?? 1));
                    RiwayatAnalisa::create([
                        'session_id' => $sessionId,
                        'tanggal' => $today,
                        'tipe' => 'produk_jadi_fulfillment',
                        'item_label' => $produk?->sku ?? '-',
                        'batas_minimum' => (float) $h['batas_minimum_total'],
                        'target_stock' => (float) $h['target_stock_total'],
                        'status' => $h['status'],
                        'qty_order' => (float) $h['qty_order'],
                        'dicatat_oleh' => $actorId,
                        'is_locked' => true,
                        'detail_payload' => [
                            'nama' => $produk?->nama,
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
    public function fulfillment($rows, float $moq = 1): array
    {
        $perGudang = [];
        $batasMinTotal = 0.0;
        $targetTotal = 0.0;
        $stokTotal = 0.0;
        $akanDatang = 0.0;

        foreach ($rows as $r) {
            $adu = round((float) $r->terjual_rata_rata_4bulan / 30);
            $batasMin = $adu * ((int) $r->lead_time_distribusi + (int) $r->buffer_distribusi);
            $target = $adu * ((int) $r->lead_time_distribusi + (int) $r->buffer_distribusi + (int) $r->review_period);

            $batasMinTotal += $batasMin;
            $targetTotal += $target;
            $stokTotal += (float) $r->stok_saat_ini;
            // Akan Datang hanya diambil dari gudang penerima langsung (fulfillment_pusat).
            $akanDatang += (float) ($r->akan_datang ?? 0);

            $perGudang[] = [
                'gudang' => $r->gudang?->nama,
                'adu' => $adu,
                'batas_minimum' => $batasMin,
                'target_stock' => $target,
                'stok_saat_ini' => (float) $r->stok_saat_ini,
            ];
        }

        $stokAll = $stokTotal + $akanDatang;
        $isOrder = $stokAll <= $targetTotal;
        $selisih = $stokAll - $targetTotal;
        $qtyOrder = $isOrder ? $this->bulatkanMoq(abs($selisih), $moq) : 0;

        return [
            'per_gudang' => $perGudang,
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
            $adu = round((float) $r->terjual_rata_rata_4bulan / 30);
            $batasMin = $adu * ((int) $r->lead_time_distribusi + (int) $r->buffer_distribusi);
            $key = "{$r->produk_id}_{$r->gudang_id}";
            $byProductGudang[$key] = $batasMin;

            $byProduct[$r->produk_id] = ($byProduct[$r->produk_id] ?? 0) + $batasMin;
        }

        return [
            'by_product' => $byProduct,
            'by_product_gudang' => $byProductGudang,
        ];
    }

    /**
     * Ambil Batas Minimum spesifik untuk stok suatu produk di gudang tertentu.
     */
    public function getBatasMinimumForStok(int $produkId, ?int $gudangId = null, ?array $map = null): float
    {
        $map = $map ?? $this->getBatasMinimumMap();
        if ($gudangId !== null && isset($map['by_product_gudang']["{$produkId}_{$gudangId}"])) {
            return (float) $map['by_product_gudang']["{$produkId}_{$gudangId}"];
        }

        return (float) ($map['by_product'][$produkId] ?? 0.0);
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

        $ffOrder = AnalisaFulfillmentInput::with(['gudang', 'produk'])->get()
            ->groupBy('produk_id')
            ->filter(fn ($rows) => $this->fulfillment($rows)['status'] === 'order')
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
            ->where(function ($q) {
                $q->where('profil_analisa', 'lokal')
                  ->orWhereNull('profil_analisa');
            })
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
            ->where(function ($q) {
                $q->whereIn('profil_analisa', self::IMPOR_PROFILES)
                  ->orWhereNull('profil_analisa');
            })
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
