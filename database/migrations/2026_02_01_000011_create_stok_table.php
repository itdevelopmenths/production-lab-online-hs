<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok', function (Blueprint $table) {
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->decimal('qty_saat_ini', 15, 2)->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->primary(['produk_id', 'gudang_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok');
    }
};
