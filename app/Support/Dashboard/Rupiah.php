<?php

namespace App\Support\Dashboard;

class Rupiah
{
    /** Format ringkas untuk tile: Rp 950 rb, Rp 850 jt, Rp 1,2 M, Rp 2,5 T. */
    public static function ringkas(float $nilai): string
    {
        $abs = abs($nilai);
        $units = [
            [1_000_000_000_000, 'T'],
            [1_000_000_000, 'M'],
            [1_000_000, 'jt'],
            [1_000, 'rb'],
        ];

        foreach ($units as [$batas, $unit]) {
            if ($abs >= $batas) {
                $angka = rtrim(rtrim(number_format($nilai / $batas, 1, ',', '.'), '0'), ',');

                return "Rp {$angka} {$unit}";
            }
        }

        return 'Rp '.number_format($nilai, 0, ',', '.');
    }

    /** Format penuh dengan pemisah ribuan: Rp 1.234.567.890. */
    public static function penuh(float $nilai): string
    {
        return 'Rp '.number_format($nilai, 0, ',', '.');
    }
}
