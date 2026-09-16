<?php

namespace Tests\Feature;

use App\Models\AnalisaImpor;
use App\Models\Gudang;
use App\Models\LeadTimeImpor;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\RiwayatAnalisa;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AnalisaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalisaImporEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchasing;
    protected Produk $botol;
    protected Supplier $supplier;
    protected Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Model::preventLazyLoading(true);

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->botol = Produk::where('sku', 'BTL-P50')->firstOrFail();
        $this->supplier = Supplier::where('kategori', 'impor')->first() ?? Supplier::firstOrFail();
        $this->gudang = Gudang::where('tipe', 'bahan_baku')->firstOrFail();
    }

    public function test_generate_impor_persists_to_lead_time_and_analisa_tables(): void
    {
        $service = app(AnalisaService::class);
        $result = $service->generateImpor(null, $this->purchasing->id);

        $this->assertGreaterThanOrEqual(1, $result['generated_count']);

        // 1. Check LeadTimeImpor
        $lt = LeadTimeImpor::where('produk_id', $this->botol->id)->first();
        $this->assertNotNull($lt);
        $this->assertGreaterThan(0, (float) $lt->lead_time_average);
        $this->assertGreaterThanOrEqual((float) $lt->lead_time_average, (float) $lt->lead_time_max);

        // 2. Check AnalisaImpor
        $ai = AnalisaImpor::where('produk_id', $this->botol->id)->first();
        $this->assertNotNull($ai);
        $this->assertEquals($this->purchasing->id, $ai->generated_by);
        $this->assertNotNull($ai->generated_at);
        $this->assertTrue($ai->punya_varian);
        $this->assertIsArray($ai->varian_detail);
        $this->assertCount(2, $ai->varian_detail);

        // 3. Test Eloquent relations on Produk
        $produk = Produk::with(['leadTimeImpor', 'analisaImpor'])->find($this->botol->id);
        $this->assertInstanceOf(LeadTimeImpor::class, $produk->leadTimeImpor);
        $this->assertInstanceOf(AnalisaImpor::class, $produk->analisaImpor);
    }

    public function test_generate_impor_abc_buffer_days_and_formulas(): void
    {
        $service = app(AnalisaService::class);
        $service->generateImpor(null, $this->purchasing->id);

        $ai = AnalisaImpor::where('produk_id', $this->botol->id)->firstOrFail();
        $lt = LeadTimeImpor::where('produk_id', $this->botol->id)->firstOrFail();

        // Rule ABC buffer:
        // Wajib A / A => +4 hari
        // B => +2 hari
        // C => +0 hari
        $expectedTambahan = match ($ai->klasifikasi_abc) {
            'wajib_a', 'a' => 4,
            'b' => 2,
            default => 0,
        };
        $this->assertEquals($expectedTambahan, $ai->tambahan_buffer_hari);

        // buffer_days = max(0, lead_time_max - lead_time_avg) + tambahan_buffer_hari
        $diff = max(0, (float) $lt->lead_time_max - (float) $lt->lead_time_average);
        $expectedBufferDays = $diff + $expectedTambahan;
        $this->assertEqualsWithDelta($expectedBufferDays, (float) $ai->buffer_days, 0.01);

        // Safety Stock = adu_eta * buffer_days
        $this->assertEqualsWithDelta((float) $ai->adu_eta * (float) $ai->buffer_days, (float) $ai->safety_stock, 0.05);

        // Target Stock = adu_eta * (lead_time + review_period) + safety_stock
        $expectedTarget = ((float) $ai->adu_eta * ((float) $ai->lead_time + $ai->review_period)) + (float) $ai->safety_stock;
        $this->assertEqualsWithDelta($expectedTarget, (float) $ai->target_stock, 0.05);

        // Proyeksi = stok_saat_ini + inbound_before_eta - (adu_eta * lead_time)
        $expectedProyeksi = (float) $ai->stok_saat_ini + (float) $ai->inbound_before_eta - ((float) $ai->adu_eta * (float) $ai->lead_time);
        $this->assertEqualsWithDelta($expectedProyeksi, (float) $ai->proyeksi, 0.05);
    }

    public function test_api_generate_impor_endpoint(): void
    {
        $this->actingAs($this->purchasing);

        $response = $this->postJson(route('analisa.generate-impor'));
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'generated_count',
            ],
        ]);
        $this->assertTrue($response->json('success'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.generated_count'));
    }

    public function test_api_impor_data_checking_endpoint_returns_stored_data(): void
    {
        $this->actingAs($this->purchasing);

        // First, trigger generate
        $this->postJson(route('analisa.generate-impor'))->assertOk();

        // Second, fetch checking data
        $response = $this->getJson(route('analisa.impor.data'));
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'produk_id',
                    'sku',
                    'nama',
                    'klasifikasi_abc',
                    'lead_time_average',
                    'lead_time_max',
                    'buffer_days',
                    'safety_stock',
                    'target_stock',
                    'stok_saat_ini',
                    'status',
                    'total_qty_order',
                    'total_nominal_order',
                    'varian',
                    'generated_at',
                    'generated_by',
                ],
            ],
            'meta' => [
                'last_generated_at',
                'last_generated_by',
            ],
        ]);

        $this->assertNotEmpty($response->json('meta.last_generated_at'));
        $this->assertEquals($this->purchasing->name, $response->json('meta.last_generated_by'));
    }

    public function test_finalisasi_locks_working_data_to_riwayat_analisa_with_session_id(): void
    {
        $this->actingAs($this->purchasing);

        // Ensure import engine data is generated
        $this->postJson(route('analisa.generate-impor'))->assertOk();

        // Call finalisasi endpoint
        $response = $this->postJson(route('analisa.finalisasi'), [
            'tipe' => 'bahan_impor',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'session_id',
            'count',
        ]);
        $this->assertTrue($response->json('success'));
        $sessionId = $response->json('session_id');
        $this->assertStringStartsWith('SNAP-', $sessionId);

        // Verify in database
        $snapshots = RiwayatAnalisa::where('session_id', $sessionId)->get();
        $this->assertNotEmpty($snapshots);

        foreach ($snapshots as $snap) {
            $this->assertEquals('bahan_impor', $snap->tipe);
            $this->assertTrue($snap->is_locked);
            $this->assertEquals($this->purchasing->id, $snap->dicatat_oleh);
            $this->assertIsArray($snap->detail_payload);
            $this->assertArrayHasKey('klasifikasi_abc', $snap->detail_payload);
            $this->assertArrayHasKey('buffer_days', $snap->detail_payload);
            $this->assertArrayHasKey('safety_stock', $snap->detail_payload);
        }

        // Verify riwayat endpoint lists the session
        $riwayatRes = $this->getJson(route('analisa.riwayat.data'));
        $riwayatRes->assertOk();
        $riwayatRes->assertJsonFragment([
            'session_id' => $sessionId,
            'is_locked' => true,
        ]);
    }

    public function test_create_po_from_impor_recommendations(): void
    {
        $this->actingAs($this->purchasing);

        // Generate import data
        $this->postJson(route('analisa.generate-impor'))->assertOk();

        $ai = AnalisaImpor::where('produk_id', $this->botol->id)->firstOrFail();

        // 1. Test prefilling form in createPoForm with impor id
        $formRes = $this->get(route('analisa.create-po', ['ids' => $ai->produk_id]));
        $formRes->assertOk();
        $formRes->assertSee($this->botol->nama);

        // 2. Test submitting PO creation
        $poResponse = $this->post(route('analisa.create-po'), [
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudang->id,
            'tanggal' => now()->toDateString(),
            'eta' => now()->addDays(20)->toDateString(),
            'catatan' => 'PO Impor Auto Engine',
            'items' => [
                [
                    'produk_id' => $this->botol->id,
                    'qty' => 500,
                    'satuan' => $this->botol->satuan ?? 'pcs',
                    'harga_satuan' => 3000,
                    'harga_total' => 1500000,
                ],
            ],
        ]);

        $poResponse->assertRedirect();
        $po = PurchaseOrder::latest('created_at')->first();
        $this->assertNotNull($po);
        $this->assertTrue((bool) $po->dari_analisa);
        $this->assertEquals('draft', $po->status);
        $this->assertEquals($this->supplier->id, $po->supplier_id);
    }
}
