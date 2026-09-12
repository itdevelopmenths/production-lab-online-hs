<?php

namespace App\Helpers;

class NumberHelper
{
    /**
     * Format kuantitas tanpa desimal .00 statis.
     * Angka bulat tampil utuh (misal: 20 atau 1.500).
     * Angka pecahan tampil sesuai desimal riilnya (misal: 12,5).
     */
    public static function formatQty(float|int|string|null $qty, ?string $nullPlaceholder = null): string
    {
        if ($qty === null) {
            return $nullPlaceholder ?? '0';
        }

        $val = (float) $qty;

        if (floor($val) == $val) {
            return number_format($val, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($val, 2, ',', '.'), '0'), ',');
    }

    /**
     * Format mata uang Rupiah standar.
     */
    public static function formatRupiah(float|int|string|null $amount): string
    {
        if ($amount === null) {
            return 'Rp 0';
        }

        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }
}
