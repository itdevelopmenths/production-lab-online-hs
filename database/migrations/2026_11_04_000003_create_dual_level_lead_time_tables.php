<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. po_lead_time
        Schema::create('po_lead_time', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('po_id')->unique()->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('supplier')->cascadeOnDelete();
            $table->timestamp('waktu_mulai');
            $table->timestamp('waktu_selesai');
            $table->integer('total_lt_menit');
            $table->decimal('total_lt_hari', 10, 1);
            $table->integer('lt_internal_menit')->default(0);
            $table->integer('lt_supplier_menit')->default(0);
            $table->integer('lt_logistik_menit')->default(0);
            $table->boolean('is_valid')->default(true);
            $table->string('alasan_tidak_valid', 255)->nullable();
            $table->foreignId('divalidasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'is_valid'], 'idx_polt_supplier_valid');
        });

        // 2. supplier_lt_summary
        Schema::create('supplier_lt_summary', function (Blueprint $table) {
            $table->foreignId('supplier_id')->primary()->constrained('supplier')->cascadeOnDelete();
            $table->integer('jumlah_transaksi')->default(0);
            $table->bigInteger('total_lt_menit')->default(0);
            $table->decimal('average_lt_hari', 10, 1)->default(0);
            $table->decimal('max_lt_hari', 10, 1)->default(0);
            $table->timestamp('diperbarui_pada')->useCurrent();
        });

        // 3. supplier_lt_stage_summary
        Schema::create('supplier_lt_stage_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('supplier')->cascadeOnDelete();
            $table->string('stage', 20);
            $table->integer('jumlah_transaksi')->default(0);
            $table->bigInteger('total_menit')->default(0);
            $table->decimal('average_hari', 10, 1)->default(0);
            $table->decimal('max_hari', 10, 1)->default(0);
            $table->timestamps();

            $table->unique(['supplier_id', 'stage'], 'uq_sltss_supplier_stage');
        });

        // 4. supplier_produk_lt_summary
        Schema::create('supplier_produk_lt_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('supplier')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->integer('jumlah_transaksi')->default(0);
            $table->bigInteger('total_lt_menit')->default(0);
            $table->decimal('average_lt_hari', 10, 1)->default(0);
            $table->decimal('max_lt_hari', 10, 1)->default(0);
            $table->timestamp('diperbarui_pada')->useCurrent();

            $table->unique(['supplier_id', 'produk_id'], 'uq_splts_supplier_produk');
        });

        // 5. supplier_produk_lt_stage_summary
        Schema::create('supplier_produk_lt_stage_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('supplier')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->string('stage', 20);
            $table->integer('jumlah_transaksi')->default(0);
            $table->bigInteger('total_menit')->default(0);
            $table->decimal('average_hari', 10, 1)->default(0);
            $table->decimal('max_hari', 10, 1)->default(0);
            $table->timestamps();

            $table->unique(['supplier_id', 'produk_id', 'stage'], 'uq_splts_stage');
        });

        // 6. supplier_lt_history
        Schema::create('supplier_lt_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('supplier')->cascadeOnDelete();
            $table->foreignId('produk_id')->nullable()->constrained('produk')->cascadeOnDelete();
            $table->foreignUuid('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('jenis_event', 20); // transaksi_baru, invalidasi, koreksi
            $table->integer('jumlah_sebelum')->default(0);
            $table->integer('jumlah_sesudah')->default(0);
            $table->bigInteger('total_menit_sebelum')->default(0);
            $table->bigInteger('total_menit_sesudah')->default(0);
            $table->decimal('average_sebelum', 10, 1)->default(0);
            $table->decimal('average_sesudah', 10, 1)->default(0);
            $table->decimal('max_sebelum', 10, 1)->default(0);
            $table->decimal('max_sesudah', 10, 1)->default(0);
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dicatat_pada')->useCurrent();
            $table->timestamps();

            $table->index(['supplier_id', 'produk_id', 'jenis_event'], 'idx_slth_composite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_lt_history');
        Schema::dropIfExists('supplier_produk_lt_stage_summary');
        Schema::dropIfExists('supplier_produk_lt_summary');
        Schema::dropIfExists('supplier_lt_stage_summary');
        Schema::dropIfExists('supplier_lt_summary');
        Schema::dropIfExists('po_lead_time');
    }
};

