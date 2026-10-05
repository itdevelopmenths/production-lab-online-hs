<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create table klasifikasi_abc
        if (! Schema::hasTable('klasifikasi_abc')) {
            Schema::create('klasifikasi_abc', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 20)->unique();
                $table->string('nama', 100);
                $table->integer('tambahan_buffer_hari')->default(0);
                $table->text('deskripsi')->nullable();
                $table->string('warna_badge', 30)->default('gray');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Insert standard default master data
            $now = now();
            DB::table('klasifikasi_abc')->insert([
                [
                    'kode' => 'wajib_a',
                    'nama' => 'Wajib A (Buffer +4 Hari)',
                    'tambahan_buffer_hari' => 4,
                    'deskripsi' => 'Produk import vital tanpa substitusi lokal',
                    'warna_badge' => 'red',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'kode' => 'a',
                    'nama' => 'A - Fast Moving (Buffer +4 Hari)',
                    'tambahan_buffer_hari' => 4,
                    'deskripsi' => 'Produk import fast moving prioritas tinggi',
                    'warna_badge' => 'amber',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'kode' => 'b',
                    'nama' => 'B - Medium Moving (Buffer +2 Hari)',
                    'tambahan_buffer_hari' => 2,
                    'deskripsi' => 'Produk import pergerakan sedang',
                    'warna_badge' => 'blue',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'kode' => 'c',
                    'nama' => 'C - Slow Moving (Buffer +0 Hari)',
                    'tambahan_buffer_hari' => 0,
                    'deskripsi' => 'Produk import pergerakan lambat',
                    'warna_badge' => 'gray',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        // 2. Add columns to analisa_impor
        if (Schema::hasTable('analisa_impor')) {
            Schema::table('analisa_impor', function (Blueprint $table) {
                if (! Schema::hasColumn('analisa_impor', 'periode_mulai')) {
                    $table->date('periode_mulai')->nullable()->after('out');
                }
                if (! Schema::hasColumn('analisa_impor', 'periode_akhir')) {
                    $table->date('periode_akhir')->nullable()->after('periode_mulai');
                }
                if (! Schema::hasColumn('analisa_impor', 'jumlah_hari_periode')) {
                    $table->integer('jumlah_hari_periode')->default(122)->after('periode_akhir');
                }
                if (! Schema::hasColumn('analisa_impor', 'klasifikasi_abc_id')) {
                    $table->foreignId('klasifikasi_abc_id')->nullable()->after('klasifikasi_abc')->constrained('klasifikasi_abc')->nullOnDelete();
                }
            });
        }

        // 3. Add columns to analisa_impor_meta
        if (Schema::hasTable('analisa_impor_meta')) {
            Schema::table('analisa_impor_meta', function (Blueprint $table) {
                if (! Schema::hasColumn('analisa_impor_meta', 'periode_mulai')) {
                    $table->date('periode_mulai')->nullable()->after('out_total_4bulan');
                }
                if (! Schema::hasColumn('analisa_impor_meta', 'periode_akhir')) {
                    $table->date('periode_akhir')->nullable()->after('periode_mulai');
                }
                if (! Schema::hasColumn('analisa_impor_meta', 'jumlah_hari_periode')) {
                    $table->integer('jumlah_hari_periode')->default(122)->after('periode_akhir');
                }
                if (! Schema::hasColumn('analisa_impor_meta', 'klasifikasi_abc_id')) {
                    $table->foreignId('klasifikasi_abc_id')->nullable()->after('klasifikasi_abc')->constrained('klasifikasi_abc')->nullOnDelete();
                }
            });
        }

        // 4. Backfill existing data
        $abcMap = DB::table('klasifikasi_abc')->pluck('id', 'kode')->toArray();

        if (Schema::hasTable('analisa_impor')) {
            $defaultEnd = now()->toDateString();
            $defaultStart = now()->subMonths(4)->toDateString();
            $defaultDays = \Carbon\Carbon::parse($defaultStart)->diffInDays(\Carbon\Carbon::parse($defaultEnd)) + 1;

            foreach (DB::table('analisa_impor')->get() as $row) {
                $code = strtolower((string) ($row->klasifikasi_abc ?? 'c'));
                $abcId = $abcMap[$code] ?? ($abcMap['c'] ?? null);

                DB::table('analisa_impor')->where('id', $row->id)->update([
                    'periode_mulai' => $row->periode_mulai ?? $defaultStart,
                    'periode_akhir' => $row->periode_akhir ?? $defaultEnd,
                    'jumlah_hari_periode' => $row->jumlah_hari_periode ?: $defaultDays,
                    'klasifikasi_abc_id' => $row->klasifikasi_abc_id ?? $abcId,
                ]);
            }
        }

        if (Schema::hasTable('analisa_impor_meta')) {
            $defaultEnd = now()->toDateString();
            $defaultStart = now()->subMonths(4)->toDateString();
            $defaultDays = \Carbon\Carbon::parse($defaultStart)->diffInDays(\Carbon\Carbon::parse($defaultEnd)) + 1;

            foreach (DB::table('analisa_impor_meta')->get() as $row) {
                $code = strtolower((string) ($row->klasifikasi_abc ?? 'c'));
                $abcId = $abcMap[$code] ?? ($abcMap['c'] ?? null);

                DB::table('analisa_impor_meta')->where('id', $row->id)->update([
                    'periode_mulai' => $row->periode_mulai ?? $defaultStart,
                    'periode_akhir' => $row->periode_akhir ?? $defaultEnd,
                    'jumlah_hari_periode' => $row->jumlah_hari_periode ?: $defaultDays,
                    'klasifikasi_abc_id' => $row->klasifikasi_abc_id ?? $abcId,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('analisa_impor_meta')) {
            Schema::table('analisa_impor_meta', function (Blueprint $table) {
                if (Schema::hasColumn('analisa_impor_meta', 'klasifikasi_abc_id')) {
                    $table->dropForeign(['klasifikasi_abc_id']);
                    $table->dropColumn('klasifikasi_abc_id');
                }
                if (Schema::hasColumn('analisa_impor_meta', 'jumlah_hari_periode')) {
                    $table->dropColumn('jumlah_hari_periode');
                }
                if (Schema::hasColumn('analisa_impor_meta', 'periode_akhir')) {
                    $table->dropColumn('periode_akhir');
                }
                if (Schema::hasColumn('analisa_impor_meta', 'periode_mulai')) {
                    $table->dropColumn('periode_mulai');
                }
            });
        }

        if (Schema::hasTable('analisa_impor')) {
            Schema::table('analisa_impor', function (Blueprint $table) {
                if (Schema::hasColumn('analisa_impor', 'klasifikasi_abc_id')) {
                    $table->dropForeign(['klasifikasi_abc_id']);
                    $table->dropColumn('klasifikasi_abc_id');
                }
                if (Schema::hasColumn('analisa_impor', 'jumlah_hari_periode')) {
                    $table->dropColumn('jumlah_hari_periode');
                }
                if (Schema::hasColumn('analisa_impor', 'periode_akhir')) {
                    $table->dropColumn('periode_akhir');
                }
                if (Schema::hasColumn('analisa_impor', 'periode_mulai')) {
                    $table->dropColumn('periode_mulai');
                }
            });
        }

        Schema::dropIfExists('klasifikasi_abc');
    }
};
