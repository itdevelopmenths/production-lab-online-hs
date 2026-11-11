<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('produk')) {
            return;
        }

        if (! Schema::hasColumn('produk', 'klasifikasi_abc_id')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->foreignId('klasifikasi_abc_id')
                    ->nullable()
                    ->after('profil_analisa')
                    ->constrained('klasifikasi_abc')
                    ->nullOnDelete();
                $table->index('klasifikasi_abc_id', 'idx_produk_klasifikasi_abc_id');
            });
        }

        if (! Schema::hasTable('klasifikasi_abc')) {
            return;
        }

        if (Schema::hasTable('analisa_impor')) {
            $rows = DB::table('analisa_impor')
                ->whereNotNull('klasifikasi_abc_id')
                ->select(['produk_id', 'klasifikasi_abc_id'])
                ->get();

            foreach ($rows as $row) {
                DB::table('produk')
                    ->where('id', $row->produk_id)
                    ->where('tipe', '!=', 'produk_jadi')
                    ->whereNull('klasifikasi_abc_id')
                    ->update(['klasifikasi_abc_id' => $row->klasifikasi_abc_id]);
            }
        }

        if (Schema::hasTable('analisa_impor_meta')) {
            $metaRows = DB::table('analisa_impor_meta')
                ->whereNotNull('klasifikasi_abc_id')
                ->select(['produk_id', 'klasifikasi_abc_id'])
                ->get();

            foreach ($metaRows as $row) {
                DB::table('produk')
                    ->where('id', $row->produk_id)
                    ->where('tipe', '!=', 'produk_jadi')
                    ->whereNull('klasifikasi_abc_id')
                    ->update(['klasifikasi_abc_id' => $row->klasifikasi_abc_id]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('produk') && Schema::hasColumn('produk', 'klasifikasi_abc_id')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->dropForeign(['klasifikasi_abc_id']);
                $table->dropIndex('idx_produk_klasifikasi_abc_id');
                $table->dropColumn('klasifikasi_abc_id');
            });
        }
    }
};
