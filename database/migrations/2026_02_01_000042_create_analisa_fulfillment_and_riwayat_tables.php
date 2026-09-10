<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisa_fulfillment_input', function (Blueprint $table) {
            $table->id();
            // one row per produk jadi + gudang fulfillment
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->decimal('terjual_rata_rata_4bulan', 15, 2)->default(0);
            $table->integer('lead_time_distribusi')->default(0);
            $table->integer('buffer_distribusi')->default(0);
            $table->integer('review_period')->default(0);
            $table->decimal('stok_saat_ini', 15, 2)->default(0);
            $table->decimal('akan_datang', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['produk_id', 'gudang_id'], 'uq_aff_produk_gudang');
        });

        Schema::create('riwayat_analisa', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            // bahan_lokal / bahan_impor / produk_jadi_fulfillment
            $table->string('tipe', 30);
            $table->string('item_label', 100);
            $table->decimal('batas_minimum', 15, 2)->default(0);
            $table->decimal('target_stock', 15, 2)->default(0);
            // order / tidak / po
            $table->string('status', 10);
            $table->decimal('qty_order', 15, 2)->nullable();
            $table->foreignId('dicatat_oleh')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['tipe', 'tanggal'], 'idx_riwayat_tipe_tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_analisa');
        Schema::dropIfExists('analisa_fulfillment_input');
    }
};
