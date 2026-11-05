<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. lead_time_lokal_stage: alter duration columns to DECIMAL(10,1)
        if (Schema::hasTable('lead_time_lokal_stage')) {
            Schema::table('lead_time_lokal_stage', function (Blueprint $table) {
                $table->decimal('perencanaan', 10, 1)->default(0)->change();
                $table->decimal('approval', 10, 1)->default(0)->change();
                $table->decimal('supplier_confirm', 10, 1)->default(0)->change();
                $table->decimal('payment', 10, 1)->default(0)->change();
                $table->decimal('po', 10, 1)->default(0)->change();
                $table->decimal('pengemasan', 10, 1)->default(0)->change();
                $table->decimal('pengiriman', 10, 1)->default(0)->change();
                $table->decimal('unloading', 10, 1)->default(0)->change();
                $table->decimal('input', 10, 1)->default(0)->change();
            });
        }

        // 2. lead_time_lokal: alter duration columns to DECIMAL(10,1)
        if (Schema::hasTable('lead_time_lokal')) {
            Schema::table('lead_time_lokal', function (Blueprint $table) {
                $table->decimal('total_average_lead_time', 10, 1)->default(0)->change();
                $table->decimal('total_max_lead_time', 10, 1)->default(0)->change();
                $table->decimal('tambahan_buffer_hari', 10, 1)->default(0)->change();
                $table->decimal('safety_stock', 10, 1)->default(0)->change();
            });
        }

        // 3. analisa_lokal: alter duration columns and add supplier_terpilih_id & sumber_lt
        if (Schema::hasTable('analisa_lokal')) {
            Schema::table('analisa_lokal', function (Blueprint $table) {
                $table->decimal('total_average_lead_time', 10, 1)->default(0)->change();
                $table->decimal('safety_stock', 10, 1)->default(0)->change();

                if (! Schema::hasColumn('analisa_lokal', 'supplier_terpilih_id')) {
                    $table->foreignId('supplier_terpilih_id')->nullable()->constrained('supplier')->nullOnDelete();
                }
                if (! Schema::hasColumn('analisa_lokal', 'sumber_lt')) {
                    $table->string('sumber_lt', 15)->default('manual');
                }
            });
        }

        // 4. lead_time_impor: ensure DECIMAL(10,1)
        if (Schema::hasTable('lead_time_impor')) {
            Schema::table('lead_time_impor', function (Blueprint $table) {
                $table->decimal('lead_time_average', 10, 1)->default(0)->change();
                $table->decimal('lead_time_max', 10, 1)->default(0)->change();
            });
        }

        // 5. analisa_impor: alter duration columns and add supplier_terpilih_id & sumber_lt
        if (Schema::hasTable('analisa_impor')) {
            Schema::table('analisa_impor', function (Blueprint $table) {
                $table->decimal('lead_time', 10, 1)->default(0)->change();
                $table->decimal('buffer_days', 10, 1)->default(0)->change();
                $table->decimal('safety_stock', 10, 1)->default(0)->change();

                if (! Schema::hasColumn('analisa_impor', 'supplier_terpilih_id')) {
                    $table->foreignId('supplier_terpilih_id')->nullable()->constrained('supplier')->nullOnDelete();
                }
                if (! Schema::hasColumn('analisa_impor', 'sumber_lt')) {
                    $table->string('sumber_lt', 15)->default('manual');
                }
            });
        }

        // 6. riwayat_analisa: add supplier_id, average_lt_dipakai, max_lt_dipakai, sumber_lt
        if (Schema::hasTable('riwayat_analisa')) {
            Schema::table('riwayat_analisa', function (Blueprint $table) {
                if (! Schema::hasColumn('riwayat_analisa', 'supplier_id')) {
                    $table->foreignId('supplier_id')->nullable()->constrained('supplier')->nullOnDelete();
                }
                if (! Schema::hasColumn('riwayat_analisa', 'average_lt_dipakai')) {
                    $table->decimal('average_lt_dipakai', 10, 1)->nullable();
                }
                if (! Schema::hasColumn('riwayat_analisa', 'max_lt_dipakai')) {
                    $table->decimal('max_lt_dipakai', 10, 1)->nullable();
                }
                if (! Schema::hasColumn('riwayat_analisa', 'sumber_lt')) {
                    $table->string('sumber_lt', 15)->default('manual');
                }
            });
        }

        // 7. Backfill supplier_terpilih_id from produk.supplier_id if available
        if (Schema::hasColumn('produk', 'supplier_id')) {
            if (Schema::hasTable('analisa_lokal') && Schema::hasColumn('analisa_lokal', 'supplier_terpilih_id')) {
                DB::table('analisa_lokal')
                    ->whereNull('supplier_terpilih_id')
                    ->update([
                        'supplier_terpilih_id' => DB::raw('(SELECT supplier_id FROM produk WHERE produk.id = analisa_lokal.produk_id)'),
                    ]);
            }
            if (Schema::hasTable('analisa_impor') && Schema::hasColumn('analisa_impor', 'supplier_terpilih_id')) {
                DB::table('analisa_impor')
                    ->whereNull('supplier_terpilih_id')
                    ->update([
                        'supplier_terpilih_id' => DB::raw('(SELECT supplier_id FROM produk WHERE produk.id = analisa_impor.produk_id)'),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('riwayat_analisa')) {
            Schema::table('riwayat_analisa', function (Blueprint $table) {
                if (Schema::hasColumn('riwayat_analisa', 'supplier_id')) {
                    $table->dropForeign(['supplier_id']);
                    $table->dropColumn(['supplier_id', 'average_lt_dipakai', 'max_lt_dipakai', 'sumber_lt']);
                }
            });
        }

        if (Schema::hasTable('analisa_impor')) {
            Schema::table('analisa_impor', function (Blueprint $table) {
                if (Schema::hasColumn('analisa_impor', 'supplier_terpilih_id')) {
                    $table->dropForeign(['supplier_terpilih_id']);
                    $table->dropColumn(['supplier_terpilih_id', 'sumber_lt']);
                }
            });
        }

        if (Schema::hasTable('analisa_lokal')) {
            Schema::table('analisa_lokal', function (Blueprint $table) {
                if (Schema::hasColumn('analisa_lokal', 'supplier_terpilih_id')) {
                    $table->dropForeign(['supplier_terpilih_id']);
                    $table->dropColumn(['supplier_terpilih_id', 'sumber_lt']);
                }
            });
        }
    }
};

