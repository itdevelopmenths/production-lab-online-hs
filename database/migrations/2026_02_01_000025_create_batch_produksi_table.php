<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_produksi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_batch', 30)->unique();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->decimal('qty_rencana', 15, 2);
            $table->decimal('qty_baik', 15, 2)->nullable();
            $table->decimal('qty_rusak', 15, 2)->nullable();
            // gudang operasional sumber bahan
            $table->foreignId('gudang_operasional_id')->nullable()->constrained('gudang')->nullOnDelete();
            // gudang fulfillment tujuan produk jadi
            $table->foreignId('gudang_tujuan_rencana_id')->nullable()->constrained('gudang')->nullOnDelete();
            // rencana / release / selesai / dibatalkan
            $table->string('status', 15)->default('rencana');
            $table->date('tanggal');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status', 'idx_batch_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_produksi');
    }
};
