<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel requests
        Schema::create('requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_request', 30)->unique();
            // request_bahan / request_produk_jadi
            $table->string('jenis', 20);
            $table->foreignUuid('referensi_batch_id')->nullable()->constrained('batch_produksi')->nullOnDelete();
            $table->foreignId('gudang_asal_id')->constrained('gudang')->cascadeOnDelete();
            $table->foreignId('gudang_tujuan_id')->constrained('gudang')->cascadeOnDelete();
            // draft / diajukan / disetujui / diproses / selesai / ditolak / dibatalkan
            $table->string('status', 20)->default('draft');
            $table->text('catatan')->nullable();
            $table->string('alasan_tolak', 255)->nullable();
            $table->string('alasan_batal', 255)->nullable();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['jenis', 'status'], 'idx_requests_jenis_status');
            $table->index('referensi_batch_id', 'idx_requests_batch');
        });

        // 2. Tabel request_items
        Schema::create('request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->decimal('qty_diminta', 15, 2);
            $table->timestamps();

            $table->index(['request_id', 'produk_id']);
        });

        // 3. Tabel transfers
        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_transaksi', 30)->unique();
            // pemenuhan_request_bahan / retur_bahan / kirim_produk_jadi / antar_fulfillment / retur_produk_jadi
            $table->string('jenis', 30);
            $table->foreignId('gudang_asal_id')->nullable()->constrained('gudang')->nullOnDelete();
            $table->foreignId('gudang_tujuan_id')->constrained('gudang')->cascadeOnDelete();
            $table->foreignUuid('request_id')->nullable()->constrained('requests')->nullOnDelete();
            $table->foreignUuid('referensi_batch_id')->nullable()->constrained('batch_produksi')->nullOnDelete();
            $table->timestamp('waktu_dikirim')->nullable();
            $table->timestamp('waktu_diterima')->nullable();
            $table->foreignId('diterima_oleh')->nullable()->constrained('users')->nullOnDelete();
            // draft / dikirim / selesai / dibatalkan
            $table->string('status', 20)->default('draft');
            $table->text('catatan')->nullable();
            $table->string('alasan_batal', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['jenis', 'status'], 'idx_transfers_jenis_status');
            $table->index(['status', 'gudang_tujuan_id', 'waktu_diterima'], 'idx_transfers_defect_monthly');
        });

        // Partial unique index 1:1 active transfer per request
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement("CREATE UNIQUE INDEX uq_transfers_active_request ON transfers (request_id) WHERE request_id IS NOT NULL AND status != 'dibatalkan'");
        } else {
            Schema::table('transfers', function (Blueprint $table) {
                $table->index('request_id', 'idx_transfers_request_id');
            });
        }

        // 4. Tabel transfer_items
        Schema::create('transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignUuid('request_item_id')->nullable()->constrained('request_items')->nullOnDelete();
            $table->decimal('qty_dikirim', 15, 2);
            $table->decimal('qty_diterima', 15, 2)->nullable();
            $table->decimal('qty_baik', 15, 2)->nullable();
            $table->decimal('qty_rusak', 15, 2)->default(0);
            $table->text('keterangan_rusak')->nullable();
            $table->timestamps();

            $table->index(['transfer_id', 'produk_id']);
        });

        // 5. Backfill data dari request_transfers lama jika ada
        if (Schema::hasTable('request_transfers')) {
            $legacyRts = DB::table('request_transfers')->get();
            foreach ($legacyRts as $rt) {
                if ($rt->jenis === 'req_bahan') {
                    $requestId = (string) Str::uuid();
                    DB::table('requests')->insert([
                        'id' => $requestId,
                        'no_request' => 'REQ-' . $rt->no_transaksi,
                        'jenis' => 'request_bahan',
                        'referensi_batch_id' => $rt->referensi_batch_id,
                        'gudang_asal_id' => $rt->gudang_asal_id,
                        'gudang_tujuan_id' => $rt->gudang_tujuan_id,
                        'status' => $rt->status,
                        'catatan' => $rt->catatan,
                        'alasan_batal' => $rt->alasan_batal ?? null,
                        'created_by' => $rt->created_by,
                        'created_at' => $rt->created_at,
                        'updated_at' => $rt->updated_at,
                    ]);

                    $rtItems = DB::table('request_transfer_items')->where('request_transfer_id', $rt->id)->get();
                    $reqItemIdMap = [];
                    foreach ($rtItems as $item) {
                        $reqItemId = (string) Str::uuid();
                        $reqItemIdMap[$item->id] = $reqItemId;
                        DB::table('request_items')->insert([
                            'id' => $reqItemId,
                            'request_id' => $requestId,
                            'produk_id' => $item->produk_id,
                            'qty_diminta' => $item->qty_diminta ?? 0,
                            'created_at' => $item->created_at,
                            'updated_at' => $item->updated_at,
                        ]);
                    }

                    // Jika sudah dalam pengiriman / selesai, buat transfer pemenuhan
                    if (in_array($rt->status, ['diproses', 'dikirim', 'selesai'], true)) {
                        $transferStatus = in_array($rt->status, ['dikirim', 'selesai'], true) ? $rt->status : 'draft';
                        DB::table('transfers')->insert([
                            'id' => $rt->id, // Pertahankan ID untuk link kartu_stok
                            'no_transaksi' => $rt->no_transaksi,
                            'jenis' => 'pemenuhan_request_bahan',
                            'gudang_asal_id' => $rt->gudang_asal_id,
                            'gudang_tujuan_id' => $rt->gudang_tujuan_id,
                            'request_id' => $requestId,
                            'referensi_batch_id' => $rt->referensi_batch_id,
                            'waktu_dikirim' => $rt->created_at,
                            'waktu_diterima' => $rt->status === 'selesai' ? $rt->updated_at : null,
                            'status' => $transferStatus,
                            'catatan' => $rt->catatan,
                            'created_by' => $rt->created_by,
                            'created_at' => $rt->created_at,
                            'updated_at' => $rt->updated_at,
                        ]);

                        foreach ($rtItems as $item) {
                            DB::table('transfer_items')->insert([
                                'id' => (string) Str::uuid(),
                                'transfer_id' => $rt->id,
                                'produk_id' => $item->produk_id,
                                'request_item_id' => $reqItemIdMap[$item->id] ?? null,
                                'qty_dikirim' => $item->qty_dikirim ?? $item->qty_diminta ?? 0,
                                'qty_diterima' => $item->qty_diterima,
                                'qty_baik' => $item->qty_baik ?? $item->qty_diterima,
                                'qty_rusak' => $item->qty_rusak ?? 0,
                                'keterangan_rusak' => $item->keterangan_rusak ?? null,
                                'created_at' => $item->created_at,
                                'updated_at' => $item->updated_at,
                            ]);
                        }
                    }
                } else {
                    // Transfer langsung (retur, kirim produk jadi, dll)
                    $transferStatus = in_array($rt->status, ['draft', 'dikirim', 'selesai', 'dibatalkan'], true) ? $rt->status : 'draft';
                    DB::table('transfers')->insert([
                        'id' => $rt->id, // Pertahankan ID asli
                        'no_transaksi' => $rt->no_transaksi,
                        'jenis' => $rt->jenis,
                        'gudang_asal_id' => $rt->gudang_asal_id,
                        'gudang_tujuan_id' => $rt->gudang_tujuan_id,
                        'request_id' => null,
                        'referensi_batch_id' => $rt->referensi_batch_id,
                        'waktu_dikirim' => $rt->created_at,
                        'waktu_diterima' => $rt->status === 'selesai' ? $rt->updated_at : null,
                        'status' => $transferStatus,
                        'catatan' => $rt->catatan,
                        'created_by' => $rt->created_by,
                        'created_at' => $rt->created_at,
                        'updated_at' => $rt->updated_at,
                    ]);

                    $rtItems = DB::table('request_transfer_items')->where('request_transfer_id', $rt->id)->get();
                    foreach ($rtItems as $item) {
                        DB::table('transfer_items')->insert([
                            'id' => (string) Str::uuid(),
                            'transfer_id' => $rt->id,
                            'produk_id' => $item->produk_id,
                            'request_item_id' => null,
                            'qty_dikirim' => $item->qty_dikirim ?? $item->qty_diminta ?? 0,
                            'qty_diterima' => $item->qty_diterima,
                            'qty_baik' => $item->qty_baik ?? $item->qty_diterima,
                            'qty_rusak' => $item->qty_rusak ?? 0,
                            'keterangan_rusak' => $item->keterangan_rusak ?? null,
                            'created_at' => $item->created_at,
                            'updated_at' => $item->updated_at,
                        ]);
                    }
                }
            }

            // Update kartu_stok referensi_tipe
            if (Schema::hasTable('kartu_stok')) {
                DB::table('kartu_stok')
                    ->where('referensi_tipe', 'request_transfer')
                    ->update(['referensi_tipe' => 'transfer']);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('request_items');
        Schema::dropIfExists('requests');
    }
};

