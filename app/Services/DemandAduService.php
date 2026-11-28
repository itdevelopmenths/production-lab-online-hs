<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sumber ADU (Average Daily Usage) berantai, mengikuti alur Excel analisa:
 *
 *  1. Produk jadi (Gudang Fulfillment): ADU penjualan per gudang = Terjual rata-rata/bulan ÷ 30 (tanpa pembulatan),
 *     diinput di Analisa Fulfillment. ADU produk jadi = Σ ADU seluruh gudang fulfillment.
 *  2. Gudang Produksi: memakai ADU produk jadi di atas untuk menentukan kebutuhan produksi.
 *  3. Bahan baku/kemas (Gudang Pusat): ADU = pemakaian bahan riil di batch produksi
 *     (kartu stok keluar ber-referensi batch_produksi) selama 4 bulan ÷ jumlah hari jendela.
 *     Bahan tanpa riwayat pemakaian memakai proyeksi BOM: Σ (ADU produk jadi × takaran per unit).
 */
class DemandAduService
{
    public const WINDOW_MONTHS = 4;

    public const SUMBER_PEMAKAIAN = 'pemakaian_produksi';

    public const SUMBER_BOM = 'proyeksi_bom';

    /**
     * @return array{start: Carbon, end: Carbon, days: int}
     */
    public function window(?CarbonInterface $end = null): array
    {
        $end = Carbon::instance(($end ?? now()))->endOfDay();
        $start = $end->copy()->subMonths(self::WINDOW_MONTHS)->startOfDay();

        return $this->windowBetween($start, $end);
    }

    /**
     * Jendela dengan batas tanggal eksplisit (inklusif). Jumlah hari = selisih tanggal + 1.
     *
     * @return array{start: Carbon, end: Carbon, days: int}
     */
    public function windowBetween(CarbonInterface $start, CarbonInterface $end): array
    {
        $start = Carbon::instance($start)->startOfDay();
        $end = Carbon::instance($end)->endOfDay();
        $days = Carbon::parse($start->toDateString())->diffInDays(Carbon::parse($end->toDateString())) + 1;

        return ['start' => $start, 'end' => $end, 'days' => max(1, (int) $days)];
    }

    /** Pembagi ADU bahan lokal (Excel Analisa Lokal): rata-rata per bulan ÷ 30 = total 4 bulan ÷ 120. */
    public const HARI_PER_BULAN_LOKAL = 30;

    /**
     * ADU Gudang Pusat per bahan: pemakaian riil di batch produksi, fallback proyeksi BOM.
     *  - adu       : total ÷ jumlah hari jendela sebenarnya (dipakai Analisa Impor, Excel ÷122).
     *  - adu_lokal : (total ÷ 4 bulan) ÷ 30 (dipakai Analisa Lokal, sesuai Excel "Terjual rata-rata ÷ 30").
     *
     * @param  array<int, int>|null  $produkIds  null = semua produk
     * @param  array{start: Carbon, end: Carbon, days: int}|null  $window  jendela eksplisit (mis. periode impor); null = jendela 4 bulan
     * @return array<int, array{total: float, adu: float, adu_lokal: float, sumber: string}>
     */
    public function aduBahanPusat(?array $produkIds = null, ?CarbonInterface $end = null, ?array $window = null): array
    {
        $w = $window ?? $this->window($end);
        $out = [];
        $hariLokal = self::WINDOW_MONTHS * self::HARI_PER_BULAN_LOKAL;

        foreach ($this->pemakaianProduksi($produkIds, $w) as $pid => $total) {
            if ($total > 0) {
                $out[$pid] = [
                    'total' => $total,
                    'adu' => $total / $w['days'],
                    'adu_lokal' => $total / $hariLokal,
                    'sumber' => self::SUMBER_PEMAKAIAN,
                ];
            }
        }

        foreach ($this->proyeksiBom($produkIds) as $pid => $adu) {
            if (! isset($out[$pid]) && $adu > 0) {
                $out[$pid] = ['total' => $adu * $w['days'], 'adu' => $adu, 'adu_lokal' => $adu, 'sumber' => self::SUMBER_BOM];
            }
        }

        return $out;
    }

    /**
     * ADU produk jadi dari Analisa Fulfillment: Σ (terjual rata-rata/bulan ÷ 30) per gudang fulfillment, tanpa pembulatan.
     *
     * @param  array<int, int>|null  $produkIds  null = semua produk jadi di analisa fulfillment
     * @return array<int, array{adu: float, terjual_bulanan: float, jumlah_gudang: int}>
     */
    public function aduProdukJadi(?array $produkIds = null): array
    {
        $rows = DB::table('analisa_fulfillment_input')
            ->when($produkIds !== null, fn ($q) => $q->whereIn('produk_id', $produkIds))
            ->get(['produk_id', 'terjual_rata_rata_4bulan']);

        $out = [];
        foreach ($rows as $r) {
            $pid = (int) $r->produk_id;
            $terjual = (float) $r->terjual_rata_rata_4bulan;
            $out[$pid] ??= ['adu' => 0.0, 'terjual_bulanan' => 0.0, 'jumlah_gudang' => 0];
            $out[$pid]['adu'] += $terjual / 30; // tanpa pembulatan, sesuai Excel
            $out[$pid]['terjual_bulanan'] += $terjual;
            $out[$pid]['jumlah_gudang']++;
        }

        return $out;
    }

    /**
     * Total qty bahan keluar untuk batch produksi dalam jendela.
     *
     * @param  array<int, int>|null  $produkIds
     * @param  array{start: Carbon, end: Carbon, days: int}  $w
     * @return array<int, float>
     */
    private function pemakaianProduksi(?array $produkIds, array $w): array
    {
        return DB::table('kartu_stok as ks')
            ->join('produk as p', 'p.id', '=', 'ks.produk_id')
            ->where('ks.referensi_tipe', 'batch_produksi')
            ->where('ks.tipe', 'out')
            ->where('p.tipe', '!=', 'produk_jadi')
            // Batas pakai datetime: kolom date bisa tersimpan sebagai 'Y-m-d H:i:s' (cast Eloquent / SQLite).
            ->whereBetween('ks.tanggal', [$w['start']->toDateTimeString(), $w['end']->toDateTimeString()])
            ->when($produkIds !== null, fn ($q) => $q->whereIn('ks.produk_id', $produkIds))
            ->groupBy('ks.produk_id')
            ->get(['ks.produk_id', DB::raw('SUM(ks.qty) as total')])
            ->mapWithKeys(fn ($r) => [(int) $r->produk_id => (float) $r->total])
            ->all();
    }

    /**
     * Proyeksi ADU bahan dari BOM: Σ (ADU produk jadi × takaran bahan per unit).
     *
     * @param  array<int, int>|null  $produkIds
     * @return array<int, float>
     */
    private function proyeksiBom(?array $produkIds): array
    {
        $boms = DB::table('bom')
            ->when($produkIds !== null, fn ($q) => $q->whereIn('bahan_id', $produkIds))
            ->get(['produk_jadi_id', 'bahan_id', 'qty_per_unit']);
        if ($boms->isEmpty()) {
            return [];
        }

        $aduPj = $this->aduProdukJadi($boms->pluck('produk_jadi_id')->map(fn ($id) => (int) $id)->unique()->values()->all());

        $out = [];
        foreach ($boms as $b) {
            $adu = (float) ($aduPj[(int) $b->produk_jadi_id]['adu'] ?? 0.0);
            if ($adu > 0) {
                $out[(int) $b->bahan_id] = ($out[(int) $b->bahan_id] ?? 0.0) + $adu * (float) $b->qty_per_unit;
            }
        }

        return $out;
    }
}
