<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Rincian 9 Tahap Lead Time (2 baris per SKU: average & max)
        Schema::create('lead_time_lokal_stage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->string('skenario', 10); // 'average' / 'max'
            $table->integer('perencanaan')->default(0);
            $table->integer('approval')->default(0);
            $table->integer('supplier_confirm')->default(0);
            $table->integer('payment')->default(0);
            $table->integer('po')->default(0);
            $table->integer('pengemasan')->default(0);
            $table->integer('pengiriman')->default(0);
            $table->integer('unloading')->default(0);
            $table->integer('input')->default(0);
            $table->timestamps();

            $table->unique(['produk_id', 'skenario'], 'uq_ltls_produk_skenario');
        });

        // 2. Tabel Ringkasan Lead Time (1 baris per SKU)
        Schema::create('lead_time_lokal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->unique()->constrained('produk')->cascadeOnDelete();
            $table->integer('total_average_lead_time')->default(0);
            $table->integer('total_max_lead_time')->default(0);
            $table->integer('tambahan_buffer_hari')->default(0);
            $table->integer('safety_stock')->default(0);
            $table->timestamps();
        });

        // 3. Tabel Working Data Analisa Lokal (1 baris per SKU)
        Schema::create('analisa_lokal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->unique()->constrained('produk')->cascadeOnDelete();
            $table->integer('total_average_lead_time')->default(0);
            $table->integer('safety_stock')->default(0);
            $table->decimal('terjual_rata_rata_4bulan', 15, 2)->default(0);
            $table->decimal('adu', 15, 4)->default(0);
            $table->integer('review_period')->default(15);
            $table->decimal('batas_minimum', 15, 2)->default(0);
            $table->decimal('target_stock', 15, 2)->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 4. Tabel Rekomendasi Order Lokal (1 baris per SKU)
        Schema::create('rekomendasi_order_lokal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->unique()->constrained('produk')->cascadeOnDelete();
            $table->decimal('batas_minimum', 15, 2)->default(0);
            $table->decimal('target_stock', 15, 2)->default(0);
            $table->decimal('stok_saat_ini', 15, 2)->default(0);
            $table->decimal('akan_datang', 15, 2)->default(0);
            $table->decimal('selisih', 15, 2)->default(0);
            $table->decimal('rumus_moq', 15, 2)->default(0);
            $table->string('status', 10)->default('tidak'); // 'order' / 'tidak'
            $table->decimal('rekomendasi_order', 15, 2)->default(0);
            $table->decimal('harga_ml_pcs', 15, 2)->default(0);
            $table->decimal('total_nominal_order', 18, 2)->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 5. Backfill Data Eksisting jika ada di lead_time_stage & analisa_lokal_input
        if (Schema::hasTable('lead_time_stage')) {
            $existingProductIds = DB::table('lead_time_stage')->distinct()->pluck('produk_id');
            foreach ($existingProductIds as $produkId) {
                $buffer = 0;
                $avgSum = 0;
                $maxSum = 0;

                foreach (['average', 'max'] as $skenario) {
                    $rows = DB::table('lead_time_stage')
                        ->where('produk_id', $produkId)
                        ->where('skenario', $skenario)
                        ->get()
                        ->keyBy('tahap');

                    $stageData = [
                        'produk_id' => $produkId,
                        'skenario' => $skenario,
                        'perencanaan' => (int) ($rows['perencanaan']->jumlah_hari ?? 0),
                        'approval' => (int) ($rows['approval']->jumlah_hari ?? 0),
                        'supplier_confirm' => (int) ($rows['supplier_confirm']->jumlah_hari ?? 0),
                        'payment' => (int) ($rows['payment']->jumlah_hari ?? 0),
                        'po' => (int) ($rows['po']->jumlah_hari ?? 0),
                        'pengemasan' => (int) ($rows['pengemasan']->jumlah_hari ?? 0),
                        'pengiriman' => (int) ($rows['pengiriman']->jumlah_hari ?? 0),
                        'unloading' => (int) ($rows['unloading']->jumlah_hari ?? 0),
                        'input' => (int) ($rows['input']->jumlah_hari ?? 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $total = array_sum([
                        $stageData['perencanaan'],
                        $stageData['approval'],
                        $stageData['supplier_confirm'],
                        $stageData['payment'],
                        $stageData['po'],
                        $stageData['pengemasan'],
                        $stageData['pengiriman'],
                        $stageData['unloading'],
                        $stageData['input'],
                    ]);

                    if ($skenario === 'average') {
                        $avgSum = $total;
                    } else {
                        $maxSum = $total;
                        foreach ($rows as $r) {
                            if (! empty($r->tambahan_buffer_hari)) {
                                $buffer += (int) $r->tambahan_buffer_hari;
                            }
                        }
                    }

                    DB::table('lead_time_lokal_stage')->updateOrInsert(
                        ['produk_id' => $produkId, 'skenario' => $skenario],
                        $stageData
                    );
                }

                $safetyStock = max(0, $maxSum - $avgSum) + $buffer;
                DB::table('lead_time_lokal')->updateOrInsert(
                    ['produk_id' => $produkId],
                    [
                        'total_average_lead_time' => $avgSum,
                        'total_max_lead_time' => $maxSum,
                        'tambahan_buffer_hari' => $buffer,
                        'safety_stock' => $safetyStock,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                // Jika ada input di analisa_lokal_input, salin ke analisa_lokal & rekomendasi_order_lokal
                if (Schema::hasTable('analisa_lokal_input')) {
                    $input = DB::table('analisa_lokal_input')->where('produk_id', $produkId)->first();
                    if ($input) {
                        $p = DB::table('produk')->where('id', $produkId)->first();
                        $terjual = (float) $input->terjual_rata_rata_4bulan;
                        $adu = $terjual / 30;
                        $rp = (int) ($input->review_period ?: 15);
                        $batasMin = $adu * ($avgSum + $safetyStock);
                        $targetStock = $adu * ($avgSum + $safetyStock + $rp);

                        DB::table('analisa_lokal')->updateOrInsert(
                            ['produk_id' => $produkId],
                            [
                                'total_average_lead_time' => $avgSum,
                                'safety_stock' => $safetyStock,
                                'terjual_rata_rata_4bulan' => $terjual,
                                'adu' => $adu,
                                'review_period' => $rp,
                                'batas_minimum' => $batasMin,
                                'target_stock' => $targetStock,
                                'generated_at' => now(),
                                'generated_by' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );

                        $stokNow = (float) $input->stok_saat_ini;
                        $akanDatang = (float) $input->akan_datang;
                        $selisih = ($stokNow + $akanDatang) - $targetStock;
                        $isOrder = ($stokNow + $akanDatang <= $batasMin) || ($selisih < 0);
                        $moq = (float) ($p?->satuan_order_moq ?: 1);
                        $rumusMoq = $isOrder ? ceil(abs($selisih) / $moq) * $moq : 0;
                        $faktor = (float) ($p?->faktor_konversi ?: 1);
                        $rekOrder = $faktor > 0 ? round($rumusMoq / $faktor, 2) : $rumusMoq;
                        $hargaSatuan = (float) ($input->harga_per_satuan ?: ($p?->harga_hpp ?: 0));

                        DB::table('rekomendasi_order_lokal')->updateOrInsert(
                            ['produk_id' => $produkId],
                            [
                                'batas_minimum' => $batasMin,
                                'target_stock' => $targetStock,
                                'stok_saat_ini' => $stokNow,
                                'akan_datang' => $akanDatang,
                                'selisih' => $selisih,
                                'rumus_moq' => $rumusMoq,
                                'status' => $isOrder ? 'order' : 'tidak',
                                'rekomendasi_order' => $rekOrder,
                                'harga_ml_pcs' => $hargaSatuan,
                                'total_nominal_order' => $rumusMoq * $hargaSatuan,
                                'generated_at' => now(),
                                'generated_by' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rekomendasi_order_lokal');
        Schema::dropIfExists('analisa_lokal');
        Schema::dropIfExists('lead_time_lokal');
        Schema::dropIfExists('lead_time_lokal_stage');
    }
};
