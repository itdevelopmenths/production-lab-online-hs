<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uom', function (Blueprint $table) {
            $table->string('satuan_dasar', 15)->nullable()->after('kategori');
            $table->decimal('faktor_konversi', 12, 4)->nullable()->default(1.0000)->after('satuan_dasar');
        });

        // Set default values for known base units and packages
        $defaults = [
            'ml' => ['satuan_dasar' => 'ml', 'faktor_konversi' => 1.0000],
            'l' => ['satuan_dasar' => 'ml', 'faktor_konversi' => 1000.0000],
            'galon' => ['satuan_dasar' => 'ml', 'faktor_konversi' => 19000.0000],
            'drum' => ['satuan_dasar' => 'ml', 'faktor_konversi' => 200000.0000],
            'gr' => ['satuan_dasar' => 'gr', 'faktor_konversi' => 1.0000],
            'kg' => ['satuan_dasar' => 'gr', 'faktor_konversi' => 1000.0000],
            'pcs' => ['satuan_dasar' => 'pcs', 'faktor_konversi' => 1.0000],
            'botol' => ['satuan_dasar' => 'pcs', 'faktor_konversi' => 1.0000],
            'box' => ['satuan_dasar' => 'pcs', 'faktor_konversi' => 1.0000],
        ];

        foreach ($defaults as $kode => $vals) {
            DB::table('uom')->where('kode', $kode)->update($vals);
        }
    }

    public function down(): void
    {
        Schema::table('uom', function (Blueprint $table) {
            $table->dropColumn(['satuan_dasar', 'faktor_konversi']);
        });
    }
};
