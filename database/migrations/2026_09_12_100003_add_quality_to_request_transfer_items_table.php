<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_transfer_items', function (Blueprint $table) {
            $table->decimal('qty_baik', 15, 2)->nullable()->after('qty_diterima');
            $table->decimal('qty_rusak', 15, 2)->nullable()->after('qty_baik');
            $table->text('keterangan_rusak')->nullable()->after('qty_rusak');
        });
    }

    public function down(): void
    {
        Schema::table('request_transfer_items', function (Blueprint $table) {
            $table->dropColumn(['qty_baik', 'qty_rusak', 'keterangan_rusak']);
        });
    }
};
