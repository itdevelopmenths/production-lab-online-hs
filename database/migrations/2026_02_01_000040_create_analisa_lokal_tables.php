<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisa_lokal_input', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->unique()->constrained('produk')->cascadeOnDelete();
            $table->decimal('terjual_rata_rata_4bulan', 15, 2)->default(0);
            $table->integer('review_period')->default(0);
            $table->decimal('stok_saat_ini', 15, 2)->default(0);
            $table->decimal('akan_datang', 15, 2)->default(0);
            $table->decimal('harga_per_satuan', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('lead_time_stage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            // average / max
            $table->string('skenario', 10);
            // perencanaan/approval/supplier_confirm/payment/po/pengemasan/pengiriman/unloading/input
            $table->string('tahap', 20);
            $table->integer('jumlah_hari')->default(0);
            $table->integer('tambahan_buffer_hari')->nullable();
            $table->timestamps();

            $table->index(['produk_id', 'skenario'], 'idx_lts_produk_skenario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_time_stage');
        Schema::dropIfExists('analisa_lokal_input');
    }
};
