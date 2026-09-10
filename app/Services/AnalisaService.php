<?php

namespace App\Services;

use App\Models\AnalisaFulfillmentInput;
use App\Models\AnalisaImporMeta;
use App\Models\AnalisaLokalInput;
use App\Models\LeadTimeStage;

/**
 * Replikasi rumus Analisa Stok dari 3 file Excel acuan (PRD Bab 6):
 * Bahan Lokal, Bahan Impor, Produk Jadi (Fulfillment).
 * Sumber tunggal Batas Minimum & Target Stock bagi seluruh sistem.
 */
class AnalisaService
{
    /**
     * Profil Bahan Lokal — hitung satu baris analisa untuk satu produk.
     *
     * @return array<string,mixed>
     */
    public function lokal(AnalisaLokalInput $input): array
    {
        $moq = (float) ($input->produk->satuan_order_moq ?? 1);

        $totalAvg = (float) LeadTimeStage::where('produk_id', $input->produk_id)
            ->where('skenario', 'average')->sum('jumlah_hari');
        $totalMax = (float) LeadTimeStage::where('produk_id', $input->produk_id)
            ->where('skenario', 'max')->sum('jumlah_hari');
        $tambahanBuffer = (float) LeadTimeStage::where('produk_id', $input->produk_id)
            ->sum('tambahan_buffer_hari');

        // Safety Stock dalam satuan HARI (identik Total Buffer).
        $safetyStockHari = max(0, $totalMax - $totalAvg) + $tambahanBuffer;

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
}
