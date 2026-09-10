<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('no_transaksi', 30)->unique();
            // req_bahan / retur_bahan / kirim_produk_jadi / antar_fulfillment / retur_produk_jadi
            $table->string('jenis', 30);
            $table->foreignId('gudang_asal_id')->nullable()->constrained('gudang')->nullOnDelete();
            $table->foreignId('gudang_tujuan_id')->nullable()->constrained('gudang')->nullOnDelete();
            $table->foreignId('referensi_batch_id')->nullable()->constrained('batch_produksi')->nullOnDelete();
            // draft / diajukan / disetujui / diproses / dikirim / selesai / dibatalkan
            $table->string('status', 20)->default('draft');
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['jenis', 'status'], 'idx_rt_jenis_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_transfers');
    }
};
