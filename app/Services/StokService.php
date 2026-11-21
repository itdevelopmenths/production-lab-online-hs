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
    public function masuk(int $produkId, int $gudangId, float $qty, string $referensiTipe, string|int|null $referensiId = null, ?string $catatan = null, ?Carbon $tanggal = null): KartuStok
    {
        return $this->post('in', $produkId, $gudangId, $qty, $referensiTipe, $referensiId, $catatan, $tanggal);
    }

    /**
     * Catat mutasi keluar (out). Mengurangi saldo gudang + append kartu stok.
     */
    public function keluar(int $produkId, int $gudangId, float $qty, string $referensiTipe, string|int|null $referensiId = null, ?string $catatan = null, ?Carbon $tanggal = null): KartuStok
    {
        return $this->post('out', $produkId, $gudangId, $qty, $referensiTipe, $referensiId, $catatan, $tanggal);
    }

    /**
     * Pindah stok antar gudang (keluar dari asal, masuk ke tujuan) dalam satu transaksi.
     *
     * @return array{keluar: KartuStok, masuk: KartuStok}
     */
    public function pindah(int $produkId, int $gudangAsalId, int $gudangTujuanId, float $qtyKeluar, float $qtyMasuk, string $referensiTipe, string|int|null $referensiId = null, ?string $catatan = null, ?Carbon $tanggal = null): array
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

    private function post(string $tipe, int $produkId, int $gudangId, float $qty, string $referensiTipe, string|int|null $referensiId = null, ?string $catatan = null, ?Carbon $tanggal = null): KartuStok
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
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Average HPP global per produk dari seluruh PO yang sudah diterima gudang
     * (status selesai atau sudah ada barang_datang), satu nilai untuk semua gudang:
     *
     *   HPP Average = Σ (hpp_per_satuan × qty) ÷ Σ qty
     *
     * Jika hpp_per_satuan item kosong, harga_total item dipakai sebagai total bersihnya.
     *
     * @param  array<int>  $produkIds  Jika kosong, hitung untuk seluruh produk yang punya PO
     * @return array<int, float> [produk_id => hpp_average]
     */
    public function averagePoHppMap(array $produkIds = []): array
    {
        $query = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.po_id')
            ->where(function ($q) {
                $q->where('po.status', 'selesai')
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('barang_datang as bd')
                            ->whereColumn('bd.po_id', 'po.id');
                    });
            })
            ->where('poi.qty', '>', 0)
            ->where(function ($q) {
                $q->where('poi.hpp_per_satuan', '>', 0)
                    ->orWhere('poi.harga_total', '>', 0);
            })
            ->groupBy('poi.produk_id')
            ->select(
                'poi.produk_id',
                DB::raw('SUM(CASE WHEN poi.hpp_per_satuan > 0 THEN poi.hpp_per_satuan * poi.qty ELSE poi.harga_total END) as total_nilai'),
                DB::raw('SUM(poi.qty) as total_qty')
            );

        if (! empty($produkIds)) {
            $query->whereIn('poi.produk_id', $produkIds);
        }

        $result = [];
        foreach ($query->get() as $row) {
            $qty = (float) $row->total_qty;
            if ($qty > 0) {
                $avg = round((float) $row->total_nilai / $qty, 4);
                if ($avg > 0) {
                    $result[(int) $row->produk_id] = $avg;
                }
            }
        }

        return $result;
    }

    /**
     * Simpan average HPP terbaru ke master produk (produk.harga_hpp),
     * dipanggil setelah penerimaan PO agar master selalu sama dengan HPP sistem.
     *
     * @param  array<int>  $produkIds
     */
    public function syncHargaHppAverage(array $produkIds): void
    {
        $produkIds = array_values(array_unique(array_filter(array_map('intval', $produkIds))));
        if (empty($produkIds)) {
            return;
        }

        foreach ($this->averagePoHppMap($produkIds) as $produkId => $avg) {
            DB::table('produk')->where('id', $produkId)->update(['harga_hpp' => $avg]);
        }
    }

    /**
     * Hitung nilai HPP untuk daftar produk atau seluruh produk,
     * dengan hierarki resolusi identik 100% dengan kolom HPP di Stok & Mutasi (resources/views/stok/index.blade.php):
     * 1. Prioritas 1: Average HPP global dari seluruh PO yang sudah diterima gudang (lihat averagePoHppMap)
     * 2. Prioritas 2 (Fallback 1): Master Produk (harga_hpp)
     * 3. Prioritas 3 (Fallback 2): Referensi Analisa Lokal (analisa_lokal_input.harga_per_satuan)
     * 4. Prioritas 4 (Fallback 3): Referensi Analisa Impor (analisa_impor_meta.harga_per_satuan)
     * 5. Khusus produk jadi: Kalkulasi akumulasi HPP bahan dari resep BOM (jika ada)
     *
     * @param array<int> $produkIds Jika kosong, hitung untuk seluruh produk aktif
     * @return array<int, float> [produk_id => hpp_value]
     */
    public function resolveHppMap(array $produkIds = []): array
    {
        // 0. Resep BOM (untuk produk jadi). Bahan BOM ikut di-lookup agar HPP-nya ter-resolve.
        $bomQuery = DB::table('bom')->select('produk_jadi_id', 'bahan_id', 'qty_per_unit');
        if (! empty($produkIds)) {
            $bomQuery->whereIn('produk_jadi_id', $produkIds);
        }
        $bomLines = $bomQuery->get();

        $lookupIds = empty($produkIds)
            ? []
            : array_values(array_unique(array_merge($produkIds, $bomLines->pluck('bahan_id')->all())));

        // 1. Average HPP global dari seluruh PO yang sudah diterima gudang (sama untuk semua gudang)
        $avgPoHpp = $this->averagePoHppMap($lookupIds);

        // 2. Referensi harga dari analisa stok sebagai fallback
        $analisaLokalQuery = DB::table('analisa_lokal_input')->where('harga_per_satuan', '>', 0);
        if (! empty($lookupIds)) {
            $analisaLokalQuery->whereIn('produk_id', $lookupIds);
        }
        $analisaLokalPrices = $analisaLokalQuery->pluck('harga_per_satuan', 'produk_id');

        $analisaImporQuery = DB::table('analisa_impor_meta')->where('harga_per_satuan', '>', 0);
        if (! empty($lookupIds)) {
            $analisaImporQuery->whereIn('produk_id', $lookupIds);
        }
        $analisaImporPrices = $analisaImporQuery->pluck('harga_per_satuan', 'produk_id');

        // 3. Master Produk (harga_hpp & tipe)
        $masterQuery = DB::table('produk')->select('id', 'tipe', 'harga_hpp');
        if (! empty($lookupIds)) {
            $masterQuery->whereIn('id', $lookupIds);
        }
        $masterProducts = $masterQuery->get()->keyBy('id');

        $resolveRawMaterialHpp = function ($pId, $fallbackHarga = 0) use ($avgPoHpp, $masterProducts, $analisaLokalPrices, $analisaImporPrices) {
            if (isset($avgPoHpp[$pId]) && (float) $avgPoHpp[$pId] > 0) {
                return (float) $avgPoHpp[$pId];
            }
            $masterHpp = (float) ($masterProducts[$pId]->harga_hpp ?? $fallbackHarga);
            if ($masterHpp > 0) {
                return $masterHpp;
            }
            if (isset($analisaLokalPrices[$pId]) && (float) $analisaLokalPrices[$pId] > 0) {
                return (float) $analisaLokalPrices[$pId];
            }
            if (isset($analisaImporPrices[$pId]) && (float) $analisaImporPrices[$pId] > 0) {
                return (float) $analisaImporPrices[$pId];
            }

            return 0.0;
        };

        // 4. Hitung BOM jika ada produk jadi
        $bomHppMap = [];
        foreach ($bomLines as $line) {
            $ingHpp = $resolveRawMaterialHpp($line->bahan_id);
            $bomHppMap[$line->produk_jadi_id] = ($bomHppMap[$line->produk_jadi_id] ?? 0.0) + ((float) $line->qty_per_unit * $ingHpp);
        }

        $result = [];
        $targetIds = ! empty($produkIds) ? $produkIds : $masterProducts->keys()->all();

        foreach ($targetIds as $id) {
            $prod = $masterProducts[$id] ?? null;
            if (! $prod) {
                $result[$id] = 0.0;
                continue;
            }

            if (in_array($prod->tipe, ['bahan', 'kemas'], true)) {
                $result[$id] = $resolveRawMaterialHpp($id, (float) $prod->harga_hpp);
            } else {
                $bomVal = (float) ($bomHppMap[$id] ?? 0);
                $result[$id] = $bomVal > 0 ? $bomVal : $resolveRawMaterialHpp($id, (float) $prod->harga_hpp);
            }
        }

        return $result;
    }
}

