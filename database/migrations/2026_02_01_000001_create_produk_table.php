<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produk', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 30)->unique();
            $table->string('nama', 150);
            // bahan / kemas / produk_jadi
            $table->string('tipe', 20);
            $table->string('satuan', 10);
            $table->decimal('satuan_order_moq', 15, 2)->default(1);
            // profil analisa: lokal / impor / null (produk jadi pakai fulfillment)
            $table->string('profil_analisa', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tipe', 'idx_produk_tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produk');
    }
};
