<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\Kategori;
use App\Models\Produk;
use App\Models\Varian;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StampsBomImporter
{
    /**
     * Import resep BOM dari file CSV Stamps POS (Cibinong City Mall export).
     *
     * @param string $filePath Absolute path ke file CSV
     * @return array{products_created: int, materials_created: int, boms_imported: int, errors: array<string>}
     */
    public function importFromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File tidak ditemukan: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Gagal membuka file: {$filePath}");
        }

        return $this->importFromHandle($handle);
    }

    /**
     * Parse stream CSV Stamps POS.
     *
     * @param resource $handle
     * @return array{products_created: int, materials_created: int, boms_imported: int, errors: array<string>}
     */
    public function importFromHandle($handle): array
    {
        $header = fgetcsv($handle, 0, ';', '"', '\\');
        if (!$header) {
            return ['products_created' => 0, 'materials_created' => 0, 'boms_imported' => 0, 'errors' => ['File CSV kosong.']];
        }

        $productsCreated = 0;
        $materialsCreated = 0;
        $bomsImported = 0;
        $duplicatesSkipped = 0;
        $errors = [];

        DB::transaction(function () use ($handle, &$productsCreated, &$materialsCreated, &$bomsImported, &$duplicatesSkipped, &$errors) {
            $currentItem = '';
            $currentVariant = '';
            $line = 1;

            // Cache produk & bahan untuk performa tinggi
            $existingProducts = Produk::with('varian')->get()->keyBy(fn ($p) => Str::lower($p->nama));

            // Set untuk mendeteksi duplikasi formula resep di dalam berkas CSV
            $seenInFile = [];

            while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
                $line++;
                if (count($row) < 4) {
                    continue;
                }

                $rawItem = trim($row[0] ?? '');
                $rawVariant = trim($row[1] ?? '');
                $rawIngredient = trim($row[2] ?? '');
                $rawQty = trim($row[3] ?? '0');
                $rawUnit = trim($row[4] ?? '');

                // Forward-fill Item Name & Variant Name
                if ($rawItem !== '') {
                    $currentItem = $rawItem;
                }
                if ($rawVariant !== '') {
                    $currentVariant = $rawVariant;
                }

                if ($currentItem === '' || $rawIngredient === '') {
                    continue;
                }

                $qty = (float) str_replace(',', '.', $rawQty);
                if ($qty <= 0) {
                    continue;
                }

                // 1. Tentukan Nama Produk Jadi
                $productName = ($currentItem === $currentVariant || $currentVariant === '')
                    ? $currentItem
                    : "{$currentItem} / {$currentVariant}";

                // Validasi data duplicate di dalam berkas CSV
                $fileRecipeKey = Str::lower($productName) . '||' . Str::lower($rawIngredient);
                if (isset($seenInFile[$fileRecipeKey])) {
                    $duplicatesSkipped++;
                    $prevLine = $seenInFile[$fileRecipeKey];
                    $errors[] = "Baris {$line}: Data duplikat di dalam berkas untuk '{$productName}' dan bahan '{$rawIngredient}' (sudah didefinisikan pada baris {$prevLine}). Baris duplikat dilewati.";
                    continue;
                }
                $seenInFile[$fileRecipeKey] = $line;

                $productKey = Str::lower($productName);

                // Dapatkan / Buat Produk Jadi
                if (!isset($existingProducts[$productKey])) {
                    $skuPrefix = 'PRD-' . strtoupper(Str::slug(substr($currentItem, 0, 8)));
                    $sku = $this->generateUniqueSku($skuPrefix);

                    $kategori = Kategori::firstOrCreate(['nama' => 'General']);
                    $varianId = null;
                    if ($currentVariant !== '' && $currentVariant !== $currentItem) {
                        $varian = Varian::firstOrCreate([
                            'kategori_id' => $kategori->id,
                            'nama' => $currentVariant,
                        ]);
                        $varianId = $varian->id;
                    }

                    $product = Produk::create([
                        'sku' => $sku,
                        'kategori_id' => $kategori->id,
                        'varian_id' => $varianId,
                        'nama_produk' => $currentItem,
                        'nama' => $productName,
                        'tipe' => 'produk_jadi',
                        'satuan' => 'pcs',
                        'satuan_order_moq' => 12,
                        'is_active' => true,
                    ]);

                    $existingProducts[$productKey] = $product;
                    $productsCreated++;
                } else {
                    $product = $existingProducts[$productKey];
                    if (!$product->kategori_id) {
                        $kategori = Kategori::firstOrCreate(['nama' => 'General']);
                        $varianId = null;
                        if ($currentVariant !== '' && $currentVariant !== $currentItem) {
                            $varian = Varian::firstOrCreate([
                                'kategori_id' => $kategori->id,
                                'nama' => $currentVariant,
                            ]);
                            $varianId = $varian->id;
                        }
                        DB::table('produk')->where('id', $product->id)->update([
                            'kategori_id' => $kategori->id,
                            'varian_id' => $varianId,
                            'nama_produk' => $currentItem,
                        ]);
                    }
                }

                // 2. Tentukan Bahan / Kemas
                $ingredientKey = Str::lower($rawIngredient);
                if (!isset($existingProducts[$ingredientKey])) {
                    $isLiquid = str_contains(strtolower($rawUnit), 'ml') || str_contains(strtolower($rawUnit), 'milli');
                    $tipe = $isLiquid ? 'bahan' : 'kemas';
                    $satuan = $isLiquid ? 'ml' : 'pcs';
                    $kategori = Kategori::firstOrCreate(['nama' => $isLiquid ? 'Bahan Baku' : 'Kemasan']);

                    $skuPrefix = $isLiquid
                        ? (str_starts_with(strtolower($rawIngredient), 'oil') ? 'OIL-' : 'BAH-')
                        : 'KEM-';
                    $sku = $this->generateUniqueSku($skuPrefix . strtoupper(Str::slug(substr($rawIngredient, 0, 8))));

                    $material = Produk::create([
                        'sku' => $sku,
                        'kategori_id' => $kategori->id,
                        'varian_id' => null,
                        'nama_produk' => $rawIngredient,
                        'nama' => $rawIngredient,
                        'tipe' => $tipe,
                        'satuan' => $satuan,
                        'satuan_order_moq' => $isLiquid ? 100 : 1000,
                        'profil_analisa' => $isLiquid ? 'lokal' : 'impor',
                        'is_active' => true,
                    ]);

                    $existingProducts[$ingredientKey] = $material;
                    $materialsCreated++;
                } else {
                    $material = $existingProducts[$ingredientKey];
                    if (!$material->kategori_id) {
                        $isLiquid = str_contains(strtolower($rawUnit), 'ml') || str_contains(strtolower($rawUnit), 'milli');
                        $kategori = Kategori::firstOrCreate(['nama' => $isLiquid ? 'Bahan Baku' : 'Kemasan']);
                        DB::table('produk')->where('id', $material->id)->update([
                            'kategori_id' => $kategori->id,
                            'nama_produk' => $rawIngredient,
                        ]);
                    }
                }

                // 3. Simpan Resep ke Tabel BOM
                Bom::updateOrCreate(
                    [
                        'produk_jadi_id' => $product->id,
                        'bahan_id' => $material->id,
                    ],
                    [
                        'qty_per_unit' => $qty,
                    ]
                );

                $bomsImported++;
            }
        });

        fclose($handle);

        return [
            'products_created' => $productsCreated,
            'materials_created' => $materialsCreated,
            'boms_imported' => $bomsImported,
            'duplicates_skipped' => $duplicatesSkipped,
            'errors' => $errors,
        ];
    }

    private function generateUniqueSku(string $prefix): string
    {
        $cleanPrefix = Str::limit($prefix, 20, '');
        $sku = $cleanPrefix;
        $counter = 1;

        while (Produk::where('sku', $sku)->exists()) {
            $suffix = '-' . $counter;
            $sku = substr($cleanPrefix, 0, 30 - strlen($suffix)) . $suffix;
            $counter++;
        }

        return $sku;
    }
}
