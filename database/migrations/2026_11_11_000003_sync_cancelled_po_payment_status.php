<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'status_pembayaran')) {
            DB::table('purchase_orders')
                ->where('status', 'dibatalkan')
                ->where(function ($q) {
                    $q->where('status_pembayaran', '!=', 'batal')
                      ->orWhereNull('status_pembayaran');
                })
                ->update(['status_pembayaran' => 'batal']);
        }
    }

    public function down(): void
    {
        // Sinkronisasi data status_pembayaran untuk PO dibatalkan tidak perlu rollback destruktif.
    }
};

