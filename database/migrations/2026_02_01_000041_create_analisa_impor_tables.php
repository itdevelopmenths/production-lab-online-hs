<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisa_impor_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->unique()->constrained('produk')->cascadeOnDelete();
            $table->boolean('punya_varian')->default(false);
            $table->decimal('out_total_4bulan', 15, 2)->default(0);
            $table->decimal('lead_time_average', 10, 2)->default(0);
            $table->decimal('lead_time_max', 10, 2)->default(0);
            // wajib_a / a / b / c
            $table->string('klasifikasi_abc', 10)->default('c');
            $table->integer('review_period')->default(0);
            $table->decimal('harga_per_satuan', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('analisa_impor_varian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analisa_impor_meta_id')->constrained('analisa_impor_meta')->cascadeOnDelete();
            $table->string('nama_varian', 50)->nullable();
            $table->decimal('persentase_distribusi', 5, 4)->default(1);
            $table->decimal('stok_saat_ini', 15, 2)->default(0);
            $table->decimal('inbound_before_eta', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisa_impor_varian');
        Schema::dropIfExists('analisa_impor_meta');
    }
};
