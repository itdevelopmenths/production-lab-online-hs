<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Indeks pada kartu_stok untuk filter rentang tanggal & tipe mutasi
        Schema::table('kartu_stok', function (Blueprint $table) {
            $table->index(['tanggal', 'created_at'], 'idx_kartu_tanggal_created');
            $table->index('tipe', 'idx_kartu_tipe');
            $table->index('created_at', 'idx_kartu_created_at');
        });

        // 2. Indeks pada item purchase order untuk agregasi withSum & join
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->index('po_id', 'idx_po_items_po_id');
            $table->index('produk_id', 'idx_po_items_produk_id');
        });

        // 3. Indeks pada pembayaran PO untuk kalkulasi pelunasan & termin
        Schema::table('payments', function (Blueprint $table) {
            $table->index('po_id', 'idx_payments_po_id');
            $table->index('tanggal_bayar', 'idx_payments_tanggal_bayar');
        });

        // 4. Indeks pada barang datang & item penerimaan gudang
        Schema::table('barang_datang', function (Blueprint $table) {
            $table->index('po_id', 'idx_bardat_po_id');
            $table->index('tanggal_terima', 'idx_bardat_tanggal_terima');
        });

        Schema::table('barang_datang_items', function (Blueprint $table) {
            $table->index('bardat_id', 'idx_bardat_items_bardat_id');
            $table->index('po_item_id', 'idx_bardat_items_po_item_id');
        });

        // 5. Indeks pada request & transfer untuk query gudang & sorting
        Schema::table('request_transfers', function (Blueprint $table) {
            $table->index('created_at', 'idx_rt_created_at');
            $table->index('gudang_asal_id', 'idx_rt_gudang_asal');
            $table->index('gudang_tujuan_id', 'idx_rt_gudang_tujuan');
        });

        Schema::table('request_transfer_items', function (Blueprint $table) {
            $table->index('request_transfer_id', 'idx_rt_items_rt_id');
            $table->index('produk_id', 'idx_rt_items_produk_id');
        });

        // 6. Indeks pada purchase orders untuk filtering dan sorting DataTables
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index('created_at', 'idx_po_created_at');
            $table->index('tanggal', 'idx_po_tanggal');
            $table->index('supplier_id', 'idx_po_supplier_id');
            $table->index('gudang_id', 'idx_po_gudang_id');
        });

        // 7. Indeks pada batch produksi
        Schema::table('batch_produksi', function (Blueprint $table) {
            $table->index('created_at', 'idx_batch_created_at');
            $table->index('tanggal', 'idx_batch_tanggal');
            $table->index('produk_id', 'idx_batch_produk_id');
        });

        // 8. Indeks pada alokasi bahan
        Schema::table('alokasi_bahan', function (Blueprint $table) {
            $table->index('bahan_id', 'idx_alokasi_bahan_id');
        });
    }

    public function down(): void
    {
        Schema::table('alokasi_bahan', function (Blueprint $table) {
            $table->dropIndex('idx_alokasi_bahan_id');
        });

        Schema::table('batch_produksi', function (Blueprint $table) {
            $table->dropIndex('idx_batch_created_at');
            $table->dropIndex('idx_batch_tanggal');
            $table->dropIndex('idx_batch_produk_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('idx_po_created_at');
            $table->dropIndex('idx_po_tanggal');
            $table->dropIndex('idx_po_supplier_id');
            $table->dropIndex('idx_po_gudang_id');
        });

        Schema::table('request_transfer_items', function (Blueprint $table) {
            $table->dropIndex('idx_rt_items_rt_id');
            $table->dropIndex('idx_rt_items_produk_id');
        });

        Schema::table('request_transfers', function (Blueprint $table) {
            $table->dropIndex('idx_rt_created_at');
            $table->dropIndex('idx_rt_gudang_asal');
            $table->dropIndex('idx_rt_gudang_tujuan');
        });

        Schema::table('barang_datang_items', function (Blueprint $table) {
            $table->dropIndex('idx_bardat_items_bardat_id');
            $table->dropIndex('idx_bardat_items_po_item_id');
        });

        Schema::table('barang_datang', function (Blueprint $table) {
            $table->dropIndex('idx_bardat_po_id');
            $table->dropIndex('idx_bardat_tanggal_terima');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_po_id');
            $table->dropIndex('idx_payments_tanggal_bayar');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropIndex('idx_po_items_po_id');
            $table->dropIndex('idx_po_items_produk_id');
        });

        Schema::table('kartu_stok', function (Blueprint $table) {
            $table->dropIndex('idx_kartu_tanggal_created');
            $table->dropIndex('idx_kartu_tipe');
            $table->dropIndex('idx_kartu_created_at');
        });
    }
};
