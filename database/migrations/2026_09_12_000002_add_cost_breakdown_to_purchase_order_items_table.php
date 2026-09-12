<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('diskon', 18, 2)->default(0)->after('harga_total');
            $table->decimal('ppn', 18, 2)->default(0)->after('diskon');
            $table->decimal('ongkir', 18, 2)->default(0)->after('ppn');
            $table->decimal('adjustment', 18, 2)->default(0)->after('ongkir');
            $table->decimal('hpp_per_satuan', 18, 4)->default(0)->after('adjustment');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'diskon',
                'ppn',
                'ongkir',
                'adjustment',
                'hpp_per_satuan',
            ]);
        });
    }
};
