<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Produk;
use App\Models\RequestTransfer;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ISO/IEC 25010 Quality Assurance:
 * Production & Batch Flow Scenarios from Worst-Case to Best-Case.
 */
class ProductionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Model::preventLazyLoading(true);
    }

    // ==========================================
    // 🌟 1. BEST-CASE SCENARIO (GOLDEN PATH)
    // ==========================================

    public function test_best_case_full_production_lifecycle_plan_release_complete_and_ship(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $produk = Produk::produkJadi()->with('bom.bahan')->firstOrFail();
        $this->assertNotEmpty($produk->bom);

        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();

        $stokService = app(StokService::class);

        // Pre-fill operational warehouse with raw materials
        foreach ($produk->bom as $bomItem) {
            $stokService->masuk(
                $bomItem->bahan_id,
                $gudangOp->id,
                10000,
                'mutasi_manual',
                null,
                'Persiapan bahan operasional'
            );
        }

        // 1. Create Batch Plan (Status: rencana)
        $qtyRencana = 100;
        $response = $this->post(route('batches.store'), [
            'produk_id' => $produk->id,
            'qty_rencana' => $qtyRencana,
            'gudang_operasional_id' => $gudangOp->id,
            'gudang_tujuan_rencana_id' => $gudangFf->id,
            'tanggal' => now()->toDateString(),
        ]);

        $batch = BatchProduksi::latest()->firstOrFail();
        $response->assertRedirect(route('batches.show', $batch));
        $this->assertEquals('rencana', $batch->status);

        // Verify BOM auto-explosion into BatchAlokasiBahan (aktif) without physical stock deductions
        $this->assertCount($produk->bom->count(), $batch->alokasi);
        foreach ($batch->alokasi as $alok) {
            $this->assertEquals('aktif', $alok->status);
            $bomRow = $produk->bom->where('bahan_id', $alok->bahan_id)->first();
            $this->assertEquals((float) $bomRow->qty_per_unit * $qtyRencana, (float) $alok->qty_dialokasikan);
            $this->assertEquals(10000, $stokService->saldo($alok->bahan_id, $gudangOp->id));
        }

        // 2. Release & Issue
        $this->post(route('batches.release', $batch))->assertSessionHas('success');
        $batch->refresh();
        $this->assertEquals('release', $batch->status);

        // Verify physical stock deduction and alokasi 'dilepas'
        foreach ($batch->alokasi as $alok) {
            $alok->refresh();
            $this->assertEquals('dilepas', $alok->status);
            $expectedStock = 10000 - (float) $alok->qty_dialokasikan;
            $this->assertEquals($expectedStock, $stokService->saldo($alok->bahan_id, $gudangOp->id));

            $kartu = KartuStok::where('produk_id', $alok->bahan_id)
                ->where('gudang_id', $gudangOp->id)
                ->where('referensi_tipe', 'batch_produksi')
                ->where('referensi_id', $batch->id)
                ->where('tipe', 'out')
                ->first();
            $this->assertNotNull($kartu);
            $this->assertEquals((float) $alok->qty_dialokasikan, (float) $kartu->qty);
        }

        // 3. Complete Batch (Hasil Produksi)
        $initialFgStock = $stokService->saldo($produk->id, $gudangOp->id);
        $qtyBaik = 98;
        $qtyRusak = 2;

        $this->post(route('batches.complete', $batch), [
            'qty_baik' => $qtyBaik,
            'qty_rusak' => $qtyRusak,
        ])->assertSessionHas('success');

        $batch->refresh();
        $this->assertEquals('selesai', $batch->status);
        $this->assertEquals($qtyBaik, (float) $batch->qty_baik);
        $this->assertEquals($qtyRusak, (float) $batch->qty_rusak);
        $this->assertEquals(98.0, $batch->yield());
        $this->assertEquals(2.0, $batch->defectRate());

        // Finished goods deposited into operational warehouse stock
        $newFgStock = $stokService->saldo($produk->id, $gudangOp->id);
        $this->assertEquals($initialFgStock + $qtyBaik, $newFgStock);

        // 4. Stock Opname
        $firstBahan = $batch->alokasi->first();
        $this->post(route('batches.opname', $batch), [
            'items' => [
                [
                    'bahan_id' => $firstBahan->bahan_id,
                    'pemakaian_aktual' => (float) $firstBahan->qty_dialokasikan + 5,
                    'keterangan' => 'Tumpah sedikit saat mixing',
                ],
            ],
        ])->assertSessionHas('success');

        $this->assertCount(1, $batch->opname);
        $this->assertEquals((float) $firstBahan->qty_dialokasikan, (float) $batch->opname->first()->pemakaian_teoritis);
        $this->assertEquals((float) $firstBahan->qty_dialokasikan + 5, (float) $batch->opname->first()->pemakaian_aktual);

        // 5. Send Finished Goods to Fulfillment
        $this->post(route('batches.kirim', $batch))->assertSessionHas('success');
        $rt = RequestTransfer::where('referensi_batch_id', $batch->id)->first();
        $this->assertNotNull($rt);
        $this->assertEquals('kirim_produk_jadi', $rt->jenis);
        $this->assertEquals('draft', $rt->status);
        $this->assertEquals($qtyBaik, (float) $rt->items->first()->qty_diminta);
    }

    // ==========================================
    // 🔍 2. BOUNDARY / EDGE-CASE SCENARIOS
    // ==========================================

    public function test_boundary_case_zero_yield_and_hundred_percent_defect(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $produk = Produk::produkJadi()->with('bom.bahan')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $stokService = app(StokService::class);

        foreach ($produk->bom as $bomItem) {
            $stokService->masuk($bomItem->bahan_id, $gudangOp->id, 5000, 'mutasi_manual');
        }

        $this->post(route('batches.store'), [
            'produk_id' => $produk->id,
            'qty_rencana' => 50,
            'gudang_operasional_id' => $gudangOp->id,
            'tanggal' => now()->toDateString(),
        ]);
        $batch = BatchProduksi::latest()->firstOrFail();

        $this->post(route('batches.release', $batch));

        $initialFgStock = $stokService->saldo($produk->id, $gudangOp->id);

        // 100% Defect (e.g. contamination during bottling)
        $this->post(route('batches.complete', $batch), [
            'qty_baik' => 0,
            'qty_rusak' => 50,
        ])->assertSessionHas('success');

        $batch->refresh();
        $this->assertEquals('selesai', $batch->status);
        $this->assertEquals(0, (float) $batch->qty_baik);
        $this->assertEquals(50, (float) $batch->qty_rusak);
        $this->assertEquals(0.0, $batch->yield());
        $this->assertEquals(100.0, $batch->defectRate());

        // Zero items added to finished stock
        $this->assertEquals($initialFgStock, $stokService->saldo($produk->id, $gudangOp->id));

        // Cannot ship zero good products
        $response = $this->post(route('batches.kirim', $batch));
        $response->assertStatus(422);
    }

    public function test_boundary_case_hundred_percent_yield_perfect_batch(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $produk = Produk::produkJadi()->with('bom.bahan')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $stokService = app(StokService::class);

        foreach ($produk->bom as $bomItem) {
            $stokService->masuk($bomItem->bahan_id, $gudangOp->id, 5000, 'mutasi_manual');
        }

        $this->post(route('batches.store'), [
            'produk_id' => $produk->id,
            'qty_rencana' => 25,
            'gudang_operasional_id' => $gudangOp->id,
            'tanggal' => now()->toDateString(),
        ]);
        $batch = BatchProduksi::latest()->firstOrFail();

        $this->post(route('batches.release', $batch));

        // 100% Good
        $this->post(route('batches.complete', $batch), [
            'qty_baik' => 25,
            'qty_rusak' => 0,
        ])->assertSessionHas('success');

        $batch->refresh();
        $this->assertEquals(100.0, $batch->yield());
        $this->assertEquals(0.0, $batch->defectRate());
    }

    // ==========================================
    // ⚠️ 3. WORST-CASE / NEGATIVE SCENARIOS
    // ==========================================

    public function test_worst_case_zero_or_negative_plan_qty_rejected(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $produk = Produk::produkJadi()->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();

        // Qty 0
        $response = $this->post(route('batches.store'), [
            'produk_id' => $produk->id,
            'qty_rencana' => 0,
            'gudang_operasional_id' => $gudangOp->id,
            'tanggal' => now()->toDateString(),
        ]);
        $response->assertSessionHasErrors('qty_rencana');

        // Negative Qty
        $response2 = $this->post(route('batches.store'), [
            'produk_id' => $produk->id,
            'qty_rencana' => -10,
            'gudang_operasional_id' => $gudangOp->id,
            'tanggal' => now()->toDateString(),
        ]);
        $response2->assertSessionHasErrors('qty_rencana');
    }

    public function test_worst_case_batch_release_fails_if_stock_is_insufficient(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $produk = Produk::produkJadi()->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();

        $this->post(route('batches.store'), [
            'produk_id' => $produk->id,
            'qty_rencana' => 500,
            'gudang_operasional_id' => $gudangOp->id,
            'tanggal' => now()->toDateString(),
        ]);

        $batch = BatchProduksi::latest()->firstOrFail();

        // Operasional has 0 stock of ingredients, release should flash error gracefully
        $response = $this->post(route('batches.release', $batch));
        $response->assertSessionHas('error');

        $batch->refresh();
        $this->assertEquals('rencana', $batch->status);
    }

    public function test_worst_case_illegal_lifecycle_transitions_abort(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $produk = Produk::produkJadi()->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();

        $this->post(route('batches.store'), [
            'produk_id' => $produk->id,
            'qty_rencana' => 10,
            'gudang_operasional_id' => $gudangOp->id,
            'tanggal' => now()->toDateString(),
        ]);
        $batch = BatchProduksi::latest()->firstOrFail();

        // 1. Cannot complete a batch while still in rencana
        $response = $this->post(route('batches.complete', $batch), [
            'qty_baik' => 10,
            'qty_rusak' => 0,
        ]);
        $response->assertStatus(422);

        // 2. Cannot ship a batch before completion
        $response2 = $this->post(route('batches.kirim', $batch));
        $response2->assertStatus(422);
    }

    public function test_worst_case_cannot_cancel_already_released_batch(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $produk = Produk::produkJadi()->with('bom.bahan')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $stokService = app(StokService::class);

        foreach ($produk->bom as $b) {
            $stokService->masuk($b->bahan_id, $gudangOp->id, 5000, 'mutasi_manual');
        }

        $this->post(route('batches.store'), [
            'produk_id' => $produk->id,
            'qty_rencana' => 10,
            'gudang_operasional_id' => $gudangOp->id,
            'tanggal' => now()->toDateString(),
        ]);
        $batch = BatchProduksi::latest()->firstOrFail();

        // Release batch
        $this->post(route('batches.release', $batch));
        $batch->refresh();
        $this->assertEquals('release', $batch->status);

        // Attempt to cancel released batch -> must abort with 422
        $response = $this->post(route('batches.cancel', $batch));
        $response->assertStatus(422);

        $batch->refresh();
        $this->assertEquals('release', $batch->status);
    }

    // ==========================================
    // 🛡️ 4. LAZY LOADING IMMUNITY & RENDERING
    // ==========================================

    public function test_batch_cancellation_releases_allocation_without_stock_movement(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $produk = Produk::produkJadi()->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $stokService = app(StokService::class);

        $this->post(route('batches.store'), [
            'produk_id' => $produk->id,
            'qty_rencana' => 50,
            'gudang_operasional_id' => $gudangOp->id,
            'tanggal' => now()->toDateString(),
        ]);

        $batch = BatchProduksi::latest()->firstOrFail();
        $this->assertEquals('rencana', $batch->status);
        $this->assertGreaterThan(0, $batch->alokasi()->where('status', 'aktif')->count());

        $alokBahanId = $batch->alokasi->first()->bahan_id;
        $stockBefore = $stokService->saldo($alokBahanId, $gudangOp->id);

        // Cancel Batch
        $this->post(route('batches.cancel', $batch))->assertSessionHas('success');

        $batch->refresh();
        $this->assertEquals('dibatalkan', $batch->status);
        $this->assertEquals(0, $batch->alokasi()->where('status', 'aktif')->count());
        $this->assertEquals($batch->alokasi()->count(), $batch->alokasi()->where('status', 'dibatalkan')->count());

        // Stock was never modified
        $stockAfter = $stokService->saldo($alokBahanId, $gudangOp->id);
        $this->assertEquals($stockBefore, $stockAfter);
    }

    public function test_batch_show_displays_early_warning_signals_when_operational_stock_is_insufficient(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $produk = Produk::produkJadi()->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();

        // Buat batch rencana tanpa mengisi stok bahan baku di gudang operasional (stok 0)
        $this->post(route('batches.store'), [
            'produk_id' => $produk->id,
            'qty_rencana' => 500,
            'gudang_operasional_id' => $gudangOp->id,
            'tanggal' => now()->toDateString(),
        ]);

        $batch = BatchProduksi::latest()->firstOrFail();
        $this->assertEquals('rencana', $batch->status);

        // Buka halaman detail batch
        $response = $this->get(route('batches.show', $batch));
        $response->assertOk();
        $response->assertSee('Stok Kurang');
        $response->assertSee('Kurang');
        $response->assertSee('Buat Request Bahan');
    }

    public function test_batch_show_displays_safe_status_when_operational_stock_is_sufficient(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $produk = Produk::produkJadi()->with('bom')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $stokService = app(StokService::class);

        // Buat batch rencana
        $this->post(route('batches.store'), [
            'produk_id' => $produk->id,
            'qty_rencana' => 10,
            'gudang_operasional_id' => $gudangOp->id,
            'tanggal' => now()->toDateString(),
        ]);

        $batch = BatchProduksi::latest()->firstOrFail();

        // Isi stok bahan baku secara berlimpah di gudang operasional
        foreach ($batch->alokasi as $alok) {
            $stokService->masuk(
                $alok->bahan_id,
                $gudangOp->id,
                100000,
                'mutasi_manual',
                null,
                'Injeksi stok melimpah untuk tes ketersediaan aman'
            );
        }

        // Buka halaman detail batch
        $response = $this->get(route('batches.show', $batch));
        $response->assertOk();
        $response->assertSee('Siap Rilis');
        $response->assertSee('Cukup');
    }
}
