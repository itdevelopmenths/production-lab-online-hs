<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('no_invoice', 50)->nullable()->after('no_po')->index();
            $table->foreignId('gudang_id')->nullable()->after('supplier_id')->constrained('gudang')->nullOnDelete();
            $table->string('skema_bayar', 20)->default('cash')->after('sumber_dana');
            $table->decimal('subtotal_produk', 18, 2)->default(0)->after('skema_bayar');
            $table->decimal('diskon_total', 18, 2)->default(0)->after('subtotal_produk');
            $table->decimal('ppn_nominal', 18, 2)->default(0)->after('diskon_total');
            $table->decimal('ongkos_kirim', 18, 2)->default(0)->after('ppn_nominal');
            $table->decimal('adjustment', 18, 2)->default(0)->after('ongkos_kirim');
            $table->decimal('grand_total', 18, 2)->default(0)->after('adjustment');
            $table->string('status_pembayaran', 20)->default('belum_lunas')->after('grand_total')->index();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gudang_id');
            $table->dropColumn([
                'no_invoice',
                'skema_bayar',
                'subtotal_produk',
                'diskon_total',
                'ppn_nominal',
                'ongkos_kirim',
                'adjustment',
                'grand_total',
                'status_pembayaran',
            ]);
        });
    }
};
