<?php

namespace Database\Seeders;

use App\Models\Divisi;
use App\Models\User;
use Illuminate\Database\Seeder;

class DivisiSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            [
                'kode' => 'purchasing',
                'nama' => 'Purchasing & Pengadaan',
                'deskripsi' => 'Pengadaan bahan baku, kemasan, pemesanan ke vendor, dan analisa kebutuhan stok',
                'color' => 'amber',
                'is_active' => true,
            ],
            [
                'kode' => 'produksi',
                'nama' => 'Produksi & Laboratorium',
                'deskripsi' => 'Peracikan formula resep (BOM), batch produksi, kontrol kualitas dan pengemasan',
                'color' => 'emerald',
                'is_active' => true,
            ],
            [
                'kode' => 'gudang',
                'nama' => 'Gudang & Logistik',
                'deskripsi' => 'Penyimpanan bahan baku pusat, mutasi stok, kartu stok, dan operasional logistik',
                'color' => 'blue',
                'is_active' => true,
            ],
            [
                'kode' => 'fulfillment',
                'nama' => 'Fulfillment & Distribusi',
                'deskripsi' => 'Penerimaan produk jadi manufaktur, packing pesanan, dan distribusi channel penjualan',
                'color' => 'purple',
                'is_active' => true,
            ],
            [
                'kode' => 'qc',
                'nama' => 'Quality Control (QC)',
                'deskripsi' => 'Pemeriksaan standar mutu bahan baku datang dan konfirmasi uji produk jadi',
                'color' => 'teal',
                'is_active' => true,
            ],
            [
                'kode' => 'finance',
                'nama' => 'Finance & Akuntansi',
                'deskripsi' => 'Verifikasi tagihan PO, pembayaran termin vendor, dan pencatatan HPP biaya',
                'color' => 'rose',
                'is_active' => true,
            ],
            [
                'kode' => 'manajemen',
                'nama' => 'Manajemen & Direksi',
                'deskripsi' => 'Supervisi operasional rantai pasok, monitoring KPI, dan persetujuan otorisasi level tinggi',
                'color' => 'indigo',
                'is_active' => true,
            ],
        ];

        foreach ($divisions as $divData) {
            Divisi::firstOrCreate(
                ['kode' => $divData['kode']],
                $divData
            );
        }

        // Backfill user yang sudah ada agar terhubung ke divisi_id
        $users = User::whereNull('divisi_id')->whereNotNull('divisi')->get();
        foreach ($users as $u) {
            $matched = Divisi::where('kode', $u->divisi)->first();
            if ($matched) {
                $u->update(['divisi_id' => $matched->id]);
            }
        }
    }
}
