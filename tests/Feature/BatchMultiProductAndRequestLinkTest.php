<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\Bom;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\RequestTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchMultiProductAndRequestLinkTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $operasional;
    protected Gudang $gudangOp;
    protected Gudang $gudangPusat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $this->gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
    }

    /**
     * TC-40: Batch multi-produk dengan BOM yang berbagi bahan.
     * Kebutuhan digabung per bahan; qty_baik dan qty_rusak dicatat per produk.
     */
    public function test_tc40_multi_product_batch_production_aggregates_shared_bom_materials(): void
    {
        // 1. Buat bahan baku bersama
        $alkohol = Produk::create(['sku' => 'RAW-ALK', 'nama' => 'Alkohol 96%', 'tipe' => 'bahan', 'satuan' => 'ml', 'is_active' => true]);
        $oilA = Produk::create(['sku' => 'RAW-OILA', 'nama' => 'Fragrance Oil A', 'tipe' => 'bahan', 'satuan' => 'ml', 'is_active' => true]);
        $oilB = Produk::create(['sku' => 'RAW-OILB', 'nama' => 'Fragrance Oil B', 'tipe' => 'bahan', 'satuan' => 'ml', 'is_active' => true]);
        $botol = Produk::create(['sku' => 'PKG-BTL', 'nama' => 'Botol 50ml', 'tipe' => 'kemas', 'satuan' => 'pcs', 'is_active' => true]);

        // 2. Buat 4 produk jadi yang berbagi bahan
        $pj1 = Produk::create(['sku' => 'PJ-01', 'nama' => 'Parfum Lavender 50ml', 'tipe' => 'produk_jadi', 'satuan' => 'pcs', 'is_active' => true]);
        $pj2 = Produk::create(['sku' => 'PJ-02', 'nama' => 'Parfum Rose 50ml', 'tipe' => 'produk_jadi', 'satuan' => 'pcs', 'is_active' => true]);
        $pj3 = Produk::create(['sku' => 'PJ-03', 'nama' => 'Parfum Vanilla 50ml', 'tipe' => 'produk_jadi', 'satuan' => 'pcs', 'is_active' => true]);
        $pj4 = Produk::create(['sku' => 'PJ-04', 'nama' => 'Parfum Jasmine 50ml', 'tipe' => 'produk_jadi', 'satuan' => 'pcs', 'is_active' => true]);

        // BOM PJ-01 (1 unit butuh 40 ml alkohol, 10 ml oilA, 1 botol)
        Bom::create(['produk_jadi_id' => $pj1->id, 'bahan_id' => $alkohol->id, 'qty_per_unit' => 40]);
        Bom::create(['produk_jadi_id' => $pj1->id, 'bahan_id' => $oilA->id, 'qty_per_unit' => 10]);
        Bom::create(['produk_jadi_id' => $pj1->id, 'bahan_id' => $botol->id, 'qty_per_unit' => 1]);

        // BOM PJ-02 (1 unit butuh 40 ml alkohol, 10 ml oilB, 1 botol)
        Bom::create(['produk_jadi_id' => $pj2->id, 'bahan_id' => $alkohol->id, 'qty_per_unit' => 40]);
        Bom::create(['produk_jadi_id' => $pj2->id, 'bahan_id' => $oilB->id, 'qty_per_unit' => 10]);
        Bom::create(['produk_jadi_id' => $pj2->id, 'bahan_id' => $botol->id, 'qty_per_unit' => 1]);

        // BOM PJ-03 (1 unit butuh 35 ml alkohol, 15 ml oilA, 1 botol)
        Bom::create(['produk_jadi_id' => $pj3->id, 'bahan_id' => $alkohol->id, 'qty_per_unit' => 35]);
        Bom::create(['produk_jadi_id' => $pj3->id, 'bahan_id' => $oilA->id, 'qty_per_unit' => 15]);
        Bom::create(['produk_jadi_id' => $pj3->id, 'bahan_id' => $botol->id, 'qty_per_unit' => 1]);

        // BOM PJ-04 (1 unit butuh 35 ml alkohol, 15 ml oilB, 1 botol)
        Bom::create(['produk_jadi_id' => $pj4->id, 'bahan_id' => $alkohol->id, 'qty_per_unit' => 35]);
        Bom::create(['produk_jadi_id' => $pj4->id, 'bahan_id' => $oilB->id, 'qty_per_unit' => 15]);
        Bom::create(['produk_jadi_id' => $pj4->id, 'bahan_id' => $botol->id, 'qty_per_unit' => 1]);

        // 3. Buat Batch Produksi berisi 4 produk jadi
        // Rencana: PJ-01: 100 pcs, PJ-02: 100 pcs, PJ-03: 50 pcs, PJ-04: 50 pcs
        $payload = [
            'tanggal' => now()->toDateString(),
            'gudang_operasional_id' => $this->gudangOp->id,
            'outputs' => [
                ['produk_id' => $pj1->id, 'qty_rencana' => 100],
                ['produk_id' => $pj2->id, 'qty_rencana' => 100],
                ['produk_id' => $pj3->id, 'qty_rencana' => 50],
                ['produk_id' => $pj4->id, 'qty_rencana' => 50],
            ],
        ];

        $response = $this->actingAs($this->operasional)->post(route('batches.store'), $payload);
        $response->assertRedirect();

        $batch = BatchProduksi::latest()->firstOrFail();
        $this->assertEquals(300, (float) $batch->qty_rencana);
        $this->assertCount(4, $batch->outputs);

        // 4. Verifikasi alokasi bahan diagregasi dengan tepat:
        // Alkohol: (100 * 40) + (100 * 40) + (50 * 35) + (50 * 35) = 4000 + 4000 + 1750 + 1750 = 11.500 ml
        // Botol: 100 + 100 + 50 + 50 = 300 pcs
        // Oil A: (100 * 10) + (50 * 15) = 1000 + 750 = 1.750 ml
        // Oil B: (100 * 10) + (50 * 15) = 1000 + 750 = 1.750 ml
        $alokasiMap = $batch->alokasi()->pluck('qty_dialokasikan', 'bahan_id');

        $this->assertEquals(11500, (float) $alokasiMap[$alkohol->id]);
        $this->assertEquals(300, (float) $alokasiMap[$botol->id]);
        $this->assertEquals(1750, (float) $alokasiMap[$oilA->id]);
        $this->assertEquals(1750, (float) $alokasiMap[$oilB->id]);

        // 5. Release batch
        $batch->update(['status' => 'release']);

        // 6. Complete batch dengan qty baik & rusak per produk
        $out1 = $batch->outputs()->where('produk_id', $pj1->id)->first();
        $out2 = $batch->outputs()->where('produk_id', $pj2->id)->first();
        $out3 = $batch->outputs()->where('produk_id', $pj3->id)->first();
        $out4 = $batch->outputs()->where('produk_id', $pj4->id)->first();

        $completePayload = [
            'outputs' => [
                ['id' => $out1->id, 'qty_baik' => 98, 'qty_rusak' => 2],
                ['id' => $out2->id, 'qty_baik' => 95, 'qty_rusak' => 5],
                ['id' => $out3->id, 'qty_baik' => 49, 'qty_rusak' => 1],
                ['id' => $out4->id, 'qty_baik' => 50, 'qty_rusak' => 0],
            ],
        ];

        $completeResponse = $this->actingAs($this->operasional)->post(route('batches.complete', $batch), $completePayload);
        $completeResponse->assertRedirect();

        $batch->refresh();
        $this->assertEquals('selesai', $batch->status);
        $this->assertEquals(292, (float) $batch->qty_baik);
        $this->assertEquals(8, (float) $batch->qty_rusak);

        $out1->refresh();
        $this->assertEquals(98, (float) $out1->qty_baik);
        $this->assertEquals(2, (float) $out1->qty_rusak);
    }

    /**
     * TC-41: Batch dan Request: Hubungkan 2 Request ke 1 batch.
     * Detail batch menampilkan kebutuhan, diminta, dan diterima per bahan.
     */
    public function test_tc41_batch_and_request_integration_progress_tracking(): void
    {
        $bahan = Produk::create(['sku' => 'RAW-MAT-41', 'nama' => 'Bahan Baku TC41', 'tipe' => 'bahan', 'satuan' => 'ml', 'is_active' => true]);
        $pj = Produk::create(['sku' => 'PJ-41', 'nama' => 'Produk Jadi TC41', 'tipe' => 'produk_jadi', 'satuan' => 'pcs', 'is_active' => true]);
        Bom::create(['produk_jadi_id' => $pj->id, 'bahan_id' => $bahan->id, 'qty_per_unit' => 10]);

        // Buat batch butuh 50 unit PJ = butuh 500 ml bahan
        $batch = BatchProduksi::create([
            'no_batch' => 'BATCH-TC41',
            'produk_id' => $pj->id,
            'qty_rencana' => 50,
            'gudang_operasional_id' => $this->gudangOp->id,
            'status' => 'rencana',
            'tanggal' => now()->toDateString(),
            'created_by' => $this->operasional->id,
        ]);
        $batch->outputs()->create(['produk_id' => $pj->id, 'qty_rencana' => 50]);
        $batch->alokasi()->create(['bahan_id' => $bahan->id, 'qty_dialokasikan' => 500, 'status' => 'aktif']);

        // Request 1: minta 300 ml, status dikirim / belum selesai
        $rt1 = RequestTransfer::create([
            'no_transaksi' => 'RT-001',
            'jenis' => 'req_bahan',
            'referensi_batch_id' => $batch->id,
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOp->id,
            'status' => 'dikirim',
            'created_by' => $this->operasional->id,
        ]);
        $rt1->items()->create(['produk_id' => $bahan->id, 'qty_diminta' => 300]);

        // Request 2: minta 200 ml, status selesai (sudah diterima)
        $rt2 = RequestTransfer::create([
            'no_transaksi' => 'RT-002',
            'jenis' => 'req_bahan',
            'referensi_batch_id' => $batch->id,
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOp->id,
            'status' => 'selesai',
            'created_by' => $this->operasional->id,
        ]);
        $rt2->items()->create(['produk_id' => $bahan->id, 'qty_diminta' => 200, 'qty_diterima' => 200, 'qty_baik' => 200]);

        // Panggil rekapBahan()
        $rekap = $batch->rekapBahan();
        $this->assertNotEmpty($rekap);

        $bahanRekap = collect($rekap)->firstWhere('bahan_id', $bahan->id);
        $this->assertNotNull($bahanRekap);
        $this->assertEquals(500, $bahanRekap['kebutuhan']); // Kebutuhan BOM
        $this->assertEquals(500, $bahanRekap['diminta']);   // 300 + 200 = 500 diminta
        $this->assertEquals(200, $bahanRekap['diterima']);  // 200 diterima dari RT2
    }
}

