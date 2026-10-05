<?php

namespace Database\Seeders;

use App\Models\KlasifikasiAbc;
use Illuminate\Database\Seeder;

class KlasifikasiAbcSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'kode' => 'wajib_a',
                'nama' => 'Wajib A (Buffer +4 Hari)',
                'tambahan_buffer_hari' => 4,
                'deskripsi' => 'Produk import vital tanpa substitusi lokal',
                'warna_badge' => 'red',
                'is_active' => true,
            ],
            [
                'kode' => 'a',
                'nama' => 'A - Fast Moving (Buffer +4 Hari)',
                'tambahan_buffer_hari' => 4,
                'deskripsi' => 'Produk import fast moving prioritas tinggi',
                'warna_badge' => 'amber',
                'is_active' => true,
            ],
            [
                'kode' => 'b',
                'nama' => 'B - Medium Moving (Buffer +2 Hari)',
                'tambahan_buffer_hari' => 2,
                'deskripsi' => 'Produk import pergerakan sedang',
                'warna_badge' => 'blue',
                'is_active' => true,
            ],
            [
                'kode' => 'c',
                'nama' => 'C - Slow Moving (Buffer +0 Hari)',
                'tambahan_buffer_hari' => 0,
                'deskripsi' => 'Produk import pergerakan lambat',
                'warna_badge' => 'gray',
                'is_active' => true,
            ],
        ];

        foreach ($items as $item) {
            KlasifikasiAbc::updateOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }
    }
}
