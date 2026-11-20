<?php

namespace Tests\Concerns;

use App\Models\AnalisaFulfillmentInput;
use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Produk;
use App\Services\DemandAduService;
use Carbon\CarbonInterface;

/**
 * Fixture demand untuk ADU otomatis (DemandAduService):
 *  - Gudang Pusat: pemakaian bahan di batch produksi (kartu stok keluar ber-referensi batch_produksi).
 *  - Produk jadi / Gudang Produksi: ADU penjualan dari Analisa Fulfillment.
 */
trait SeedsDemandRequests
{
    /**
     * Pemakaian bahan di batch produksi selama jendela 4 bulan sehingga ADU Pusat = $adu per hari.
     */
    protected function seedPusatDemand(Produk $produk, float $adu, ?CarbonInterface $tanggal = null): KartuStok
    {
        $days = app(DemandAduService::class)->window()['days'];

        return KartuStok::create([
            'tanggal' => ($tanggal ?? now())->toDateString(),
            'produk_id' => $produk->id,
            'gudang_id' => $this->demandGudang('operasional')->id,
            'tipe' => 'out',
            'qty' => round($adu * $days, 2),
            'saldo_setelah' => 0,
            'referensi_tipe' => 'batch_produksi',
            'referensi_id' => null,
            'catatan' => 'Fixture pemakaian produksi',
        ]);
    }

    /**
     * ADU penjualan produk jadi di Gudang Fulfillment = $adu per hari (menggantikan input fulfillment lain produk tsb).
     */
    protected function seedProduksiDemand(Produk $produk, float $adu): AnalisaFulfillmentInput
    {
        AnalisaFulfillmentInput::where('produk_id', $produk->id)->delete();

        return AnalisaFulfillmentInput::create([
            'produk_id' => $produk->id,
            'gudang_id' => $this->demandGudang('fulfillment')->id,
            'terjual_rata_rata_4bulan' => $adu * 30,
            'lead_time_distribusi' => 2,
            'buffer_distribusi' => 1,
            'review_period' => 15,
        ]);
    }

    private function demandGudang(string $tipe): Gudang
    {
        return Gudang::firstOrCreate(
            ['tipe' => $tipe],
            ['kode' => 'T-' . strtoupper(substr($tipe, 0, 4)), 'nama' => 'Gudang Uji ' . $tipe, 'is_pusat' => false, 'status' => 'aktif']
        );
    }
}
