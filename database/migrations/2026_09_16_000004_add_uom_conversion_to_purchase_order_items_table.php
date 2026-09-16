<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('qty_satuan_beli', 15, 2)->nullable()->after('qty');
            $table->string('satuan_beli', 20)->nullable()->after('qty_satuan_beli');
            $table->decimal('faktor_konversi', 15, 4)->default(1.0000)->after('satuan_beli');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'qty_satuan_beli',
                'satuan_beli',
                'faktor_konversi',
            ]);
        });
    }
};
