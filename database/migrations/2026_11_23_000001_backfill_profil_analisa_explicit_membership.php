<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keanggotaan Analisa Lokal/Impor kini eksplisit lewat produk.profil_analisa (Add SKU).
 * Sebelumnya produk ber-profil NULL otomatis ikut di-generate. Agar daftar analisa yang sudah
 * tampil tidak hilang, SKU ber-profil NULL yang sudah punya baris analisa diberi profil sesuai barisnya.
 * Impor diprioritaskan (sama seperti fallback legacy generateImpor), sisanya lokal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('produk', 'profil_analisa')) {
            return;
        }

        $imporTables = array_values(array_filter(['analisa_impor', 'analisa_impor_meta', 'lead_time_impor'], fn ($t) => Schema::hasTable($t)));
        $lokalTables = array_values(array_filter(['analisa_lokal', 'rekomendasi_order_lokal', 'analisa_lokal_input'], fn ($t) => Schema::hasTable($t)));

        $this->backfill($imporTables, 'impor');
        $this->backfill($lokalTables, 'lokal');
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function backfill(array $tables, string $profil): void
    {
        if (empty($tables)) {
            return;
        }

        DB::table('produk')
            ->whereNull('profil_analisa')
            ->whereIn('tipe', ['bahan', 'kemas'])
            ->where(function ($q) use ($tables) {
                foreach ($tables as $table) {
                    $q->orWhereExists(function ($sub) use ($table) {
                        $sub->select(DB::raw(1))
                            ->from($table)
                            ->whereColumn("{$table}.produk_id", 'produk.id');
                    });
                }
            })
            ->update(['profil_analisa' => $profil]);
    }

    public function down(): void
    {
        // Tidak dibalik: profil eksplisit tetap valid untuk alur lama.
    }
};
