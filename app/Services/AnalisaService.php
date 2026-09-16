<?php

namespace App\Services;

use App\Models\AnalisaFulfillmentInput;
use App\Models\AnalisaImporMeta;
use App\Models\AnalisaLokal;
use App\Models\AnalisaLokalInput;
use App\Models\LeadTimeLokal;
use App\Models\LeadTimeLokalStage;
use App\Models\LeadTimeStage;
use App\Models\Produk;
use App\Models\PurchaseOrderItem;
use App\Models\RekomendasiOrderLokal;
use App\Models\Stok;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replikasi rumus Analisa Stok dari 3 file Excel acuan (PRD Bab 6):
 * Bahan Lokal, Bahan Impor, Produk Jadi (Fulfillment).
 * Sumber tunggal Batas Minimum & Target Stock bagi seluruh sistem.
 */
class AnalisaService
{
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

            $totalAvg = $avgStage ? $avgStage->totalHari() : 0;
            $totalMax = $maxStage ? $maxStage->totalHari() : 0;

            // 2. Summary Lead Time & Safety Stock
            $ltLokal = LeadTimeLokal::firstOrNew(['produk_id' => $p->id]);
            $buffer = $ltLokal->exists
                ? (int) ($ltLokal->tambahan_buffer_hari ?? 0)
                : (int) (DB::table('lead_time_stage')->where('produk_id', $p->id)->sum('tambahan_buffer_hari') ?? 0);
            $safetyStock = max(0, $totalMax - $totalAvg) + $buffer;

            $ltLokal->fill([
                'total_average_lead_time' => $totalAvg,
                'total_max_lead_time' => $totalMax,
                'tambahan_buffer_hari' => $buffer,
                'safety_stock' => $safetyStock,
            ])->save();

            // 3. Analisa Lokal (Working Table)
            $existingAnalisa = AnalisaLokal::where('produk_id', $p->id)->first();
            $existingInput = AnalisaLokalInput::where('produk_id', $p->id)->first();

            $terjual = (float) ($existingAnalisa?->terjual_rata_rata_4bulan ?? $existingInput?->terjual_rata_rata_4bulan ?? 0);
            $reviewPeriod = (int) ($existingAnalisa?->review_period ?? $existingInput?->review_period ?? 15);
            if ($reviewPeriod <= 0) {
                $reviewPeriod = 15;
            }

            $adu = $terjual / 30;
            $batasMinimum = round($adu * ($totalAvg + $safetyStock), 2);
            $targetStock = round($adu * ($totalAvg + $safetyStock + $reviewPeriod), 2);

            $analisaLokal = AnalisaLokal::updateOrCreate(
                ['produk_id' => $p->id],
                [
                    'total_average_lead_time' => $totalAvg,
                    'safety_stock' => $safetyStock,
                    'terjual_rata_rata_4bulan' => $terjual,
                    'adu' => round($adu, 4),
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
                    $q->whereIn('status', ['draft', 'diajukan', 'disetujui', 'dikirim_ke_gudang']);
                })->sum('qty');
            $akanDatang = ($existingInput && $existingInput->akan_datang !== null)
                ? (float) $existingInput->akan_datang
                : $akanDatangDb;

            $tersedia = $stokSaatIni + $akanDatang;
            $isOrder = $tersedia <= $batasMinimum;
            $selisih = round($tersedia - $batasMinimum, 2);
            $status = $isOrder ? 'order' : 'tidak';

            $moq = (float) ($p->satuan_order_moq ?: 1);
            $rumusMoq = $isOrder ? $this->bulatkanMoq(abs($selisih), $moq) : 0.0;

            $faktor = (float) ($p->faktor_konversi ?: 1);
            $rekomendasiOrder = ($faktor > 0) ? round($rumusMoq / $faktor, 2) : $rumusMoq;

            $hargaSatuan = (float) ($existingInput?->harga_per_satuan ?: ($p->harga_hpp ?: 0));
            $totalNominal = round($rumusMoq * $hargaSatuan, 2);

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
        $batasMinimum = $adu * ($totalAvg + $safetyStockHari);
        $targetStock = $adu * ($totalAvg + $safetyStockHari + (int) $input->review_period);

        $tersedia = (float) $input->stok_saat_ini + (float) $input->akan_datang;
        $isOrder = $tersedia <= $batasMinimum;
        $selisih = $tersedia - $batasMinimum;
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
            'total_nominal_order' => $qtyOrder * (float) $input->harga_per_satuan,
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
        $reviewPeriod = (int) $meta->review_period;
        $bufferDays = max(0, $ltMax - $ltAvg) + $meta->tambahanHariAbc();

        $varianHasil = [];
        $totalQtyOrder = 0.0;
        $totalSelisih = 0.0;

        foreach ($meta->varian as $v) {
            $out = (float) $v->persentase_distribusi * (float) $meta->out_total_4bulan;
            $aduBase = $out / 122;

            $aduEta = $meta->punya_varian
                ? $aduBase + ($aduBase * $ltAvg / 30)
                : $aduBase + ($reviewPeriod > 0 ? $ltAvg / $reviewPeriod : 0);

            $safetyStock = $aduEta * $bufferDays;
            $minimumStock = $aduEta * $ltAvg;
            $targetStock = $aduEta * ($ltAvg + $reviewPeriod) + $safetyStock;
            $proyeksi = (float) $v->stok_saat_ini + (float) $v->inbound_before_eta - ($aduEta * $ltAvg);
            $selisih = $proyeksi - $targetStock;
            $isPo = $selisih < 0;
            $qtyOrder = $isPo ? $this->bulatkanMoq(abs($selisih), $moq) : 0;

            $totalQtyOrder += $qtyOrder;
            $totalSelisih += $selisih;

            $varianHasil[] = [
                'nama_varian' => $v->nama_varian,
                'adu_base' => $aduBase,
                'adu_eta' => $aduEta,
                'buffer_days' => $bufferDays,
                'safety_stock' => $safetyStock,
                'minimum_stock' => $minimumStock,
                'target_stock' => $targetStock,
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
            $adu = (float) $r->terjual_rata_rata_4bulan / 30;
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

        // 1. Profil Bahan Lokal (prioritaskan tabel analisa_lokal tersimpan)
        $analisaLokalList = AnalisaLokal::all();
        if ($analisaLokalList->isNotEmpty()) {
            foreach ($analisaLokalList as $al) {
                $byProduct[$al->produk_id] = (float) $al->batas_minimum;
            }
        } else {
            $lokalInputs = AnalisaLokalInput::with('produk')->get();
            foreach ($lokalInputs as $input) {
                $hasil = $this->lokal($input);
                $min = (float) ($hasil['batas_minimum'] ?? 0);
                $byProduct[$input->produk_id] = $min;
            }
        }

        // 2. Profil Bahan Impor
        $imporMetas = AnalisaImporMeta::with(['produk', 'varian'])->get();
        foreach ($imporMetas as $meta) {
            $hasil = $this->impor($meta);
            $minTotal = 0.0;
            foreach ($hasil['varian'] as $v) {
                $minTotal += (float) ($v['minimum_stock'] ?? 0);
            }
            $byProduct[$meta->produk_id] = $minTotal;
        }

        // 3. Profil Produk Jadi (Fulfillment)
        $ffInputs = AnalisaFulfillmentInput::all();
        foreach ($ffInputs as $r) {
            $adu = (float) $r->terjual_rata_rata_4bulan / 30;
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
        $lokalOrder = RekomendasiOrderLokal::where('status', 'order')->count();
        if ($lokalOrder === 0 && AnalisaLokalInput::exists()) {
            $lokalOrder = AnalisaLokalInput::with('produk')->get()
                ->filter(fn ($i) => $this->lokal($i)['status'] === 'order')
                ->count();
        }

        $imporOrder = AnalisaImporMeta::with(['produk', 'varian'])->get()
            ->filter(fn ($m) => $this->impor($m)['status'] === 'po')
            ->count();

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
}
