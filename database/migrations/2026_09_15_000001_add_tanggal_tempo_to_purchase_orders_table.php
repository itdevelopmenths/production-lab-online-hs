<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->date('tanggal_tempo')->nullable()->after('skema_bayar');
            $table->index('tanggal_tempo');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['tanggal_tempo']);
            $table->dropColumn('tanggal_tempo');
        });
    }
};
