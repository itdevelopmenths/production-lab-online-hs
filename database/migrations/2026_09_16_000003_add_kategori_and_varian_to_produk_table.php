<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->foreignId('kategori_id')->nullable()->after('id')->constrained('kategori')->nullOnDelete();
            $table->foreignId('varian_id')->nullable()->after('kategori_id')->constrained('varian')->nullOnDelete();
            $table->string('nama_produk', 150)->nullable()->after('nama');
            $table->decimal('faktor_konversi', 15, 4)->default(1.0000)->after('satuan');

            $table->index(['kategori_id', 'varian_id'], 'idx_produk_kategori_varian');
        });
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropIndex('idx_produk_kategori_varian');
            $table->dropForeign(['kategori_id']);
            $table->dropForeign(['varian_id']);
            $table->dropColumn(['kategori_id', 'varian_id', 'nama_produk', 'faktor_konversi']);
        });
    }
};
