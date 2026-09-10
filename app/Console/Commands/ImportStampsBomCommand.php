<?php

namespace App\Console\Commands;

use App\Services\StampsBomImporter;
use Illuminate\Console\Command;

class ImportStampsBomCommand extends Command
{
    protected $signature = 'bom:import-stamps {file? : Path ke file CSV Stamps (default: list product varian dan bom.csv di root)}';

    protected $description = 'Import resep BOM dari file export Stamps POS (Cibinong City Mall)';

    public function handle(StampsBomImporter $importer): int
    {
        $file = $this->argument('file') ?? base_path('list product varian dan bom.csv');

        if (!file_exists($file)) {
            $this->error("File CSV tidak ditemukan pada lokasi: {$file}");
            return Command::FAILURE;
        }

        $this->info("Memulai impor resep BOM dari: {$file} ...");

        try {
            $start = microtime(true);
            $result = $importer->importFromFile($file);
            $duration = round(microtime(true) - $start, 2);

            $this->newLine();
            $this->info("✓ Impor BOM Stamps POS Berhasil ({$duration}s):");
            $this->table(
                ['Metrik', 'Jumlah'],
                [
                    ['Produk Jadi Baru Dibuat', $result['products_created']],
                    ['Bahan / Kemas Baru Dibuat', $result['materials_created']],
                    ['Baris Resep BOM Terimpor', $result['boms_imported']],
                ]
            );

            if (!empty($result['errors'])) {
                $this->warn("Terdapat " . count($result['errors']) . " catatan/peringatan:");
                foreach (array_slice($result['errors'], 0, 5) as $err) {
                    $this->line(" - {$err}");
                }
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Gagal melakukan impor: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
