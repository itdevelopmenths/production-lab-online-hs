<?php

namespace App\Services\Purchasing;

class PurchasingCostCalculator
{
    /**
     * Hitung total bersih (Net Total) per item setelah komponen diskon, PPN, ongkir, dan adjustment.
     */
    public function calculateItemNet(
        float $hargaTotal,
        float $diskon = 0,
        float $ppn = 0,
        float $ongkir = 0,
        float $adjustment = 0
    ): float {
        $net = $hargaTotal - $diskon + $ppn + $ongkir + $adjustment;

        return max(0, round($net, 2));
    }

    /**
     * Hitung HPP per satuan produk.
     */
    public function calculateHppPerSatuan(float $netTotal, float $qty): float
    {
        if ($qty <= 0) {
            return 0.0;
        }

        return round($netTotal / $qty, 4);
    }

    /**
     * Hitung dan alokasikan total biaya PO dan baris-baris item pengadaan.
     *
     * @param  array<int, array{
     *     produk_id: int|string,
     *     qty: float|int,
     *     harga_total: float|int,
     *     diskon?: float|int,
     *     ppn?: float|int,
     *     ongkir?: float|int,
     *     adjustment?: float|int
     * }>  $items
     * @return array{
     *     subtotal_produk: float,
     *     diskon_total: float,
     *     ppn_nominal: float,
     *     ongkos_kirim: float,
     *     adjustment: float,
     *     grand_total: float,
     *     items: array<int, array{
     *         produk_id: int|string,
     *         qty: float,
     *         harga_total: float,
     *         diskon: float,
     *         ppn: float,
     *         ongkir: float,
     *         adjustment: float,
     *         net_total: float,
     *         hpp_per_satuan: float
     *     }>
     * }
     */
    public function calculatePoTotals(
        array $items,
        float $headerDiskon = 0,
        float $headerPpn = 0,
        float $headerOngkir = 0,
        float $headerAdjustment = 0
    ): array {
        $subtotalProduk = 0.0;
        $itemDiskonSum = 0.0;
        $itemPpnSum = 0.0;
        $itemOngkirSum = 0.0;
        $itemAdjSum = 0.0;

        foreach ($items as $item) {
            $subtotalProduk += (float) ($item['harga_total'] ?? 0);
            $itemDiskonSum += (float) ($item['diskon'] ?? 0);
            $itemPpnSum += (float) ($item['ppn'] ?? 0);
            $itemOngkirSum += (float) ($item['ongkir'] ?? 0);
            $itemAdjSum += (float) ($item['adjustment'] ?? 0);
        }

        $totalDiskon = round($itemDiskonSum + $headerDiskon, 2);
        $totalPpn = round($itemPpnSum + $headerPpn, 2);
        $totalOngkir = round($itemOngkirSum + $headerOngkir, 2);
        $totalAdj = round($itemAdjSum + $headerAdjustment, 2);

        $processedItems = [];
        $count = count($items);

        foreach ($items as $index => $item) {
            $faktor = (float) ($item['faktor_konversi'] ?? 1.0000);
            if ($faktor <= 0) {
                $faktor = 1.0000;
            }

            $qtySatuanBeli = isset($item['qty_satuan_beli']) && $item['qty_satuan_beli'] !== null && $item['qty_satuan_beli'] !== ''
                ? (float) $item['qty_satuan_beli']
                : null;

            // Jika qty_satuan_beli diisi, kuantitas dasar adalah qty_satuan_beli * faktor
            // Jika tidak, gunakan item['qty']
            if ($qtySatuanBeli !== null) {
                $qty = round($qtySatuanBeli * $faktor, 2);
            } else {
                $qty = (float) ($item['qty'] ?? 0);
            }

            $hargaTotal = (float) ($item['harga_total'] ?? 0);

            // Item-level components
            $d = (float) ($item['diskon'] ?? 0);
            $p = (float) ($item['ppn'] ?? 0);
            $o = (float) ($item['ongkir'] ?? 0);
            $a = (float) ($item['adjustment'] ?? 0);

            // Alokasi header prorata jika ada
            if ($subtotalProduk > 0) {
                $ratio = $hargaTotal / $subtotalProduk;
                $d += round($headerDiskon * $ratio, 2);
                $p += round($headerPpn * $ratio, 2);
                $o += round($headerOngkir * $ratio, 2);
                $a += round($headerAdjustment * $ratio, 2);
            } elseif ($count > 0) {
                $d += round($headerDiskon / $count, 2);
                $p += round($headerPpn / $count, 2);
                $o += round($headerOngkir / $count, 2);
                $a += round($headerAdjustment / $count, 2);
            }

            $netTotal = $this->calculateItemNet($hargaTotal, $d, $p, $o, $a);
            $hpp = $this->calculateHppPerSatuan($netTotal, $qty);

            $processedItems[] = [
                'produk_id' => $item['produk_id'],
                'qty' => $qty,
                'qty_satuan_beli' => $qtySatuanBeli,
                'satuan_beli' => $item['satuan_beli'] ?? null,
                'faktor_konversi' => $faktor,
                'harga_total' => round($hargaTotal, 2),
                'diskon' => round($d, 2),
                'ppn' => round($p, 2),
                'ongkir' => round($o, 2),
                'adjustment' => round($a, 2),
                'net_total' => $netTotal,
                'hpp_per_satuan' => $hpp,
            ];
        }

        $grandTotal = max(0, round($subtotalProduk - $totalDiskon + $totalPpn + $totalOngkir + $totalAdj, 2));

        return [
            'subtotal_produk' => round($subtotalProduk, 2),
            'diskon_total' => $totalDiskon,
            'ppn_nominal' => $totalPpn,
            'ongkos_kirim' => $totalOngkir,
            'adjustment' => $totalAdj,
            'grand_total' => $grandTotal,
            'items' => $processedItems,
        ];
    }
}
