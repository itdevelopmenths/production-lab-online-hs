<?php

namespace Database\Seeders;

use App\Models\Uom;
use Illuminate\Database\Seeder;

class UomSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            [
                'kode' => 'pcs',
                'nama' => 'Pieces / Unit',
                'kategori' => 'Satuan Hitung',
                'deskripsi' => 'Satuan hitung per buah atau komponen fisik individu',
                'is_active' => true,
            ],
            [
                'kode' => 'ml',
                'nama' => 'Mililiter',
                'kategori' => 'Volume',
                'deskripsi' => 'Satuan volume cairan kimia formula laboratorium',
                'is_active' => true,
            ],
            [
                'kode' => 'gr',
                'nama' => 'Gram',
                'kategori' => 'Massa',
                'deskripsi' => 'Satuan berat / massa bahan bubuk atau serbuk',
                'is_active' => true,
            ],
            [
                'kode' => 'kg',
                'nama' => 'Kilogram',
                'kategori' => 'Massa',
                'deskripsi' => 'Setara 1.000 gram untuk bahan baku bulk',
                'is_active' => true,
            ],
            [
                'kode' => 'l',
                'nama' => 'Liter',
                'kategori' => 'Volume',
                'deskripsi' => 'Setara 1.000 mililiter untuk cairan bulk',
                'is_active' => true,
            ],
            [
                'kode' => 'botol',
                'nama' => 'Botol',
                'kategori' => 'Kemasan',
                'deskripsi' => 'Kemasan wadah botol primer parfum / kosmetik',
                'is_active' => true,
            ],
            [
                'kode' => 'box',
                'nama' => 'Box / Karton',
                'kategori' => 'Kemasan',
                'deskripsi' => 'Kemasan karton sekunder pengiriman atau penyimpanan',
                'is_active' => true,
            ],
            [
                'kode' => 'drum',
                'nama' => 'Drum',
                'kategori' => 'Kemasan',
                'deskripsi' => 'Wadah drum besar penyimpanan bahan baku kimia',
                'is_active' => true,
            ],
        ];

        foreach ($units as $u) {
            Uom::firstOrCreate(['kode' => $u['kode']], $u);
        }
    }
}
