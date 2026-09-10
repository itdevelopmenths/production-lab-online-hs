<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('no_po', 30)->unique();
            $table->foreignId('supplier_id')->constrained('supplier')->cascadeOnDelete();
            $table->date('tanggal');
            $table->date('eta')->nullable();
            $table->string('sumber_dana', 50)->nullable();
            // draft / diajukan / disetujui / dikirim_ke_gudang / selesai / dibatalkan
            $table->string('status', 25)->default('draft');
            $table->boolean('dari_analisa')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status', 'idx_po_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
