<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\RequestTransfer;
use App\Models\Stok;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestTransferEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
        $this->seed();
    }

    public function test_status_column_in_datatable_renders_priority_color_badges(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $gudangAsal = Gudang::firstOrFail();
        $gudangTujuan = Gudang::where('id', '!=', $gudangAsal->id)->firstOrFail();

        $rt = RequestTransfer::create([
            'no_transaksi' => 'TR-TEST-BADGE-01',
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangAsal->id,
            'gudang_tujuan_id' => $gudangTujuan->id,
            'status' => 'diajukan',
            'created_by' => $operasional->id,
        ]);

        $response = $this->getJson(route('rt.data'));
        $response->assertOk();

        $json = $response->json();
        $this->assertNotEmpty($json['data']);

        $found = collect($json['data'])->firstWhere('no_transaksi', 'TR-TEST-BADGE-01');
        $this->assertNotNull($found);
        $this->assertStringContainsString('bg-amber-50', $found['status']);
        $this->assertStringContainsString('text-amber-700', $found['status']);
        $this->assertStringContainsString('Diajukan', $found['status']);
    }

    public function test_stok_tersedia_endpoint_returns_stock_map_per_warehouse(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $gudang = Gudang::firstOrFail();
        $produk = Produk::bahan()->firstOrFail();

        $stokService = app(StokService::class);
        $stokService->masuk($produk->id, $gudang->id, 750, 'manual', 1, 'Inisialisasi test stok');

        $response = $this->getJson(route('rt.stok-tersedia', [
            'gudang_id' => $gudang->id,
            'produk_ids' => $produk->id,
        ]));

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('stok', $data);
        $this->assertGreaterThanOrEqual(750, (float) $data['stok'][$produk->id]);
    }

    public function test_edit_draft_request_transfer_renders_and_updates(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $gudangAsal = Gudang::where('tipe', 'bahan_baku')->firstOrFail();
        $gudangTujuan = Gudang::where('tipe', 'operasional')->firstOrFail();
        $bahan1 = Produk::bahan()->firstOrFail();
        $bahan2 = Produk::bahan()->where('id', '!=', $bahan1->id)->firstOrFail();

        // 1. Buat dokumen tahap draft
        $rt = RequestTransfer::create([
            'no_transaksi' => 'TR-DRAFT-EDIT-01',
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangAsal->id,
            'gudang_tujuan_id' => $gudangTujuan->id,
            'status' => 'draft',
            'catatan' => 'Catatan awal draft',
            'created_by' => $operasional->id,
        ]);
        $rt->items()->create([
            'produk_id' => $bahan1->id,
            'qty_diminta' => 100,
        ]);

        // 2. Akses halaman edit draft
        $resEdit = $this->get(route('rt.edit', $rt));
        $resEdit->assertOk();
        $resEdit->assertSee('Edit TR-DRAFT-EDIT-01');
        $resEdit->assertSee('Catatan awal draft');

        // 3. Simpan update perubahan draft
        $resUpdate = $this->put(route('rt.update', $rt), [
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangAsal->id,
            'gudang_tujuan_id' => $gudangTujuan->id,
            'catatan' => 'Catatan revisi draft',
            'items' => [
                [
                    'produk_id' => $bahan1->id,
                    'qty_diminta' => 250,
                ],
                [
                    'produk_id' => $bahan2->id,
                    'qty_diminta' => 50,
                ],
            ],
        ]);

        $resUpdate->assertRedirect(route('rt.show', $rt));
        $resUpdate->assertSessionHas('success');

        // Verifikasi perubahan di database
        $this->assertDatabaseHas('request_transfers', [
            'id' => $rt->id,
            'catatan' => 'Catatan revisi draft',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('request_transfer_items', [
            'request_transfer_id' => $rt->id,
            'produk_id' => $bahan1->id,
            'qty_diminta' => 250,
        ]);

        $this->assertDatabaseHas('request_transfer_items', [
            'request_transfer_id' => $rt->id,
            'produk_id' => $bahan2->id,
            'qty_diminta' => 50,
        ]);
    }

    public function test_non_draft_cannot_be_edited(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $gudangAsal = Gudang::where('tipe', 'bahan_baku')->firstOrFail();
        $gudangTujuan = Gudang::where('tipe', 'operasional')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        $rt = RequestTransfer::create([
            'no_transaksi' => 'TR-SUBMITTED-01',
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangAsal->id,
            'gudang_tujuan_id' => $gudangTujuan->id,
            'status' => 'diajukan', // BUKAN DRAFT
            'created_by' => $operasional->id,
        ]);

        // GET edit must fail
        $resGet = $this->get(route('rt.edit', $rt));
        $resGet->assertStatus(422);

        // PUT update must fail
        $resPut = $this->put(route('rt.update', $rt), [
            'jenis' => 'req_bahan',
            'items' => [
                ['produk_id' => $bahan->id, 'qty_diminta' => 50],
            ],
        ]);
        $resPut->assertStatus(422);
    }

    public function test_cancellation_before_approval(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $gudangAsal = Gudang::where('tipe', 'bahan_baku')->firstOrFail();
        $gudangTujuan = Gudang::where('tipe', 'operasional')->firstOrFail();

        $rt = RequestTransfer::create([
            'no_transaksi' => 'TR-CANCEL-01',
            'jenis' => 'req_bahan',
            'gudang_asal_id' => $gudangAsal->id,
            'gudang_tujuan_id' => $gudangTujuan->id,
            'status' => 'diajukan',
            'created_by' => $operasional->id,
        ]);

        $response = $this->post(route('rt.transition', $rt), [
            'aksi' => 'cancel',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('request_transfers', [
            'id' => $rt->id,
            'status' => 'dibatalkan',
        ]);
    }

    public function test_surat_jalan_view_renders_matching_specification(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        $gudangAsal = Gudang::where('tipe', 'bahan_baku')->firstOrFail();
        $gudangTujuan = Gudang::where('tipe', 'operasional')->firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        $rt = RequestTransfer::create([
            'no_transaksi' => 'TRF-20260801-0002',
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $gudangAsal->id,
            'gudang_tujuan_id' => $gudangTujuan->id,
            'status' => 'selesai',
            'catatan' => 'ORDERAN LIQUID OPENING HARUMNYA DIKIRIM 30 JULI',
            'created_by' => $operasional->id,
        ]);

        $rt->items()->create([
            'produk_id' => $bahan->id,
            'qty_diminta' => 537,
            'qty_dikirim' => 537,
        ]);

        $response = $this->get(route('rt.surat-jalan', $rt));
        $response->assertOk();
        $response->assertSee('SURAT JALAN');
        $response->assertSee('Dokumen Pengiriman Barang');
        $response->assertSee('TRF-20260801-0002');
        $response->assertSee('DARI (PENGIRIM)');
        $response->assertSee('KE (PENERIMA)');
        $response->assertSee($gudangAsal->nama);
        $response->assertSee($gudangTujuan->nama);
        $response->assertSee($bahan->sku);
        $response->assertSee($bahan->nama);
        $response->assertSee('537');
        $response->assertSee('TOTAL ITEM');
        $response->assertSee('ORDERAN LIQUID OPENING HARUMNYA DIKIRIM 30 JULI');
        $response->assertSee('PENGIRIM');
        $response->assertSee('PENGEMUDI / KURIR');
        $response->assertSee('PENERIMA');
    }
}
