<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\Bom;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiOutputBatchProductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_multi_output_batch_creation_aggregates_bom_and_produces_multiple_stocks(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();

        // Siapkan 2 produk jadi berbeda
        $produk1 = Produk::where('sku', 'GOH-P50')->firstOrFail(); // Parfum GOH 50ml
        $produk2 = Produk::firstOrCreate(
            ['sku' => 'GOH-P30'],
            [
                'nama' => 'Parfum GOH 30ml',
                'tipe' => 'produk_jadi',
                'satuan' => 'pcs',
                'satuan_order_moq' => 12,
                'status' => 'aktif',
            ]
        );

        $alk = Produk::where('sku', 'ALK-01')->firstOrFail();
        $oil = Produk::where('sku', 'OIL-GOH')->firstOrFail();

        // BOM untuk produk2: 24ml alkohol, 5ml oil
        Bom::firstOrCreate(['produk_jadi_id' => $produk2->id, 'bahan_id' => $alk->id], ['qty_per_unit' => 24]);
        Bom::firstOrCreate(['produk_jadi_id' => $produk2->id, 'bahan_id' => $oil->id], ['qty_per_unit' => 5]);

        // Beri stok bahan melimpah di Gudang Operasional
        $stokService = app(StokService::class);
        $stokService->masuk($alk->id, $gudangOp->id, 50000, 'mutasi_manual', null, 'Stok op alkohol');
        $stokService->masuk($oil->id, $gudangOp->id, 20000, 'mutasi_manual', null, 'Stok op oil');
        $btl = Produk::where('sku', 'BTL-P50')->first();
        if ($btl) {
            $stokService->masuk($btl->id, $gudangOp->id, 5000, 'mutasi_manual', null, 'Stok op btl');
        }
        $cap = Produk::where('sku', 'CAP-01')->first();
        if ($cap) {
            $stokService->masuk($cap->id, $gudangOp->id, 5000, 'mutasi_manual', null, 'Stok op cap');
        }

        // 1. Buat Batch Multi-Output
        // Output 1: 100 pcs GOH 50ml (BOM: alk 40*100=4000, oil 8*100=800, btl 100, cap 100)
        // Output 2: 200 pcs GOH 30ml (BOM: alk 24*200=4800, oil 5*200=1000)
        // Total Alokasi: alk = 8800, oil = 1800
        $this->actingAs($operasional);
        $response = $this->post(route('batches.store'), [
            'gudang_operasional_id' => $gudangOp->id,
            'gudang_tujuan_rencana_id' => $gudangFf->id,
            'tanggal' => now()->toDateString(),
            'outputs' => [
                ['produk_id' => $produk1->id, 'qty_rencana' => 100],
                ['produk_id' => $produk2->id, 'qty_rencana' => 200],
            ],
        ]);

        $batch = BatchProduksi::latest()->firstOrFail();
        $response->assertRedirect(route('batches.show', $batch));

        $this->assertEquals('rencana', $batch->status);
        $this->assertEquals(300, (float) $batch->qty_rencana);
        $this->assertCount(2, $batch->outputs);

        // Cek alokasi gabungan BOM
        $alokAlk = $batch->alokasi()->where('bahan_id', $alk->id)->first();
        $this->assertNotNull($alokAlk);
        $this->assertEquals(8800, (float) $alokAlk->qty_dialokasikan);

        $alokOil = $batch->alokasi()->where('bahan_id', $oil->id)->first();
        $this->assertNotNull($alokOil);
        $this->assertEquals(1800, (float) $alokOil->qty_dialokasikan);

        // 2. Release & Issue
        $this->post(route('batches.release', $batch))->assertSessionHas('success');
        $batch->refresh();
        $this->assertEquals('release', $batch->status);
        $this->assertEquals(50000 - 8800, $stokService->saldo($alk->id, $gudangOp->id));
        $this->assertEquals(20000 - 1800, $stokService->saldo($oil->id, $gudangOp->id));

        // 3. Selesaikan Batch Multi-Output dengan Qty Baik & Rusak
        $out1 = $batch->outputs->where('produk_id', $produk1->id)->first();
        $out2 = $batch->outputs->where('produk_id', $produk2->id)->first();

        $this->post(route('batches.complete', $batch), [
            'outputs' => [
                ['id' => $out1->id, 'qty_baik' => 98, 'qty_rusak' => 2],
                ['id' => $out2->id, 'qty_baik' => 195, 'qty_rusak' => 5],
            ],
        ])->assertSessionHas('success');

        $batch->refresh();
        $out1->refresh();
        $out2->refresh();

        $this->assertEquals('selesai', $batch->status);
        $this->assertEquals(293, (float) $batch->qty_baik);
        $this->assertEquals(7, (float) $batch->qty_rusak);
        $this->assertEquals(98, (float) $out1->qty_baik);
        $this->assertEquals(2, (float) $out1->qty_rusak);
        $this->assertEquals(195, (float) $out2->qty_baik);
        $this->assertEquals(5, (float) $out2->qty_rusak);

        // Pastikan stok fisik masing-masing produk jadi bertambah di gudang operasional
        $this->assertEquals(98, $stokService->saldo($produk1->id, $gudangOp->id));
        $this->assertEquals(195, $stokService->saldo($produk2->id, $gudangOp->id));

        // 4. DataTables API Test
        $dataRes = $this->getJson(route('batches.data'));
        $dataRes->assertOk();
        $json = $dataRes->json();
        $row = collect($json['data'])->firstWhere('no_batch', $batch->no_batch);
        $this->assertNotNull($row);
        $this->assertStringContainsString('Parfum GOH 50ml', $row['produk_nama']);
        $this->assertStringContainsString('Parfum GOH 30ml', $row['produk_nama']);
        $this->assertEquals('300', $row['qty_rencana']);
        $this->assertEquals('293', $row['qty_baik']);
        $this->assertEquals('7', $row['qty_rusak']);
        $this->assertStringContainsString('97.7%', $row['yield']); // 293 / 300 = 97.666%
    }
}
