<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_jadi_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('bahan_id')->constrained('produk')->cascadeOnDelete();
            $table->decimal('qty_per_unit', 15, 4);
            $table->timestamps();

            $table->unique(['produk_jadi_id', 'bahan_id'], 'uq_bom_produk_bahan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom');
    }
};
