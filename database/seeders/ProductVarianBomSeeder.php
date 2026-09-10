<?php

namespace Database\Seeders;

use App\Services\StampsBomImporter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ProductVarianBomSeeder extends Seeder
{
    /**
     * Jalankan seeder terpisah untuk data produk jadi, varian, dan resep BOM dari CSV Stamps POS.
     */
    public function run(StampsBomImporter $importer): void
    {
        $filePath = base_path('list product varian dan bom.csv');

        if (!File::exists($filePath)) {
            $message = "File CSV tidak ditemukan pada path: {$filePath}";
            if ($this->command) {
                $this->command->error($message);
            }
            throw new \RuntimeException($message);
        }

        if ($this->command) {
            $this->command->info("Memulai impor dari file: " . basename($filePath) . " ...");
        }

        $start = microtime(true);
        $result = $importer->importFromFile($filePath);
        $duration = round(microtime(true) - $start, 2);

        if ($this->command) {
            $this->command->newLine();
            $this->command->info("✓ Impor Berhasil Selesai dalam {$duration} detik!");
            $this->command->table(
                ['Komponen', 'Jumlah Ditambahkan/Diperbarui'],
                [
                    ['Produk Jadi (Varian)', $result['products_created']],
                    ['Bahan Baku & Kemasan', $result['materials_created']],
                    ['Item Resep (BOM)', $result['boms_imported']],
                ]
            );

            if (!empty($result['errors'])) {
                $this->command->warn("Peringatan (" . count($result['errors']) . "):");
                foreach (array_slice($result['errors'], 0, 5) as $err) {
                    $this->command->line(" - {$err}");
                }
            }
        }
    }
}
