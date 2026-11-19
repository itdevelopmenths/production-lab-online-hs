<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Waktu batch diselesaikan: dasar lead time produksi (rencana dibuat -> selesai)
        // untuk analisa stok barang jadi di Gudang Produksi.
        Schema::table('batch_produksi', function (Blueprint $table) {
            $table->timestamp('waktu_selesai')->nullable()->after('tanggal');
        });
    }

    public function down(): void
    {
        Schema::table('batch_produksi', function (Blueprint $table) {
            $table->dropColumn('waktu_selesai');
        });
    }
};
