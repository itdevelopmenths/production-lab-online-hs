<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_produksi_outputs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('batch_produksi')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->decimal('qty_rencana', 15, 2);
            $table->decimal('qty_baik', 15, 2)->nullable();
            $table->decimal('qty_rusak', 15, 2)->nullable();
            $table->string('catatan', 255)->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'produk_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_produksi_outputs');
    }
};
