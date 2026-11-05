<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\StockRequest;
use App\Models\Transfer;
use App\Models\User;
use App\Services\RequestService;
use App\Services\StokService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RequestAndTransferSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $operasional;
    protected User $fulfillment;
    protected User $gudangUser;
    protected Gudang $gudangPusat;
    protected Gudang $gudangOps;
    protected Gudang $gudangFf;
    protected Produk $bahan;
    protected Produk $produkJadi;
    protected RequestService $reqService;
    protected TransferService $trfService;
    protected StokService $stokService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->fulfillment = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();
        $this->gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $this->gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $this->gudangOps = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $this->gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();

        $this->bahan = Produk::bahan()->active()->firstOrFail();
        $this->produkJadi = Produk::produkJadi()->active()->firstOrFail();

        $this->reqService = app(RequestService::class);
        $this->trfService = app(TransferService::class);
        $this->stokService = app(StokService::class);
    }

    /**
     * TC-18: Request dibuat tanpa memotong stok (stok asal & tujuan tetap utuh).
     */
    public function test_tc18_request_creation_does_not_affect_stock(): void
    {
        $stokAsalAwal = $this->stokService->saldo($this->bahan->id, $this->gudangPusat->id);
        $stokTujuanAwal = $this->stokService->saldo($this->bahan->id, $this->gudangOps->id);

        $request = $this->reqService->create([
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'status' => 'draft',
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_diminta' => 50],
            ],
        ], $this->operasional);

        $this->assertEquals('draft', $request->status);
        $this->assertEquals($stokAsalAwal, $this->stokService->saldo($this->bahan->id, $this->gudangPusat->id));
        $this->assertEquals($stokTujuanAwal, $this->stokService->saldo($this->bahan->id, $this->gudangOps->id));
    }

    /**
     * TC-19: Persetujuan dan penolakan Request oleh Manager.
     */
    public function test_tc19_request_approval_and_rejection_by_manager(): void
    {
        $request = $this->reqService->create([
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'status' => 'diajukan',
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_diminta' => 50],
            ],
        ], $this->operasional);

        // Approve
        $this->reqService->approve($request, $this->manager);
        $request->refresh();
        $this->assertEquals('disetujui', $request->status);
        $this->assertEquals($this->manager->id, $request->disetujui_oleh);

        // Buat request lain untuk ditolak
        $request2 = $this->reqService->create([
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'status' => 'diajukan',
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_diminta' => 100],
            ],
        ], $this->operasional);

        // Coba tolak tanpa alasan -> gagal
        try {
            $this->reqService->reject($request2, $this->manager, '');
            $this->fail('Harusnya gagal tanpa alasan penolakan');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        // Tolak dengan alasan
        $this->reqService->reject($request2, $this->manager, 'Stok di gudang pusat sedang dialokasikan untuk batch lain');
        $request2->refresh();
        $this->assertEquals('ditolak', $request2->status);
        $this->assertEquals('Stok di gudang pusat sedang dialokasikan untuk batch lain', $request2->alasan_tolak);
    }

    /**
     * TC-20 s.d. TC-24: Siklus pemenuhan Request via Transfer:
     * Buat transfer -> status request 'diproses' -> kirim transfer (stok asal terpotong) -> terima transfer (QC invarian, stok tujuan bertambah qty_baik) -> request otomatis 'selesai'.
     */
    public function test_tc20_to_tc24_request_fulfillment_via_transfer_lifecycle(): void
    {
        // 1. Setup stok di gudang asal
        $this->stokService->masuk($this->bahan->id, $this->gudangPusat->id, 100, 'mutasi_manual', null, 'Saldo test');
        $stokAsalAwal = $this->stokService->saldo($this->bahan->id, $this->gudangPusat->id);
        $stokTujuanAwal = $this->stokService->saldo($this->bahan->id, $this->gudangOps->id);

        // 2. Buat Request Bahan & Disetujui
        $request = $this->reqService->create([
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'status' => 'diajukan',
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_diminta' => 50],
            ],
        ], $this->operasional);

        $this->reqService->approve($request, $this->manager);

        // 3. TC-20: Buat Transfer pemenuhan
        $transfer = $this->trfService->create([
            'jenis' => 'pemenuhan_request_bahan',
            'request_id' => $request->id,
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'items' => [
                [
                    'produk_id' => $this->bahan->id,
                    'request_item_id' => $request->items->first()->id,
                    'qty_dikirim' => 50,
                ],
            ],
        ], $this->operasional);

        $request->refresh();
        $this->assertEquals('diproses', $request->status); // Status request berubah ke diproses

        // 4. TC-21: Kirim Transfer -> memotong stok gudang asal
        $this->trfService->ship($transfer, $this->operasional);
        $transfer->refresh();
        $this->assertEquals('dikirim', $transfer->status);
        $this->assertEquals($stokAsalAwal - 50, $this->stokService->saldo($this->bahan->id, $this->gudangPusat->id));
        $this->assertEquals($stokTujuanAwal, $this->stokService->saldo($this->bahan->id, $this->gudangOps->id)); // Belum masuk tujuan

        // 5. TC-23: Uji pelanggaran invarian QC (qty_baik + qty_rusak != qty_dikirim)
        try {
            $this->trfService->receive($transfer, [
                'items' => [
                    [
                        'id' => $transfer->items->first()->id,
                        'qty_baik' => 40,
                        'qty_rusak' => 5, // 40 + 5 = 45 != 50
                    ],
                ],
            ], $this->gudangUser);
            $this->fail('Harusnya gagal karena melanggar invarian QC');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        // 6. TC-22 & TC-24: Terima transfer dengan invarian valid (45 baik + 5 rusak = 50 dikirim)
        $this->trfService->receive($transfer, [
            'items' => [
                [
                    'id' => $transfer->items->first()->id,
                    'qty_baik' => 45,
                    'qty_rusak' => 5,
                    'keterangan_rusak' => 'Kemasan sobek selama pengangkutan',
                ],
            ],
        ], $this->gudangUser);

        $transfer->refresh();
        $this->assertEquals('selesai', $transfer->status);

        // Hanya qty_baik (45) yang masuk ke stok gudang tujuan
        $this->assertEquals($stokTujuanAwal + 45, $this->stokService->saldo($this->bahan->id, $this->gudangOps->id));

        // Data barang rusak tersimpan di transfer_items
        $tItem = $transfer->items->first();
        $this->assertEquals(45, (float) $tItem->qty_baik);
        $this->assertEquals(5, (float) $tItem->qty_rusak);
        $this->assertEquals('Kemasan sobek selama pengangkutan', $tItem->keterangan_rusak);

        // TC-24: Request otomatis selesai
        $request->refresh();
        $this->assertEquals('selesai', $request->status);
    }

    /**
     * TC-25: Transfer langsung tanpa request_id (Retur Bahan).
     */
    public function test_tc25_direct_transfer_without_request_id(): void
    {
        $this->stokService->masuk($this->bahan->id, $this->gudangOps->id, 20, 'mutasi_manual', null, 'Saldo awal');

        $transfer = $this->trfService->create([
            'jenis' => 'retur_bahan',
            'gudang_asal_id' => $this->gudangOps->id,
            'gudang_tujuan_id' => $this->gudangPusat->id,
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_dikirim' => 10],
            ],
        ], $this->operasional);

        $this->assertNull($transfer->request_id);
        $this->assertEquals('retur_bahan', $transfer->jenis);

        $this->trfService->ship($transfer, $this->operasional);
        $this->trfService->receive($transfer, [
            'items' => [
                ['id' => $transfer->items->first()->id, 'qty_baik' => 10, 'qty_rusak' => 0],
            ],
        ], $this->gudangUser);

        $transfer->refresh();
        $this->assertEquals('selesai', $transfer->status);
    }

    /**
     * TC-38: Kirim Produk Jadi merujuk Request Produk Jadi.
     */
    public function test_tc38_kirim_produk_jadi_links_to_request_produk_jadi(): void
    {
        // Fulfillment buat Request Produk Jadi
        $request = $this->reqService->create([
            'jenis' => 'request_produk_jadi',
            'gudang_asal_id' => $this->gudangOps->id,
            'gudang_tujuan_id' => $this->gudangFf->id,
            'status' => 'diajukan',
            'items' => [
                ['produk_id' => $this->produkJadi->id, 'qty_diminta' => 30],
            ],
        ], $this->fulfillment);

        $this->reqService->approve($request, $this->manager);

        // Operasional buat Transfer kirim_produk_jadi merujuk request_id
        $transfer = $this->trfService->create([
            'jenis' => 'kirim_produk_jadi',
            'request_id' => $request->id,
            'gudang_asal_id' => $this->gudangOps->id,
            'gudang_tujuan_id' => $this->gudangFf->id,
            'items' => [
                [
                    'produk_id' => $this->produkJadi->id,
                    'request_item_id' => $request->items->first()->id,
                    'qty_dikirim' => 30,
                ],
            ],
        ], $this->operasional);

        $this->assertEquals($request->id, $transfer->request_id);
        $request->refresh();
        $this->assertEquals('diproses', $request->status);
    }

    /**
     * TC-39: Aturan 1:1 active Transfer per Request & batal draft Transfer kembalikan Request ke disetujui.
     */
    public function test_tc39_one_to_one_active_transfer_constraint_and_draft_cancellation(): void
    {
        $request = $this->reqService->create([
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'status' => 'diajukan',
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_diminta' => 50],
            ],
        ], $this->operasional);

        $this->reqService->approve($request, $this->manager);

        // Transfer 1 dibuat
        $trf1 = $this->trfService->create([
            'jenis' => 'pemenuhan_request_bahan',
            'request_id' => $request->id,
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_dikirim' => 50],
            ],
        ], $this->operasional);

        // Coba buat Transfer 2 untuk Request yang sama -> harus ditolak
        try {
            $this->trfService->create([
                'jenis' => 'pemenuhan_request_bahan',
                'request_id' => $request->id,
                'gudang_asal_id' => $this->gudangPusat->id,
                'gudang_tujuan_id' => $this->gudangOps->id,
                'items' => [
                    ['produk_id' => $this->bahan->id, 'qty_dikirim' => 50],
                ],
            ], $this->operasional);
            $this->fail('Harusnya gagal karena request sudah memiliki transfer aktif');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        // Batal Draft Transfer 1 -> Request kembali ke disetujui (FR-TRF-07)
        $this->trfService->cancelDraft($trf1, $this->operasional, 'Salah pilih gudang tujuan');
        $trf1->refresh();
        $this->assertEquals('dibatalkan', $trf1->status);

        $request->refresh();
        $this->assertEquals('disetujui', $request->status);

        // Sekarang Transfer pengganti dapat dibuat untuk Request tersebut
        $trf2 = $this->trfService->create([
            'jenis' => 'pemenuhan_request_bahan',
            'request_id' => $request->id,
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_dikirim' => 50],
            ],
        ], $this->operasional);

        $this->assertNotNull($trf2);
        $this->assertEquals('draft', $trf2->status);
    }

    /**
     * FR-REQ-08: Edit Request berstatus disetujui otomatis reset ke status diajukan.
     */
    public function test_edit_approved_request_reverts_status_to_diajukan(): void
    {
        $request = $this->reqService->create([
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'status' => 'diajukan',
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_diminta' => 50],
            ],
        ], $this->operasional);

        $this->reqService->approve($request, $this->manager);
        $this->assertEquals('disetujui', $request->status);

        // Edit kuantitas
        $this->reqService->update($request, [
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_diminta' => 75],
            ],
        ], $this->operasional);

        $request->refresh();
        $this->assertEquals('diajukan', $request->status);
        $this->assertNull($request->disetujui_oleh);
        $this->assertEquals(75, (float) $request->items->first()->qty_diminta);
    }
}
