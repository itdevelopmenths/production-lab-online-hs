<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Produk;
use App\Models\RequestTransfer;
use App\Models\Stok;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ISO/IEC 25010 Quality Assurance:
 * Request & Transfer Scenarios from Worst-Case to Best-Case.
 */
class RequestTransferFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Model::preventLazyLoading(true);
    }

    // ==========================================
    // 🌟 1. BEST-CASE SCENARIOS (GOLDEN PATHS)
    // ==========================================

    public function test_best_case_five_stage_request_pipeline_with_approval(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $gudangBahan = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        $stokService = app(StokService::class);
        $initialBahanStock = $stokService->saldo($bahan->id, $gudangBahan->id);
        $initialOpStock = $stokService->saldo($bahan->id, $gudangOp->id);
        $this->assertGreaterThanOrEqual(1000, $initialBahanStock);

        // 1. Operasional creates Request Bahan (status: draft)
        $this->actingAs($operasional);
        $qtyDiminta = 500;

        $response = $this->post(route('rt.store'), [
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangBahan->id,
            'gudang_tujuan_id' => $gudangOp->id,
            'catatan' => 'Permintaan alkohol untuk batch GOH-001',
            'items' => [
                ['produk_id' => $bahan->id, 'qty_diminta' => $qtyDiminta],
            ],
        ]);

        $rt = RequestTransfer::latest()->firstOrFail();
        $response->assertRedirect(route('rt.show', $rt));
        $this->assertEquals('draft', $rt->status);
        $this->assertTrue($rt->pakaiApproval());

        // 2. Operasional submits request (status: diajukan)
        $this->post(route('rt.transition', $rt), ['aksi' => 'submit'])->assertSessionHas('success');
        $rt->refresh();
        $this->assertEquals('diajukan', $rt->status);

        // 3. Manager approves request (status: disetujui)
        $this->actingAs($manager);
        $this->post(route('rt.transition', $rt), ['aksi' => 'approve'])->assertSessionHas('success');
        $rt->refresh();
        $this->assertEquals('disetujui', $rt->status);

        // 4. Gudang processes goods (status: diproses) -> stock leaves Gudang Bahan
        $this->actingAs($gudangUser);
        $rtItem = $rt->items->first();

        $this->post(route('rt.transition', $rt), [
            'aksi' => 'process',
            'items' => [
                ['id' => $rtItem->id, 'qty' => $qtyDiminta],
            ],
        ])->assertSessionHas('success');

        $rt->refresh();
        $this->assertEquals('diproses', $rt->status);

        // Verify stock out from source
        $this->assertEquals($initialBahanStock - $qtyDiminta, $stokService->saldo($bahan->id, $gudangBahan->id));
        $this->assertEquals($initialOpStock, $stokService->saldo($bahan->id, $gudangOp->id));

        $kartuOut = KartuStok::where('produk_id', $bahan->id)
            ->where('gudang_id', $gudangBahan->id)
            ->where('referensi_tipe', 'request_transfer')
            ->where('referensi_id', $rt->id)
            ->where('tipe', 'out')
            ->first();
        $this->assertNotNull($kartuOut);
        $this->assertEquals($qtyDiminta, (float) $kartuOut->qty);

        // 5. Operasional receives goods (status: selesai) -> stock enters Gudang Ops
        $this->actingAs($operasional);
        $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $rtItem->id, 'qty' => $qtyDiminta],
            ],
        ])->assertSessionHas('success');

        $rt->refresh();
        $this->assertEquals('selesai', $rt->status);

        // Verify stock in at destination
        $this->assertEquals($initialOpStock + $qtyDiminta, $stokService->saldo($bahan->id, $gudangOp->id));

        $kartuIn = KartuStok::where('produk_id', $bahan->id)
            ->where('gudang_id', $gudangOp->id)
            ->where('referensi_tipe', 'request_transfer')
            ->where('referensi_id', $rt->id)
            ->where('tipe', 'in')
            ->first();
        $this->assertNotNull($kartuIn);
        $this->assertEquals($qtyDiminta, (float) $kartuIn->qty);
    }

    public function test_best_case_three_stage_direct_transfer_pipeline_without_approval(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $fulfillment = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();

        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $produk = Produk::produkJadi()->firstOrFail();

        $stokService = app(StokService::class);
        $stokService->masuk($produk->id, $gudangOp->id, 200, 'mutasi_manual', null, 'Persiapan stok');

        $initialOpStock = $stokService->saldo($produk->id, $gudangOp->id);
        $initialFfStock = $stokService->saldo($produk->id, $gudangFf->id);
        $qtyKirim = 100;

        // 1. Create direct transfer document (status: draft)
        $this->actingAs($operasional);
        $response = $this->post(route('rt.store'), [
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => $qtyKirim],
            ],
        ]);

        $rt = RequestTransfer::latest()->firstOrFail();
        $this->assertEquals('draft', $rt->status);
        $this->assertFalse($rt->pakaiApproval());

        // 2. Ship directly without approval (status: dikirim)
        $rtItem = $rt->items->first();
        $this->post(route('rt.transition', $rt), [
            'aksi' => 'ship',
            'items' => [
                ['id' => $rtItem->id, 'qty' => $qtyKirim],
            ],
        ])->assertSessionHas('success');

        $rt->refresh();
        $this->assertEquals('dikirim', $rt->status);
        $this->assertEquals($initialOpStock - $qtyKirim, $stokService->saldo($produk->id, $gudangOp->id));
        $this->assertEquals($initialFfStock, $stokService->saldo($produk->id, $gudangFf->id));

        // 3. Destination receives (status: selesai)
        $this->actingAs($fulfillment);
        $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $rtItem->id, 'qty' => $qtyKirim],
            ],
        ])->assertSessionHas('success');

        $rt->refresh();
        $this->assertEquals('selesai', $rt->status);
        $this->assertEquals($initialFfStock + $qtyKirim, $stokService->saldo($produk->id, $gudangFf->id));
    }

    // ==========================================
    // 🔍 2. BOUNDARY / EDGE-CASE SCENARIOS
    // ==========================================

    public function test_boundary_case_discrepancy_between_sent_and_received_quantities(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $fulfillment = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();

        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $produk = Produk::produkJadi()->firstOrFail();

        $stokService = app(StokService::class);
        $stokService->masuk($produk->id, $gudangOp->id, 500, 'mutasi_manual');

        $this->actingAs($operasional);
        $this->post(route('rt.store'), [
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 100],
            ],
        ]);
        $rt = RequestTransfer::with('items')->latest()->firstOrFail();
        $rtItem = $rt->items->first();

        // Sender actually sends 95 (5 pieces missing/held)
        $this->post(route('rt.transition', $rt), [
            'aksi' => 'ship',
            'items' => [
                ['id' => $rtItem->id, 'qty' => 95],
            ],
        ]);
        $rt->refresh();
        $this->assertEquals(95, (float) $rt->items->first()->qty_dikirim);

        // Receiver receives 93 (2 pieces broken in transit)
        $this->actingAs($fulfillment);
        $this->post(route('rt.transition', $rt), [
            'aksi' => 'receive',
            'items' => [
                ['id' => $rtItem->id, 'qty' => 93],
            ],
        ]);
        $rt->refresh();
        $this->assertEquals('selesai', $rt->status);
        $this->assertEquals(93, (float) $rt->items->first()->qty_diterima);
        $this->assertEquals(93, $stokService->saldo($produk->id, $gudangFf->id));
    }

    // ==========================================
    // ⚠️ 3. WORST-CASE / NEGATIVE SCENARIOS
    // ==========================================

    public function test_worst_case_zero_or_negative_request_quantity_rejected(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $gudangBahan = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        $response = $this->post(route('rt.store'), [
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangBahan->id,
            'gudang_tujuan_id' => $gudangOp->id,
            'items' => [
                ['produk_id' => $bahan->id, 'qty_diminta' => 0],
            ],
        ]);
        $response->assertSessionHasErrors('items.0.qty_diminta');
    }

    public function test_worst_case_duplicate_items_in_request_rejected(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $gudangBahan = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        $response = $this->post(route('rt.store'), [
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangBahan->id,
            'gudang_tujuan_id' => $gudangOp->id,
            'items' => [
                ['produk_id' => $bahan->id, 'qty_diminta' => 10],
                ['produk_id' => $bahan->id, 'qty_diminta' => 20],
            ],
        ]);
        $response->assertSessionHasErrors('items.0.produk_id');
    }

    public function test_worst_case_insufficient_stock_at_origin_warehouse(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $produk = Produk::produkJadi()->firstOrFail();

        // Origin warehouse has 0 stock of this product
        $this->post(route('rt.store'), [
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 500],
            ],
        ]);
        $rt = RequestTransfer::with('items')->latest()->firstOrFail();

        // Trying to ship should gracefully fail with error
        $response = $this->post(route('rt.transition', $rt), [
            'aksi' => 'ship',
            'items' => [
                ['id' => $rt->items->first()->id, 'qty' => 500],
            ],
        ]);
        $response->assertSessionHas('error');
        $rt->refresh();
        $this->assertEquals('draft', $rt->status);
    }

    public function test_worst_case_invalid_cross_pipeline_actions_abort(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $gudangBahan = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        $this->actingAs($operasional);

        // Create Request Bahan (5-stage approval pipeline)
        $this->post(route('rt.store'), [
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangBahan->id,
            'gudang_tujuan_id' => $gudangOp->id,
            'items' => [
                ['produk_id' => $bahan->id, 'qty_diminta' => 10],
            ],
        ]);
        $rt = RequestTransfer::with('items')->latest()->firstOrFail();

        // Cannot call 'ship' directly on a request requiring approval -> caught and redirected back with error
        $response = $this->post(route('rt.transition', $rt), [
            'aksi' => 'ship',
            'items' => [['id' => $rt->items->first()->id, 'qty' => 10]],
        ]);

        $response->assertSessionHas('error');
    }

    public function test_worst_case_cannot_cancel_already_completed_transfer(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();

        $rt = RequestTransfer::create([
            'no_transaksi' => 'TR-FINAL-01',
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'status' => 'selesai',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager);
        $response = $this->post(route('rt.transition', $rt), ['aksi' => 'cancel']);
        $response->assertSessionHas('error');

        $rt->refresh();
        $this->assertEquals('selesai', $rt->status);
    }

    // ==========================================
    // 🛡️ 4. LAZY LOADING IMMUNITY & RENDERING
    // ==========================================

    public function test_cancellation_of_draft_transfer(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $produk = Produk::produkJadi()->firstOrFail();

        $this->actingAs($operasional);
        $this->post(route('rt.store'), [
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangOp->id,
            'gudang_tujuan_id' => $gudangFf->id,
            'items' => [
                ['produk_id' => $produk->id, 'qty_diminta' => 50],
            ],
        ]);

        $rt = RequestTransfer::latest()->firstOrFail();

        // Manager has rt.cancel permission to cancel transfer
        $this->actingAs($manager);
        $this->post(route('rt.transition', $rt), ['aksi' => 'cancel'])->assertSessionHas('success');

        $rt->refresh();
        $this->assertEquals('dibatalkan', $rt->status);
    }

    public function test_request_transfer_show_displays_critical_warning_when_origin_stock_is_insufficient(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $gudangBahan = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        // Permintaan 999.999 unit yang melebihi saldo fisik riil di gudang asal
        $this->post(route('rt.store'), [
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangBahan->id,
            'gudang_tujuan_id' => $gudangOp->id,
            'items' => [
                ['produk_id' => $bahan->id, 'qty_diminta' => 999999],
            ],
        ]);

        $rt = RequestTransfer::latest()->firstOrFail();

        $response = $this->get(route('rt.show', $rt));
        $response->assertOk();
        $response->assertSee('Stok Asal Kurang');
        $response->assertSee('Peringatan Kritis');
        $response->assertSee('Kurang');
    }

    public function test_request_transfer_show_displays_ready_status_when_origin_stock_is_sufficient(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $gudangBahan = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        // Permintaan 5 unit kecil yang pasti tersedia di gudang pusat
        $this->post(route('rt.store'), [
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangBahan->id,
            'gudang_tujuan_id' => $gudangOp->id,
            'items' => [
                ['produk_id' => $bahan->id, 'qty_diminta' => 5],
            ],
        ]);

        $rt = RequestTransfer::latest()->firstOrFail();

        $response = $this->get(route('rt.show', $rt));
        $response->assertOk();
        $response->assertSee('Stok Asal Cukup');
        $response->assertSee('Tersedia');
    }

    public function test_idempotent_transition_handles_concurrent_or_double_submissions_gracefully(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudangBahan = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        // Buat request transfer status draft
        $rt = RequestTransfer::create([
            'no_transaksi' => 'TR-IDEMP-01',
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangBahan->id,
            'gudang_tujuan_id' => $gudangOp->id,
            'status' => 'draft',
            'created_by' => $manager->id,
        ]);

        $rt->items()->create([
            'produk_id' => $bahan->id,
            'qty_diminta' => 10,
        ]);

        $this->actingAs($manager);

        // 1. Submit pertama: draft -> diajukan
        $res1 = $this->post(route('rt.transition', $rt), ['aksi' => 'submit']);
        $res1->assertSessionHas('success');
        $this->assertEquals('diajukan', $rt->fresh()->status);

        // 2. Submit kedua (simulasi double-click): aksi submit dikirim lagi saat status sudah diajukan
        // Sistem tidak boleh melempar error "Transisi tidak valid", melainkan merespon sukses secara idempoten
        $res2 = $this->post(route('rt.transition', $rt), ['aksi' => 'submit']);
        $res2->assertSessionHas('success');
        $res2->assertSessionMissing('error');
        $this->assertEquals('diajukan', $rt->fresh()->status);

        // 3. Approve pertama: diajukan -> disetujui
        $resApprove1 = $this->post(route('rt.transition', $rt), ['aksi' => 'approve']);
        $resApprove1->assertSessionHas('success');
        $this->assertEquals('disetujui', $rt->fresh()->status);

        // 4. Approve kedua (simulasi double-click):
        $resApprove2 = $this->post(route('rt.transition', $rt), ['aksi' => 'approve']);
        $resApprove2->assertSessionHas('success');
        $resApprove2->assertSessionMissing('error');
        $this->assertEquals('disetujui', $rt->fresh()->status);
    }

    public function test_create_view_auto_fills_shortage_from_batch(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();

        $produkJadi = Produk::where('tipe', 'produk_jadi')->firstOrFail();
        $bahanA = Produk::where('tipe', 'bahan')->firstOrFail();
        $bahanB = Produk::where('tipe', 'bahan')->where('id', '!=', $bahanA->id)->firstOrFail();

        // Setup batch produksi dengan alokasi 2 bahan
        $batch = BatchProduksi::create([
            'no_batch' => 'BATCH-TEST-SHORTAGE',
            'produk_id' => $produkJadi->id,
            'qty_rencana' => 100,
            'gudang_operasional_id' => $gudangOp->id,
            'status' => 'rencana',
            'tanggal' => now(),
            'created_by' => $operasional->id,
        ]);

        // Bahan A: dialokasikan 50, stok fisik di gd ops = 10 -> KURANG 40
        $batch->alokasi()->create([
            'bahan_id' => $bahanA->id,
            'qty_dialokasikan' => 50,
            'status' => 'aktif',
        ]);
        Stok::updateOrCreate(
            ['gudang_id' => $gudangOp->id, 'produk_id' => $bahanA->id],
            ['qty_saat_ini' => 10]
        );

        // Bahan B: dialokasikan 20, stok fisik di gd ops = 30 -> CUKUP (kurang 0)
        $batch->alokasi()->create([
            'bahan_id' => $bahanB->id,
            'qty_dialokasikan' => 20,
            'status' => 'aktif',
        ]);
        Stok::updateOrCreate(
            ['gudang_id' => $gudangOp->id, 'produk_id' => $bahanB->id],
            ['qty_saat_ini' => 30]
        );

        $this->actingAs($operasional);

        // 1. Akses halaman create dengan query parameter batch_id
        $response = $this->get(route('rt.create', ['batch_id' => $batch->id]));

        $response->assertOk();
        $response->assertViewHas('prefilledRows');
        $response->assertViewHas('defaultGudangTujuanId', $gudangOp->id);
        $response->assertViewHas('defaultGudangAsalId', $gudangPusat->id);

        $prefilled = $response->viewData('prefilledRows');
        // Hanya Bahan A yang kurang yang masuk ke prefilled
        $this->assertCount(1, $prefilled);
        $this->assertEquals($bahanA->id, $prefilled[0]['produk_id']);
        $this->assertEquals(40, $prefilled[0]['qty']); // 50 - 10 = 40
        $this->assertEquals($bahanA->satuan, $prefilled[0]['satuan']);
        $this->assertEquals($bahanA->nama, $prefilled[0]['selectedItem']['nama']);

        // 2. Akses halaman create tanpa batch_id -> prefilledRows harus kosong
        $responseNormal = $this->get(route('rt.create'));
        $responseNormal->assertOk();
        $this->assertEmpty($responseNormal->viewData('prefilledRows'));
    }
}
