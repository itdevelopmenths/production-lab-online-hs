<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Alter purchase_orders
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('stage_aktif', 20)->default('perencanaan')->after('status')->index();
            $table->date('eta_supplier')->nullable()->after('eta');
            $table->string('no_referensi_supplier', 50)->nullable()->after('no_invoice');
            $table->smallInteger('revisi_ke')->default(0)->after('stage_aktif');
            $table->string('alasan_batal', 255)->nullable()->after('revisi_ke');
            $table->foreignId('dibatalkan_oleh')->nullable()->after('alasan_batal')->constrained('users')->nullOnDelete();
        });

        // 2. Alter purchase_order_items
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('qty_konfirmasi', 15, 2)->nullable()->after('qty');
            $table->decimal('harga_konfirmasi', 18, 2)->nullable()->after('harga_total');
        });

        // 3. Create po_stage
        Schema::create('po_stage', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('stage', 20);
            $table->smallInteger('urutan');
            $table->smallInteger('putaran')->default(1);
            $table->string('status', 15)->default('berjalan'); // berjalan, selesai, dikembalikan
            $table->timestamp('waktu_mulai')->useCurrent();
            $table->timestamp('waktu_selesai')->nullable();
            $table->integer('durasi_menit')->nullable();
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dicatat_pada')->nullable();
            $table->string('catatan', 255)->nullable();
            $table->timestamps();

            $table->unique(['po_id', 'stage', 'putaran'], 'uq_po_stage_putaran');
            $table->index(['po_id', 'stage']);
            $table->index(['po_id', 'status']);
        });

        // 4. Create po_pengiriman
        Schema::create('po_pengiriman', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('po_id')->unique()->constrained('purchase_orders')->cascadeOnDelete();
            $table->integer('jumlah_koli')->nullable();
            $table->decimal('berat_kg', 12, 2)->nullable();
            $table->string('catatan_pengemasan', 255)->nullable();
            $table->string('ekspedisi', 100)->nullable();
            $table->string('moda', 10)->nullable(); // darat, laut, udara
            $table->string('no_resi', 60)->nullable();
            $table->string('no_kontainer', 30)->nullable();
            $table->timestamp('eta_tiba')->nullable();
            $table->timestamps();
        });

        // 5. Create po_revisi
        Schema::create('po_revisi', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->smallInteger('revisi_ke');
            $table->string('stage_saat_ajukan', 20);
            $table->string('status', 15)->default('menunggu'); // menunggu, disetujui, ditolak
            $table->string('alasan', 255);
            $table->json('data_lama');
            $table->json('data_usulan');
            $table->foreignId('diajukan_oleh')->constrained('users')->cascadeOnDelete();
            $table->timestamp('diajukan_pada')->useCurrent();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_pada')->nullable();
            $table->string('catatan_keputusan', 255)->nullable();
            $table->timestamps();

            $table->index(['po_id', 'status']);
        });

        // 6. Create po_log_perubahan
        Schema::create('po_log_perubahan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('nama_field', 50);
            $table->string('nilai_lama', 255)->nullable();
            $table->string('nilai_baru', 255)->nullable();
            $table->foreignId('diubah_oleh')->constrained('users')->cascadeOnDelete();
            $table->timestamp('diubah_pada')->useCurrent();
            $table->timestamps();

            $table->index(['po_id', 'diubah_pada']);
        });

        // 7. Alter barang_datang
        Schema::table('barang_datang', function (Blueprint $table) {
            $table->timestamp('waktu_tiba')->nullable()->after('tanggal_terima');
            $table->string('status_posting', 15)->default('belum')->after('kondisi'); // belum, diposting
            $table->foreignId('diposting_oleh')->nullable()->after('status_posting')->constrained('users')->nullOnDelete();
            $table->timestamp('diposting_pada')->nullable()->after('diposting_oleh');
        });

        // 8. Alter barang_datang_items
        Schema::table('barang_datang_items', function (Blueprint $table) {
            $table->decimal('qty_fisik', 15, 2)->default(0)->after('po_item_id');
            $table->decimal('qty_diterima', 15, 2)->nullable()->change();
        });

        // Backfill existing data
        DB::table('barang_datang')->update([
            'status_posting' => 'diposting',
            'diposting_pada' => now(),
        ]);

        DB::table('purchase_orders')
            ->where('skema_bayar', 'cash')
            ->update(['skema_bayar' => 'pelunasan']);

        // Backfill status lama ke 4 status v2.4 (draft, berjalan, selesai, dibatalkan)
        DB::table('purchase_orders')
            ->whereIn('status', ['diajukan', 'disetujui', 'dikirim_ke_gudang'])
            ->update(['status' => 'berjalan']);
    }

    public function down(): void
    {
        Schema::table('barang_datang_items', function (Blueprint $table) {
            $table->dropColumn('qty_fisik');
            $table->decimal('qty_diterima', 15, 2)->nullable(false)->change();
        });

        Schema::table('barang_datang', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diposting_oleh');
            $table->dropColumn([
                'waktu_tiba',
                'status_posting',
                'diposting_pada',
            ]);
        });

        Schema::dropIfExists('po_log_perubahan');
        Schema::dropIfExists('po_revisi');
        Schema::dropIfExists('po_pengiriman');
        Schema::dropIfExists('po_stage');

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['qty_konfirmasi', 'harga_konfirmasi']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dibatalkan_oleh');
            $table->dropColumn([
                'stage_aktif',
                'eta_supplier',
                'no_referensi_supplier',
                'revisi_ke',
                'alasan_batal',
            ]);
        });
    }
};

