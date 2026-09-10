<?php

namespace App\Services;

use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Stok;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mesin kartu stok (append-only ledger) + saldo Stok per produk+gudang.
 * Semua pergerakan stok fisik harus lewat service ini agar saldo & kartu stok konsisten.
 */
class StokService
{
    /**
     * Catat mutasi masuk (in). Menambah saldo gudang + append kartu stok.
     */
    public function masuk(int $produkId, int $gudangId, float $qty, string $referensiTipe, ?int $referensiId = null, ?string $catatan = null, ?Carbon $tanggal = null): KartuStok
    {
        return $this->post('in', $produkId, $gudangId, $qty, $referensiTipe, $referensiId, $catatan, $tanggal);
    }

    /**
     * Catat mutasi keluar (out). Mengurangi saldo gudang + append kartu stok.
     */
    public function keluar(int $produkId, int $gudangId, float $qty, string $referensiTipe, ?int $referensiId = null, ?string $catatan = null, ?Carbon $tanggal = null): KartuStok
    {
        return $this->post('out', $produkId, $gudangId, $qty, $referensiTipe, $referensiId, $catatan, $tanggal);
    }

    /**
     * Pindah stok antar gudang (keluar dari asal, masuk ke tujuan) dalam satu transaksi.
     *
     * @return array{keluar: KartuStok, masuk: KartuStok}
     */
    public function pindah(int $produkId, int $gudangAsalId, int $gudangTujuanId, float $qtyKeluar, float $qtyMasuk, string $referensiTipe, ?int $referensiId = null, ?string $catatan = null, ?Carbon $tanggal = null): array
    {
        return DB::transaction(function () use ($produkId, $gudangAsalId, $gudangTujuanId, $qtyKeluar, $qtyMasuk, $referensiTipe, $referensiId, $catatan, $tanggal) {
            $out = $this->post('out', $produkId, $gudangAsalId, $qtyKeluar, $referensiTipe, $referensiId, $catatan, $tanggal);
            $in = $this->post('in', $produkId, $gudangTujuanId, $qtyMasuk, $referensiTipe, $referensiId, $catatan, $tanggal);

            return ['keluar' => $out, 'masuk' => $in];
        });
    }

    /**
     * Penyesuaian ke qty absolut (opname / mutasi manual). Selisih dicatat sebagai in/out.
     */
    public function setAbsolut(int $produkId, int $gudangId, float $qtyBaru, ?string $catatan = null): ?KartuStok
    {
        return DB::transaction(function () use ($produkId, $gudangId, $qtyBaru, $catatan) {
            $saldo = $this->saldo($produkId, $gudangId);
            $delta = $qtyBaru - $saldo;
            if (abs($delta) < 0.00001) {
                return null;
            }
            $tipe = $delta > 0 ? 'in' : 'out';

            return $this->post($tipe, $produkId, $gudangId, abs($delta), 'mutasi_manual', null, $catatan);
        });
    }

    public function saldo(int $produkId, int $gudangId): float
    {
        $stok = Stok::where('produk_id', $produkId)->where('gudang_id', $gudangId)->first();

        return $stok ? (float) $stok->qty_saat_ini : 0.0;
    }

    private function post(string $tipe, int $produkId, int $gudangId, float $qty, string $referensiTipe, ?int $referensiId, ?string $catatan, ?Carbon $tanggal): KartuStok
    {
        if ($qty <= 0) {
            throw new RuntimeException('Qty mutasi stok harus lebih dari 0.');
        }

        return DB::transaction(function () use ($tipe, $produkId, $gudangId, $qty, $referensiTipe, $referensiId, $catatan, $tanggal) {
            $row = DB::table('stok')->lockForUpdate()
                ->where('produk_id', $produkId)->where('gudang_id', $gudangId)->first();
            $saldo = $row ? (float) $row->qty_saat_ini : 0.0;

            $delta = $tipe === 'in' ? $qty : -$qty;
            $saldoBaru = $saldo + $delta;

            if ($tipe === 'out' && $saldoBaru < 0) {
                $gudang = Gudang::find($gudangId);
                if (! $gudang || ! $gudang->allow_negative_stock) {
                    throw new RuntimeException("Stok tidak cukup untuk dikeluarkan (saldo {$saldo}, diminta {$qty}).");
                }
            }

            DB::table('stok')->updateOrInsert(
                ['produk_id' => $produkId, 'gudang_id' => $gudangId],
                ['qty_saat_ini' => $saldoBaru, 'updated_at' => now()],
            );

            return KartuStok::create([
                'tanggal' => $tanggal ?? now(),
                'produk_id' => $produkId,
                'gudang_id' => $gudangId,
                'tipe' => $tipe,
                'qty' => $qty,
                'saldo_setelah' => $saldoBaru,
                'referensi_tipe' => $referensiTipe,
                'referensi_id' => $referensiId,
                'catatan' => $catatan,
            ]);
        });
    }
}
