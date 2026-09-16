<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Lead Time Impor (1 baris per SKU)
        Schema::create('lead_time_impor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->unique()->constrained('produk')->cascadeOnDelete();
            $table->decimal('lead_time_average', 10, 2)->default(0);
            $table->decimal('lead_time_max', 10, 2)->default(0);
            $table->timestamps();
        });

        // 2. Tabel Working Data Analisa Impor (1 baris per SKU)
        Schema::create('analisa_impor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->unique()->constrained('produk')->cascadeOnDelete();
            $table->decimal('out', 15, 2)->default(0);
            $table->decimal('adu_base', 15, 4)->default(0);
            $table->decimal('adu_eta', 15, 4)->default(0);
            $table->decimal('lead_time', 10, 2)->default(0);
            $table->integer('review_period')->default(30);
            $table->string('klasifikasi_abc', 10)->default('c'); // 'wajib_a', 'a', 'b', 'c'
            $table->integer('tambahan_buffer_hari')->default(0);
            $table->decimal('buffer_days', 10, 2)->default(0);
            $table->decimal('safety_stock', 15, 2)->default(0);
            $table->decimal('minimum_stock', 15, 2)->default(0);
            $table->decimal('target_stock', 15, 2)->default(0);
            $table->decimal('stok_saat_ini', 15, 2)->default(0);
            $table->decimal('inbound_before_eta', 15, 2)->default(0);
            $table->decimal('proyeksi', 15, 2)->default(0);
            $table->decimal('qty_order', 15, 2)->default(0);
            $table->decimal('po', 15, 2)->default(0); // MOQ rounded
            $table->string('status', 10)->default('tidak'); // 'po' / 'tidak'
            $table->decimal('harga_per_satuan', 15, 2)->default(0);
            $table->decimal('total_nominal_order', 18, 2)->default(0);
            $table->boolean('punya_varian')->default(false);
            $table->jsonb('varian_detail')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 3. Alter riwayat_analisa: tambahkan kolom session_id, is_locked, detail_payload
        if (Schema::hasTable('riwayat_analisa')) {
            Schema::table('riwayat_analisa', function (Blueprint $table) {
                if (! Schema::hasColumn('riwayat_analisa', 'session_id')) {
                    $table->string('session_id', 50)->nullable()->index();
                }
                if (! Schema::hasColumn('riwayat_analisa', 'is_locked')) {
                    $table->boolean('is_locked')->default(true);
                }
                if (! Schema::hasColumn('riwayat_analisa', 'detail_payload')) {
                    $table->jsonb('detail_payload')->nullable();
                }
            });
        }

        // 4. Backfill Data Eksisting jika ada di analisa_impor_meta
        if (Schema::hasTable('analisa_impor_meta')) {
            $metas = DB::table('analisa_impor_meta')->get();
            $abcBonusMap = ['wajib_a' => 4, 'a' => 4, 'b' => 2, 'c' => 0];

            foreach ($metas as $meta) {
                $ltAvg = (float) $meta->lead_time_average;
                $ltMax = (float) $meta->lead_time_max;
                $reviewPeriod = (int) ($meta->review_period ?: 30);
                $abc = strtolower((string) ($meta->klasifikasi_abc ?: 'c'));
                $bonusHari = $abcBonusMap[$abc] ?? 0;
                $bufferDays = max(0, $ltMax - $ltAvg) + $bonusHari;

                // Simpan lead_time_impor
                DB::table('lead_time_impor')->updateOrInsert(
                    ['produk_id' => $meta->produk_id],
                    [
                        'lead_time_average' => $ltAvg,
                        'lead_time_max' => $ltMax,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                // Ambil varian jika ada
                $varians = DB::table('analisa_impor_varian')
                    ->where('analisa_impor_meta_id', $meta->id)
                    ->get();

                $totalOut = (float) $meta->out_total_4bulan;
                $harga = (float) $meta->harga_per_satuan;

                $totalStok = 0.0;
                $totalInbound = 0.0;
                $totalSafetyStock = 0.0;
                $totalMinStock = 0.0;
                $totalTargetStock = 0.0;
                $totalProyeksi = 0.0;
                $totalQtyOrder = 0.0;
                $totalPo = 0.0;
                $varianDetails = [];

                if ($varians->isNotEmpty()) {
                    foreach ($varians as $v) {
                        $vOut = (float) $v->persentase_distribusi * $totalOut;
                        $vAduBase = $vOut / 122;
                        $vAduEta = $meta->punya_varian
                            ? $vAduBase + ($vAduBase * $ltAvg / 30)
                            : $vAduBase + ($reviewPeriod > 0 ? $ltAvg / $reviewPeriod : 0);

                        $vSafety = $vAduEta * $bufferDays;
                        $vMin = $vAduEta * $ltAvg;
                        $vTarget = $vAduEta * ($ltAvg + $reviewPeriod) + $vSafety;
                        $vStok = (float) $v->stok_saat_ini;
                        $vInbound = (float) $v->inbound_before_eta;
                        $vProyeksi = $vStok + $vInbound - ($vAduEta * $ltAvg);
                        $vSelisih = $vProyeksi - $vTarget;
                        $isPo = $vSelisih < 0;
                        $vQtyOrder = $isPo ? abs($vSelisih) : 0;

                        $totalStok += $vStok;
                        $totalInbound += $vInbound;
                        $totalSafetyStock += $vSafety;
                        $totalMinStock += $vMin;
                        $totalTargetStock += $vTarget;
                        $totalProyeksi += $vProyeksi;
                        $totalQtyOrder += $vQtyOrder;
                        $totalPo += $vQtyOrder;

                        $varianDetails[] = [
                            'nama_varian' => $v->nama_varian,
                            'persentase' => (float) $v->persentase_distribusi,
                            'out' => round($vOut, 2),
                            'adu_base' => round($vAduBase, 4),
                            'adu_eta' => round($vAduEta, 4),
                            'buffer_days' => round($bufferDays, 2),
                            'safety_stock' => round($vSafety, 2),
                            'minimum_stock' => round($vMin, 2),
                            'target_stock' => round($vTarget, 2),
                            'stok_saat_ini' => round($vStok, 2),
                            'inbound' => round($vInbound, 2),
                            'proyeksi' => round($vProyeksi, 2),
                            'selisih' => round($vSelisih, 2),
                            'status' => $isPo ? 'po' : 'tidak',
                            'qty_order' => round($vQtyOrder, 2),
                        ];
                    }
                    $aduBase = $totalOut / 122;
                    $aduEta = $meta->punya_varian
                        ? $aduBase + ($aduBase * $ltAvg / 30)
                        : $aduBase + ($reviewPeriod > 0 ? $ltAvg / $reviewPeriod : 0);
                } else {
                    $aduBase = $totalOut / 122;
                    $aduEta = $aduBase + ($reviewPeriod > 0 ? $ltAvg / $reviewPeriod : 0);
                    $totalSafetyStock = $aduEta * $bufferDays;
                    $totalMinStock = $aduEta * $ltAvg;
                    $totalTargetStock = $aduEta * ($ltAvg + $reviewPeriod) + $totalSafetyStock;
                    $totalProyeksi = 0 - ($aduEta * $ltAvg);
                    $totalQtyOrder = $totalTargetStock > 0 ? $totalTargetStock : 0;
                    $totalPo = $totalQtyOrder;
                }

                $status = $totalQtyOrder > 0 ? 'po' : 'tidak';
                $totalNominal = $totalPo * $harga;

                DB::table('analisa_impor')->updateOrInsert(
                    ['produk_id' => $meta->produk_id],
                    [
                        'out' => $totalOut,
                        'adu_base' => round($aduBase, 4),
                        'adu_eta' => round($aduEta, 4),
                        'lead_time' => $ltAvg,
                        'review_period' => $reviewPeriod,
                        'klasifikasi_abc' => $abc,
                        'tambahan_buffer_hari' => $bonusHari,
                        'buffer_days' => round($bufferDays, 2),
                        'safety_stock' => round($totalSafetyStock, 2),
                        'minimum_stock' => round($totalMinStock, 2),
                        'target_stock' => round($totalTargetStock, 2),
                        'stok_saat_ini' => round($totalStok, 2),
                        'inbound_before_eta' => round($totalInbound, 2),
                        'proyeksi' => round($totalProyeksi, 2),
                        'qty_order' => round($totalQtyOrder, 2),
                        'po' => round($totalPo, 2),
                        'status' => $status,
                        'harga_per_satuan' => $harga,
                        'total_nominal_order' => round($totalNominal, 2),
                        'punya_varian' => (bool) $meta->punya_varian,
                        'varian_detail' => json_encode($varianDetails),
                        'generated_at' => now(),
                        'generated_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analisa_impor');
        Schema::dropIfExists('lead_time_impor');

        if (Schema::hasTable('riwayat_analisa')) {
            Schema::table('riwayat_analisa', function (Blueprint $table) {
                if (Schema::hasColumn('riwayat_analisa', 'detail_payload')) {
                    $table->dropColumn('detail_payload');
                }
                if (Schema::hasColumn('riwayat_analisa', 'is_locked')) {
                    $table->dropColumn('is_locked');
                }
                if (Schema::hasColumn('riwayat_analisa', 'session_id')) {
                    $table->dropColumn('session_id');
                }
            });
        }
    }
};
