<?php

namespace Tests\Concerns;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\DemandAduService;

/**
 * Fixture demand untuk ADU otomatis (DemandAduService): permintaan yang sudah disetujui/selesai
 * dalam jendela 4 bulan terakhir.
 */
trait SeedsDemandRequests
{
    /**
     * Permintaan bahan baku Gudang Produksi -> Gudang Pusat sehingga ADU Pusat = $adu per hari.
     */
    protected function seedPusatDemand(Produk $produk, float $adu, string $status = 'selesai'): StockRequest
    {
        return $this->createDemandRequest('request_bahan', 'operasional', 'bahan_baku', $produk, $adu, $status);
    }

    /**
     * Permintaan produk jadi Gudang Fulfillment -> Gudang Produksi sehingga ADU Produksi = $adu per hari.
     */
    protected function seedProduksiDemand(Produk $produk, float $adu, string $status = 'selesai'): StockRequest
    {
        return $this->createDemandRequest('request_produk_jadi', 'fulfillment', 'operasional', $produk, $adu, $status);
    }

    private function demandGudang(string $tipe): Gudang
    {
        return Gudang::firstOrCreate(
            ['tipe' => $tipe],
            ['kode' => 'T-' . strtoupper(substr($tipe, 0, 4)), 'nama' => 'Gudang Uji ' . $tipe, 'is_pusat' => false, 'status' => 'aktif']
        );
    }

    private function createDemandRequest(string $jenis, string $tipePemohon, string $tipePengirim, Produk $produk, float $adu, string $status): StockRequest
    {
        $days = app(DemandAduService::class)->window()['days'];
        $pemohon = $this->demandGudang($tipePemohon);
        $pengirim = $this->demandGudang($tipePengirim);
        $user = User::query()->orderBy('id')->firstOrFail();

        $req = StockRequest::create([
            'no_request' => 'REQ-T-' . uniqid(),
            'jenis' => $jenis,
            'gudang_asal_id' => $pemohon->id,
            'gudang_tujuan_id' => $pengirim->id,
            'status' => $status,
            'disetujui_pada' => now(),
            'created_by' => $user->id,
        ]);
        $req->items()->create(['produk_id' => $produk->id, 'qty_diminta' => round($adu * $days, 2)]);

        return $req;
    }
}
