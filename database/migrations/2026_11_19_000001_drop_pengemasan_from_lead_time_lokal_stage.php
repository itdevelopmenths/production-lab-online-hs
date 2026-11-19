<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap Pengemasan sudah dilebur ke Pengiriman & Logistik (alur 8 tahap).
     * Nilai lama dipindahkan ke kolom pengiriman agar total lead time tidak berubah.
     */
    public function up(): void
    {
        if (! Schema::hasTable('lead_time_lokal_stage') || ! Schema::hasColumn('lead_time_lokal_stage', 'pengemasan')) {
            return;
        }

        DB::table('lead_time_lokal_stage')->update([
            'pengiriman' => DB::raw('pengiriman + pengemasan'),
        ]);

        Schema::table('lead_time_lokal_stage', function (Blueprint $table) {
            $table->dropColumn('pengemasan');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('lead_time_lokal_stage') && ! Schema::hasColumn('lead_time_lokal_stage', 'pengemasan')) {
            Schema::table('lead_time_lokal_stage', function (Blueprint $table) {
                $table->decimal('pengemasan', 10, 1)->default(0)->after('po');
            });
        }
    }
};
