<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barang_datang_items', function (Blueprint $table) {
            $table->decimal('selisih', 15, 2)->default(0)->after('qty_diterima');
            $table->text('keterangan_selisih')->nullable()->after('selisih');
        });
    }

    public function down(): void
    {
        Schema::table('barang_datang_items', function (Blueprint $table) {
            $table->dropColumn([
                'selisih',
                'keterangan_selisih',
            ]);
        });
    }
};
