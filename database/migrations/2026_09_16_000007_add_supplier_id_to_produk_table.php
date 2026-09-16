<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('varian_id')->constrained('supplier')->nullOnDelete();
            $table->index('supplier_id', 'idx_produk_supplier_id');
        });

        // Backfill default suppliers untuk data bahan & kemas eksisting jika supplier tersedia
        $supLokal = DB::table('supplier')->where('kategori', 'lokal')->value('id') ?? DB::table('supplier')->value('id');
        $supImpor = DB::table('supplier')->where('kategori', 'impor')->value('id') ?? $supLokal;

        if ($supLokal) {
            DB::table('produk')->whereIn('sku', ['ALK-01', 'OIL-GOH'])->update(['supplier_id' => $supLokal]);
            DB::table('produk')->where('profil_analisa', 'lokal')->whereNull('supplier_id')->update(['supplier_id' => $supLokal]);
        }

        if ($supImpor) {
            DB::table('produk')->whereIn('sku', ['BTL-P50', 'CAP-01'])->update(['supplier_id' => $supImpor]);
            DB::table('produk')->where('profil_analisa', 'impor')->whereNull('supplier_id')->update(['supplier_id' => $supImpor]);
        }
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropIndex('idx_produk_supplier_id');
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
