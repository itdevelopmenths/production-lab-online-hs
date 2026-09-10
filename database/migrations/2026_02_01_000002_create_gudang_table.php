<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gudang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 150);
            // bahan_baku / operasional / fulfillment_pusat / fulfillment_cabang
            $table->string('tipe', 30);
            $table->foreignId('parent_gudang_id')->nullable()
                ->constrained('gudang')->nullOnDelete();
            // aktif / nonaktif
            $table->string('status', 10)->default('aktif');
            $table->boolean('allow_negative_stock')->default(false);
            $table->timestamps();

            $table->index('tipe', 'idx_gudang_tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gudang');
    }
};
