<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opname', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batch_produksi')->cascadeOnDelete();
            $table->foreignId('bahan_id')->constrained('produk')->cascadeOnDelete();
            $table->decimal('pemakaian_teoritis', 15, 2);
            $table->decimal('pemakaian_aktual', 15, 2);
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname');
    }
};
