<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_transfer_id')->constrained('request_transfers')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->decimal('qty_diminta', 15, 2);
            $table->decimal('qty_dikirim', 15, 2)->nullable();
            $table->decimal('qty_diterima', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_transfer_items');
    }
};
