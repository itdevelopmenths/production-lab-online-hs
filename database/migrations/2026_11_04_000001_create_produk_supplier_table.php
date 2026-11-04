<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produk_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('supplier')->cascadeOnDelete();
            // aktif / nonaktif
            $table->string('status', 10)->default('aktif');
            $table->timestamps();

            $table->unique(['produk_id', 'supplier_id'], 'uq_produk_supplier');
            $table->index(['supplier_id', 'status'], 'idx_ps_supplier_status');
        });

        // Backfill data dari produk.supplier_id yang sudah ada
        if (Schema::hasTable('produk') && Schema::hasColumn('produk', 'supplier_id')) {
            $existing = DB::table('produk')
                ->whereNotNull('supplier_id')
                ->get(['id', 'supplier_id']);

            foreach ($existing as $row) {
                DB::table('produk_supplier')->updateOrInsert(
                    [
                        'produk_id' => $row->id,
                        'supplier_id' => $row->supplier_id,
                    ],
                    [
                        'status' => 'aktif',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('produk_supplier');
    }
};

