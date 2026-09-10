<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alokasi_bahan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('batch_produksi')->cascadeOnDelete();
            $table->foreignId('bahan_id')->constrained('produk')->cascadeOnDelete();
            $table->decimal('qty_dialokasikan', 15, 2);
            // aktif / dilepas / dibatalkan
            $table->string('status', 15)->default('aktif');
            $table->timestamps();

            $table->index(['batch_id', 'status'], 'idx_alokasi_batch_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alokasi_bahan');
    }
};
