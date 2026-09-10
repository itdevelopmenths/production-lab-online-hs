<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang_datang', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->date('tanggal_terima');
            // baik / rusak_sebagian
            $table->string('kondisi', 20)->default('baik');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('barang_datang_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bardat_id')->constrained('barang_datang')->cascadeOnDelete();
            $table->foreignUuid('po_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
            $table->decimal('qty_diterima', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang_datang_items');
        Schema::dropIfExists('barang_datang');
    }
};
