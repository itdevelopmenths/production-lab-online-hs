<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot komposisi baru (Excel Analisa Import, sheet P50 "Pivot 4"):
 * SKU-SKU dalam satu grup (mis. botol P50 per warna) berbagi total out 4 bulan grup.
 * ADU Pivot = % komposisi baru × total out grup ÷ jumlah hari,
 * ADU ETA = ADU Base + ADU Pivot × Lead Time / 30.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analisa_impor', function (Blueprint $table) {
            if (! Schema::hasColumn('analisa_impor', 'grup_pivot')) {
                $table->string('grup_pivot', 50)->nullable()->after('adu_eta');
            }
            if (! Schema::hasColumn('analisa_impor', 'persentase_pivot')) {
                $table->decimal('persentase_pivot', 8, 4)->nullable()->after('grup_pivot');
            }
            if (! Schema::hasColumn('analisa_impor', 'adu_pivot')) {
                $table->decimal('adu_pivot', 15, 4)->default(0)->after('persentase_pivot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('analisa_impor', function (Blueprint $table) {
            foreach (['adu_pivot', 'persentase_pivot', 'grup_pivot'] as $col) {
                if (Schema::hasColumn('analisa_impor', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
