<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_termins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('termin_ke');
            $table->date('tanggal_tempo');
            $table->decimal('nominal_tagihan', 18, 2);
            $table->decimal('nominal_dibayar', 18, 2)->default(0);
            $table->string('status', 20)->default('belum_lunas');
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->index(['po_id', 'termin_ke']);
            $table->index('status');
            $table->index('tanggal_tempo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_termins');
    }
};
