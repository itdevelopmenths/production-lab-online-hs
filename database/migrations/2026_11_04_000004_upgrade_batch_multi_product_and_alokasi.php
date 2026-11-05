<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Alter batch_produksi: nullable produk_id & qty_rencana
        Schema::table('batch_produksi', function (Blueprint $table) {
            $table->unsignedBigInteger('produk_id')->nullable()->change();
            $table->decimal('qty_rencana', 15, 2)->nullable()->change();
        });

        // 2. Alter batch_produksi_outputs: add gudang_tujuan_rencana_id
        Schema::table('batch_produksi_outputs', function (Blueprint $table) {
            $table->foreignId('gudang_tujuan_rencana_id')->nullable()->after('catatan')->constrained('gudang')->nullOnDelete();
        });

        // Backfill batch_produksi lama ke batch_produksi_outputs jika belum ada
        $existingBatches = DB::table('batch_produksi')->get();
        foreach ($existingBatches as $batch) {
            if ($batch->produk_id && $batch->qty_rencana) {
                $exists = DB::table('batch_produksi_outputs')
                    ->where('batch_id', $batch->id)
                    ->where('produk_id', $batch->produk_id)
                    ->exists();

                if (! $exists) {
                    DB::table('batch_produksi_outputs')->insert([
                        'id' => (string) Str::uuid(),
                        'batch_id' => $batch->id,
                        'produk_id' => $batch->produk_id,
                        'qty_rencana' => $batch->qty_rencana,
                        'qty_baik' => $batch->qty_baik,
                        'qty_rusak' => $batch->qty_rusak,
                        'gudang_tujuan_rencana_id' => $batch->gudang_tujuan_rencana_id ?? null,
                        'created_at' => $batch->created_at ?? now(),
                        'updated_at' => $batch->updated_at ?? now(),
                    ]);
                }
            }
        }

        // Add unique constraint on batch_produksi_outputs
        Schema::table('batch_produksi_outputs', function (Blueprint $table) {
            $table->unique(['batch_id', 'produk_id'], 'uq_batch_output_produk');
        });

        // 3. Clean duplicate alokasi_bahan if any before adding unique constraint
        $duplicates = DB::table('alokasi_bahan')
            ->select('batch_id', 'bahan_id')
            ->groupBy('batch_id', 'bahan_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $rows = DB::table('alokasi_bahan')
                ->where('batch_id', $dup->batch_id)
                ->where('bahan_id', $dup->bahan_id)
                ->orderBy('created_at')
                ->get();

            $totalQty = $rows->sum('qty_dialokasikan');
            $first = $rows->first();

            DB::table('alokasi_bahan')
                ->where('batch_id', $dup->batch_id)
                ->where('bahan_id', $dup->bahan_id)
                ->where('id', '!=', $first->id)
                ->delete();

            DB::table('alokasi_bahan')
                ->where('id', $first->id)
                ->update(['qty_dialokasikan' => $totalQty]);
        }

        Schema::table('alokasi_bahan', function (Blueprint $table) {
            $table->unique(['batch_id', 'bahan_id'], 'uq_alokasi_batch_bahan');
        });
    }

    public function down(): void
    {
        Schema::table('alokasi_bahan', function (Blueprint $table) {
            $table->dropUnique('uq_alokasi_batch_bahan');
        });

        Schema::table('batch_produksi_outputs', function (Blueprint $table) {
            $table->dropUnique('uq_batch_output_produk');
            $table->dropConstrainedForeignId('gudang_tujuan_rencana_id');
        });

        Schema::table('batch_produksi', function (Blueprint $table) {
            $table->unsignedBigInteger('produk_id')->nullable(false)->change();
            $table->decimal('qty_rencana', 15, 2)->nullable(false)->change();
        });
    }
};
