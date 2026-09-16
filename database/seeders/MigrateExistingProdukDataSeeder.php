<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\Varian;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateExistingProdukDataSeeder extends Seeder
{
    /**
     * Backfill kategori_id, varian_id, nama_produk, dan faktor_konversi untuk produk yang sudah ada.
     */
    public function run(): void
    {
        $this->command?->info("Memulai migrasi data master kategori & varian produk...");

        $kategoriBahan = Kategori::firstOrCreate(['nama' => 'Bahan Baku']);
        $kategoriKemas = Kategori::firstOrCreate(['nama' => 'Kemasan']);
        $kategoriParfum = Kategori::firstOrCreate(['nama' => 'Eau de Parfum']);
        $kategoriGeneral = Kategori::firstOrCreate(['nama' => 'General']);

        $updatedCount = 0;
        $variantsCreated = 0;

        DB::transaction(function () use (
            $kategoriBahan,
            $kategoriKemas,
            $kategoriParfum,
            $kategoriGeneral,
            &$updatedCount,
            &$variantsCreated
        ) {
            $allProduk = Produk::withoutGlobalScopes()->get();

            foreach ($allProduk as $produk) {
                // If nama contains delimiter ' / ' (misal Stamps POS variant format)
                if (str_contains($produk->nama, ' / ')) {
                    [$rawBase, $rawVariant] = explode(' / ', $produk->nama, 2);
                    $baseName = trim($rawBase);
                    $variantName = trim($rawVariant);

                    // Tentukan kategori
                    if ($produk->tipe === 'bahan') {
                        $kategori = $kategoriBahan;
                    } elseif ($produk->tipe === 'kemas') {
                        $kategori = $kategoriKemas;
                    } else {
                        $kategori = (str_contains(strtolower($baseName), 'parfum') || str_contains(strtolower($baseName), 'edp'))
                            ? $kategoriParfum
                            : $kategoriGeneral;
                    }

                    $varian = Varian::firstOrCreate(
                        [
                            'kategori_id' => $kategori->id,
                            'nama' => $variantName,
                        ]
                    );

                    if ($varian->wasRecentlyCreated) {
                        $variantsCreated++;
                    }

                    DB::table('produk')->where('id', $produk->id)->update([
                        'kategori_id' => $kategori->id,
                        'varian_id' => $varian->id,
                        'nama_produk' => $baseName,
                        'faktor_konversi' => $produk->faktor_konversi ?: 1.0000,
                    ]);

                    $updatedCount++;
                } else {
                    // Produk tanpa delimiter ' / '
                    if ($produk->tipe === 'bahan') {
                        $kategori = $kategoriBahan;
                    } elseif ($produk->tipe === 'kemas') {
                        $kategori = $kategoriKemas;
                    } else {
                        $kategori = $kategoriGeneral;
                    }

                    DB::table('produk')->where('id', $produk->id)->update([
                        'kategori_id' => $kategori->id,
                        'varian_id' => null,
                        'nama_produk' => $produk->nama,
                        'faktor_konversi' => $produk->faktor_konversi ?: 1.0000,
                    ]);

                    $updatedCount++;
                }
            }
        });

        $this->command?->info("✓ Selesai! {$updatedCount} produk diperbarui, {$variantsCreated} varian baru dibuat.");
    }
}
