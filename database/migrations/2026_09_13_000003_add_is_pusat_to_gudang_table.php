<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gudang', function (Blueprint $table) {
            $table->boolean('is_pusat')->default(false)->after('tipe');
            $table->index(['tipe', 'is_pusat'], 'idx_gudang_tipe_pusat');
        });

        // Selaraskan data lama ke model baru (Skenario A)
        // 1. Gudang Bahan Baku Pusat
        DB::table('gudang')
            ->where('kode', 'GD-PUSAT')
            ->orWhere(function ($q) {
                $q->where('tipe', 'bahan_baku')
                  ->where(DB::raw('LOWER(nama)'), 'LIKE', '%pusat%');
            })
            ->update([
                'is_pusat' => true,
                'tipe' => 'bahan_baku',
            ]);

        // 2. Fulfillment Pusat
        DB::table('gudang')
            ->where('tipe', 'fulfillment_pusat')
            ->orWhere('kode', 'FF-PUSAT')
            ->update([
                'tipe' => 'fulfillment',
                'is_pusat' => true,
            ]);

        // 3. Fulfillment Cabang
        DB::table('gudang')
            ->where('tipe', 'fulfillment_cabang')
            ->update([
                'tipe' => 'fulfillment',
                'is_pusat' => false,
            ]);
    }

    public function down(): void
    {
        // Revert data to legacy types before dropping column
        DB::table('gudang')
            ->where('tipe', 'fulfillment')
            ->where('is_pusat', true)
            ->update(['tipe' => 'fulfillment_pusat']);

        DB::table('gudang')
            ->where('tipe', 'fulfillment')
            ->where('is_pusat', false)
            ->update(['tipe' => 'fulfillment_cabang']);

        Schema::table('gudang', function (Blueprint $table) {
            $table->dropIndex('idx_gudang_tipe_pusat');
            $table->dropColumn('is_pusat');
        });
    }
};
