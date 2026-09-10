<?php

namespace Database\Seeders;

use App\Models\AnalisaFulfillmentInput;
use App\Models\AnalisaImporMeta;
use App\Models\AnalisaLokalInput;
use App\Models\Bom;
use App\Models\Gudang;
use App\Models\LeadTimeStage;
use App\Models\Produk;
use App\Models\Supplier;
use App\Services\StokService;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // ===== Gudang (dengan hierarki fulfillment) =====
        $gBahan = Gudang::firstOrCreate(['kode' => 'GD-PUSAT'], ['nama' => 'Gudang Bahan Baku Pusat', 'tipe' => 'bahan_baku', 'status' => 'aktif']);
        $gOps = Gudang::firstOrCreate(['kode' => 'GD-OPS'], ['nama' => 'Gudang Operasional', 'tipe' => 'operasional', 'status' => 'aktif']);
        $ffPusat = Gudang::firstOrCreate(['kode' => 'FF-PUSAT'], ['nama' => 'Fulfillment Pusat', 'tipe' => 'fulfillment_pusat', 'status' => 'aktif']);
        $ffSby = Gudang::firstOrCreate(['kode' => 'FF-SBY'], ['nama' => 'Fulfillment SBY', 'tipe' => 'fulfillment_cabang', 'status' => 'aktif', 'parent_gudang_id' => $ffPusat->id]);
        $ffSolo = Gudang::firstOrCreate(['kode' => 'FF-SOLO'], ['nama' => 'Fulfillment Solo', 'tipe' => 'fulfillment_cabang', 'status' => 'aktif', 'parent_gudang_id' => $ffPusat->id]);

        // ===== Supplier =====
        Supplier::firstOrCreate(['nama' => 'PT Alkohol Nusantara'], ['kategori' => 'lokal', 'kontak' => '081234500001', 'alamat' => 'Sidoarjo', 'termin_default' => 'termin']);
        Supplier::firstOrCreate(['nama' => 'Glass Bottle Import Co.'], ['kategori' => 'impor', 'kontak' => '081234500002', 'termin_default' => 'tempo']);

        // ===== Produk (bahan, kemas, produk jadi) =====
        $alk = Produk::firstOrCreate(['sku' => 'ALK-01'], ['nama' => 'Alkohol 96%', 'tipe' => 'bahan', 'satuan' => 'ml', 'satuan_order_moq' => 5000, 'profil_analisa' => 'lokal']);
        $oil = Produk::firstOrCreate(['sku' => 'OIL-GOH'], ['nama' => 'Oil Concentrate GOH', 'tipe' => 'bahan', 'satuan' => 'ml', 'satuan_order_moq' => 100, 'profil_analisa' => 'lokal']);
        $btl = Produk::firstOrCreate(['sku' => 'BTL-P50'], ['nama' => 'Botol 50ml', 'tipe' => 'kemas', 'satuan' => 'pcs', 'satuan_order_moq' => 1000, 'profil_analisa' => 'impor']);
        $cap = Produk::firstOrCreate(['sku' => 'CAP-01'], ['nama' => 'Tutup Botol', 'tipe' => 'kemas', 'satuan' => 'pcs', 'satuan_order_moq' => 1000, 'profil_analisa' => 'impor']);
        $goh = Produk::firstOrCreate(['sku' => 'GOH-P50'], ['nama' => 'Parfum GOH 50ml', 'tipe' => 'produk_jadi', 'satuan' => 'pcs', 'satuan_order_moq' => 12]);

        // ===== BOM Parfum GOH 50ml =====
        foreach ([[$alk->id, 40], [$oil->id, 8], [$btl->id, 1], [$cap->id, 1]] as [$bahanId, $qty]) {
            Bom::firstOrCreate(['produk_jadi_id' => $goh->id, 'bahan_id' => $bahanId], ['qty_per_unit' => $qty]);
        }

        // ===== Stok awal bahan di Gudang Bahan Baku =====
        $stok = app(StokService::class);
        foreach ([[$alk->id, 500000], [$oil->id, 20000], [$btl->id, 8000], [$cap->id, 8000]] as [$pid, $qty]) {
            if ($stok->saldo($pid, $gBahan->id) <= 0) {
                $stok->masuk($pid, $gBahan->id, $qty, 'mutasi_manual', null, 'Saldo awal');
            }
        }

        // ===== Analisa Lokal (ALK-01) + Lead Time Stage =====
        AnalisaLokalInput::firstOrCreate(['produk_id' => $alk->id], [
            'terjual_rata_rata_4bulan' => 39303.25, 'review_period' => 15,
            'stok_saat_ini' => 8000, 'akan_datang' => 0, 'harga_per_satuan' => 526,
        ]);
        $stages = ['perencanaan' => [1, 2], 'approval' => [1, 2], 'supplier_confirm' => [2, 3], 'payment' => [1, 2], 'po' => [1, 1], 'pengemasan' => [2, 4], 'pengiriman' => [3, 6], 'unloading' => [1, 1], 'input' => [1, 1]];
        foreach ($stages as $tahap => [$avg, $max]) {
            LeadTimeStage::firstOrCreate(['produk_id' => $alk->id, 'skenario' => 'average', 'tahap' => $tahap], ['jumlah_hari' => $avg]);
            LeadTimeStage::firstOrCreate(['produk_id' => $alk->id, 'skenario' => 'max', 'tahap' => $tahap], ['jumlah_hari' => $max, 'tambahan_buffer_hari' => $tahap === 'input' ? 2 : null]);
        }

        // ===== Analisa Impor (BTL-P50) + varian =====
        $meta = AnalisaImporMeta::firstOrCreate(['produk_id' => $btl->id], [
            'punya_varian' => true, 'out_total_4bulan' => 47312, 'lead_time_average' => 81.75,
            'lead_time_max' => 114, 'klasifikasi_abc' => 'a', 'review_period' => 30, 'harga_per_satuan' => 1500,
        ]);
        $meta->varian()->firstOrCreate(['nama_varian' => 'Bening'], ['persentase_distribusi' => 0.6, 'stok_saat_ini' => 1573, 'inbound_before_eta' => 10044]);
        $meta->varian()->firstOrCreate(['nama_varian' => 'Frosted'], ['persentase_distribusi' => 0.4, 'stok_saat_ini' => 900, 'inbound_before_eta' => 5000]);

        // ===== Analisa Fulfillment (GOH-P50 per gudang) =====
        foreach ([[$ffPusat->id, 60000, 500], [$ffSby->id, 90000, 4000], [$ffSolo->id, 45000, 2000]] as [$gid, $terjual, $stokNow]) {
            AnalisaFulfillmentInput::firstOrCreate(['produk_id' => $goh->id, 'gudang_id' => $gid], [
                'terjual_rata_rata_4bulan' => $terjual, 'lead_time_distribusi' => 2, 'buffer_distribusi' => 1,
                'review_period' => 15, 'stok_saat_ini' => $stokNow, 'akan_datang' => $gid === $ffPusat->id ? 20000 : 0,
            ]);
        }
    }
}
