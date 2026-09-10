<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            // tempo / termin / pelunasan
            $table->string('skema', 15);
            $table->date('tanggal_bayar');
            $table->decimal('nominal', 18, 2);
            $table->string('bukti_file', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
