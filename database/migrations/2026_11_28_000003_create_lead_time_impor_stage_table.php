<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_time_impor_stage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->string('skenario', 10); // 'average' / 'max'
            $table->decimal('perencanaan', 10, 1)->default(0);
            $table->decimal('supplier_confirm', 10, 1)->default(0);
            $table->decimal('approval', 10, 1)->default(0);
            $table->decimal('payment', 10, 1)->default(0);
            $table->decimal('po', 10, 1)->default(0);
            $table->decimal('pengiriman', 10, 1)->default(0);
            $table->decimal('unloading', 10, 1)->default(0);
            $table->decimal('input', 10, 1)->default(0);
            $table->timestamps();

            $table->unique(['produk_id', 'skenario'], 'uq_ltis_produk_skenario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_time_impor_stage');
    }
};
