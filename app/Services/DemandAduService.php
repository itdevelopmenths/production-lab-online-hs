<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * ADU (Average Daily Usage) per gudang yang dihitung dari PERMINTAAN barang (requests)
 * selama 4 bulan terakhir:
 *
 *  - Gudang Pusat (bahan baku): permintaan bahan baku (request_bahan) dari Gudang Produksi.
 *    ADU = total qty diminta / jumlah hari jendela, per produk.
 *  - Gudang Produksi (operasional): permintaan produk jadi (request_produk_jadi) dari Gudang Fulfillment.
 *    ADU = total qty diminta / jumlah hari jendela, per produk per gudang produksi pengirim.
 *
 * Dalam tabel `requests`, gudang_asal_id = gudang pemohon dan gudang_tujuan_id = gudang pengirim.
 * Hanya request berstatus disetujui / diproses / selesai yang dihitung (qty_diminta).
 * Tanggal request = waktu disetujui (fallback tanggal dibuat).
 */
class DemandAduService
{
    public const WINDOW_MONTHS = 4;

    public const COUNTED_STATUS = ['disetujui', 'diproses', 'selesai'];

    /**
     * @return array{start: Carbon, end: Carbon, days: int}
     */
    public function window(?CarbonInterface $end = null): array
    {
        $end = Carbon::instance(($end ?? now()))->endOfDay();
        $start = $end->copy()->subMonths(self::WINDOW_MONTHS)->startOfDay();
        $days = Carbon::parse($start->toDateString())->diffInDays(Carbon::parse($end->toDateString())) + 1;

        return ['start' => $start, 'end' => $end, 'days' => max(1, (int) $days)];
    }

    /**
     * ADU Gudang Pusat per produk dari request bahan baku yang dikirim Gudang Pusat (bahan_baku).
     *
     * @param  array<int, int>|null  $produkIds  null = semua produk
     * @return array<int, array{total: float, adu: float}>
     */
    public function aduBahanPusat(?array $produkIds = null, ?CarbonInterface $end = null): array
    {
        $w = $this->window($end);

        $rows = $this->baseQuery('request_bahan', ['bahan_baku'], $w)
            ->when($produkIds !== null, fn ($q) => $q->whereIn('ri.produk_id', $produkIds))
            ->select('ri.produk_id', DB::raw('SUM(ri.qty_diminta) as total'))
            ->groupBy('ri.produk_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $total = (float) $r->total;
            $out[(int) $r->produk_id] = ['total' => $total, 'adu' => $total / $w['days']];
        }

        return $out;
    }

    /**
     * ADU Gudang Produksi per produk jadi dari request produk jadi yang dikirim Gudang Produksi (operasional).
     *
     * @param  array<int, int>|null  $produkIds  null = semua produk
     * @return array<string, array{produk_id: int, gudang_id: int, total: float, adu: float}>  key "{produk_id}_{gudang_id}"
     */
    public function aduProdukJadiProduksi(?array $produkIds = null, ?CarbonInterface $end = null): array
    {
        $w = $this->window($end);

        $rows = $this->baseQuery('request_produk_jadi', ['operasional'], $w)
            ->when($produkIds !== null, fn ($q) => $q->whereIn('ri.produk_id', $produkIds))
            ->select('ri.produk_id', 'r.gudang_tujuan_id as gudang_id', DB::raw('SUM(ri.qty_diminta) as total'))
            ->groupBy('ri.produk_id', 'r.gudang_tujuan_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $total = (float) $r->total;
            $out[$r->produk_id . '_' . $r->gudang_id] = [
                'produk_id' => (int) $r->produk_id,
                'gudang_id' => (int) $r->gudang_id,
                'total' => $total,
                'adu' => $total / $w['days'],
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $tipePengirim
     * @param  array{start: Carbon, end: Carbon, days: int}  $w
     */
    private function baseQuery(string $jenis, array $tipePengirim, array $w)
    {
        return DB::table('request_items as ri')
            ->join('requests as r', 'r.id', '=', 'ri.request_id')
            ->join('gudang as g', 'g.id', '=', 'r.gudang_tujuan_id')
            ->where('r.jenis', $jenis)
            ->whereIn('r.status', self::COUNTED_STATUS)
            ->whereIn('g.tipe', $tipePengirim)
            ->whereRaw('COALESCE(r.disetujui_pada, r.created_at) >= ?', [$w['start']->toDateTimeString()])
            ->whereRaw('COALESCE(r.disetujui_pada, r.created_at) <= ?', [$w['end']->toDateTimeString()]);
    }
}
