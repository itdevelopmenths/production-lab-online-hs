<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\Bom;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\StockRequest;
use App\Models\Stok;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BatchAdjustBahanTest extends TestCase
{
    use RefreshDatabase;

    private User $operasional;
    private Gudang $gudangOp;
    private Gudang $gudangPusat;
    private Produk $alkohol;
    private Produk $oil;
    private BatchProduksi $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');

        $this->operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->gudangOp = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $this->gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();

        $this->alkohol = Produk::create(['sku' => 'RAW-ALK', 'nama' => 'Alkohol 96%', 'tipe' => 'bahan', 'satuan' => 'ml', 'is_active' => true]);
        $this->oil = Produk::create(['sku' => 'RAW-OIL', 'nama' => 'Fragrance Oil', 'tipe' => 'bahan', 'satuan' => 'ml', 'is_active' => true]);
        $pj = Produk::create(['sku' => 'PJ-ADJ', 'nama' => 'Parfum Adj 50ml', 'tipe' => 'produk_jadi', 'satuan' => 'pcs', 'is_active' => true]);
        Bom::create(['produk_jadi_id' => $pj->id, 'bahan_id' => $this->alkohol->id, 'qty_per_unit' => 40]);
        Bom::create(['produk_jadi_id' => $pj->id, 'bahan_id' => $this->oil->id, 'qty_per_unit' => 10]);

        $this->batch = BatchProduksi::create([
            'no_batch' => 'B-TEST-0001',
            'produk_id' => $pj->id,
            'qty_rencana' => 10,
            'gudang_operasional_id' => $this->gudangOp->id,
            'status' => 'release',
            'tanggal' => now()->toDateString(),
            'created_by' => $this->operasional->id,
        ]);
        $this->batch->alokasi()->create(['bahan_id' => $this->alkohol->id, 'qty_dialokasikan' => 400, 'status' => 'dilepas']);
        $this->batch->alokasi()->create(['bahan_id' => $this->oil->id, 'qty_dialokasikan' => 100, 'status' => 'dilepas']);
    }

    public function test_adjustment_creates_submitted_request_for_filled_items_only(): void
    {
        $response = $this->actingAs($this->operasional)->post(route('batches.adjust-bahan', $this->batch), [
            'alasan' => 'penguapan',
            'catatan' => 'Suhu tinggi',
            'items' => [
                ['bahan_id' => $this->alkohol->id, 'qty' => '25.5', 'evidence' => UploadedFile::fake()->image('bukti.jpg')],
                ['bahan_id' => $this->oil->id, 'qty' => '0'],
            ],
        ]);

        $request = StockRequest::with('items')->firstOrFail();
        $response->assertRedirect(route('requests.show', $request));

        $this->assertSame('diajukan', $request->status);
        $this->assertSame('request_bahan', $request->jenis);
        $this->assertSame($this->batch->id, $request->referensi_batch_id);
        $this->assertSame($this->gudangOp->id, $request->gudang_asal_id);
        $this->assertSame($this->gudangPusat->id, $request->gudang_tujuan_id);
        $this->assertStringContainsString('Penguapan', $request->catatan);
        $this->assertCount(1, $request->items);
        $this->assertSame($this->alkohol->id, $request->items->first()->produk_id);
        $this->assertEqualsWithDelta(25.5, (float) $request->items->first()->qty_diminta, 0.0001);
        $this->assertSame(0, Stok::where('gudang_id', $this->gudangOp->id)->count());

        $path = $request->items->first()->evidence_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_adjustment_requires_catatan(): void
    {
        $this->actingAs($this->operasional)->post(route('batches.adjust-bahan', $this->batch), [
            'alasan' => 'penguapan',
            'catatan' => '',
            'items' => [['bahan_id' => $this->alkohol->id, 'qty' => '5']],
        ])->assertSessionHasErrors('catatan');

        $this->assertSame(0, StockRequest::count());
    }

    public function test_adjustment_rejects_invalid_evidence_file(): void
    {
        $this->actingAs($this->operasional)->post(route('batches.adjust-bahan', $this->batch), [
            'alasan' => 'penguapan',
            'catatan' => 'Uji file',
            'items' => [['bahan_id' => $this->alkohol->id, 'qty' => '5', 'evidence' => UploadedFile::fake()->create('x.exe', 10)]],
        ])->assertSessionHasErrors('items.0.evidence');

        $this->assertSame(0, StockRequest::count());
    }

    public function test_adjustment_rejects_when_no_qty_filled(): void
    {
        $this->actingAs($this->operasional)->post(route('batches.adjust-bahan', $this->batch), [
            'alasan' => 'tumpah',
            'catatan' => 'Botol bocor',
            'items' => [['bahan_id' => $this->alkohol->id, 'qty' => '']],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, StockRequest::count());
    }

    public function test_adjustment_rejects_bahan_outside_batch_recipe(): void
    {
        $asing = Produk::create(['sku' => 'RAW-X', 'nama' => 'Asing', 'tipe' => 'bahan', 'satuan' => 'ml', 'is_active' => true]);

        $this->actingAs($this->operasional)->post(route('batches.adjust-bahan', $this->batch), [
            'alasan' => 'susut',
            'catatan' => 'Selisih takar',
            'items' => [['bahan_id' => $asing->id, 'qty' => '5']],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, StockRequest::count());
    }

    public function test_adjustment_only_allowed_while_batch_release(): void
    {
        $this->batch->update(['status' => 'selesai']);

        $this->actingAs($this->operasional)->post(route('batches.adjust-bahan', $this->batch), [
            'alasan' => 'penguapan',
            'catatan' => 'Uji status',
            'items' => [['bahan_id' => $this->alkohol->id, 'qty' => '5']],
        ])->assertStatus(422);

        $this->assertSame(0, StockRequest::count());
    }

    public function test_detail_page_shows_adjustment_modal_while_release(): void
    {
        $this->actingAs($this->operasional)
            ->get(route('batches.show', $this->batch))
            ->assertOk()
            ->assertSee('Penyesuaian Bahan')
            ->assertSee(route('batches.adjust-bahan', $this->batch), false);
    }

    public function test_detail_page_lists_adjustment_history(): void
    {
        $this->actingAs($this->operasional)->post(route('batches.adjust-bahan', $this->batch), [
            'alasan' => 'penguapan',
            'catatan' => 'Suhu ruang tinggi',
            'items' => [['bahan_id' => $this->alkohol->id, 'qty' => '12']],
        ]);

        $request = StockRequest::firstOrFail();

        $this->actingAs($this->operasional)
            ->get(route('batches.show', $this->batch))
            ->assertOk()
            ->assertSee('Riwayat Penyesuaian Bahan')
            ->assertSee('Penguapan')
            ->assertSee('Suhu ruang tinggi')
            ->assertSee($request->no_request)
            ->assertSee('Alkohol 96%');
    }

    public function test_detail_page_hides_history_without_adjustment(): void
    {
        $this->actingAs($this->operasional)
            ->get(route('batches.show', $this->batch))
            ->assertOk()
            ->assertDontSee('Riwayat Penyesuaian Bahan');
    }
}
