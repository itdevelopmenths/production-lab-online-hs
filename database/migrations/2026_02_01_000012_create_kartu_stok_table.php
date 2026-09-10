<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kartu_stok', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            // in / out
            $table->string('tipe', 5);
            $table->decimal('qty', 15, 2);
            $table->decimal('saldo_setelah', 15, 2);
            // purchase_order / request_transfer / batch_produksi / mutasi_manual
            $table->string('referensi_tipe', 30);
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->string('catatan', 255)->nullable();
            $table->timestamps();

            $table->index(['produk_id', 'gudang_id'], 'idx_kartu_produk_gudang');
            $table->index(['referensi_tipe', 'referensi_id'], 'idx_kartu_referensi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kartu_stok');
    }
};
