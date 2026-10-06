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
        $this->assertGreaterThan(0, (float) $ai->safety_stock);
        $this->assertGreaterThan(0, (float) $ai->target_stock);

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
        $expectedBufferDays = round($diff + $expectedTambahan);
        $this->assertEqualsWithDelta($expectedBufferDays, (float) $ai->buffer_days, 0.01);

        // Safety Stock = adu_eta * buffer_days
        $this->assertEqualsWithDelta(round((float) $ai->adu_eta * (float) $ai->buffer_days), (float) $ai->safety_stock, 150.0);

        // Target Stock = adu_eta * (lead_time + review_period) + safety_stock
        $expectedTarget = round(((float) $ai->adu_eta * ((float) $ai->lead_time + $ai->review_period)) + (float) $ai->safety_stock);
        $this->assertEqualsWithDelta($expectedTarget, (float) $ai->target_stock, 150.0);

        // Proyeksi = stok_saat_ini + inbound_before_eta - (adu_eta * lead_time)
        $expectedProyeksi = round((float) $ai->stok_saat_ini + (float) $ai->inbound_before_eta - ((float) $ai->adu_eta * (float) $ai->lead_time));
        $this->assertEqualsWithDelta($expectedProyeksi, (float) $ai->proyeksi, 150.0);
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

    /**
     * Benchmark Verifikasi Kasus Uji Bab 6.3.5 PRD v2.2 (Botol 50ml)
     */
    public function test_benchmark_botol_50ml_case_prd_v2_2(): void
    {
        $this->actingAs($this->purchasing);

        // 1. Setup produk Botol 50ml Non-Varian dengan parameter Bab 6.3.5
        $p = Produk::create([
            'sku' => 'TEST-BTL50',
            'nama' => 'Botol 50ml Test Case PRD 2.2',
            'satuan' => 'pcs',
            'satuan_order_moq' => 10000,
            'faktor_konversi' => 1,
            'tipe' => 'kemas',
            'supplier_id' => $this->supplier->id,
            'harga_hpp' => 1500,
        ]);

        $abcA = \App\Models\KlasifikasiAbc::where('kode', 'a')->firstOrFail();

        // 2. Submit update manual impor dengan parameter pasti Bab 6.3.5
        $res = $this->postJson(route('analisa.update-manual-impor'), [
            'produk_id' => $p->id,
            'periode_mulai' => '2024-10-01',
            'periode_akhir' => '2025-01-31',
            'out' => 55099,
            'lead_time_average' => 60,
            'lead_time_max' => 92,
            'klasifikasi_abc_id' => $abcA->id,
            'review_period' => 30,
            'stok_saat_ini' => 1200,
            'inbound_before_eta' => 0,
            'harga_per_satuan' => 1500,
        ]);
        $res->assertOk();

        // 3. Verifikasi hasil kalkulasi engine pada database
        $ai = AnalisaImpor::where('produk_id', $p->id)->firstOrFail();

        // Jumlah hari: 01/10/2024 s/d 31/01/2025 = 123 hari (31 + 30 + 31 + 31)
        $this->assertEquals(123, $ai->jumlah_hari_periode);

        // ADU Base = 55.099 / 123 = 447.96 pcs/hari
        $this->assertEqualsWithDelta(447.96, (float) $ai->adu_base, 150.0);

        // Buffer Days = (92 - 60) + 4 = 36 hari
        $this->assertEqualsWithDelta(36.00, (float) $ai->buffer_days, 0.01);

        // ADU ETA = 447.96 + (60 / 30) = 449.96 pcs/hari
        $this->assertEqualsWithDelta(449.96, (float) $ai->adu_eta, 150.0);

        // Safety Stock = 449.96 * 36 = 16.198,56 pcs
        $this->assertEqualsWithDelta(16198.56, (float) $ai->safety_stock, 1.0);

        // Minimum Stock = 449.96 * 60 = 26.997,60 pcs
        $this->assertEqualsWithDelta(26997.60, (float) $ai->minimum_stock, 1.0);

        // Target Stock = 449.96 * (60 + 36 + 30) = 56.694,96 pcs
        $this->assertEqualsWithDelta(56694.96, (float) $ai->target_stock, 1.0);

        // Proyeksi = 1.200 + 0 - 26.997,60 = -25.797,60 pcs
        $this->assertEqualsWithDelta(-25797.60, (float) $ai->proyeksi, 1.0);

        // Kebutuhan Qty Order = 82.492,56 pcs
        $this->assertEqualsWithDelta(82492.56, (float) $ai->qty_order, 1.0);

        // Rekomendasi Order Bulat MOQ 10.000 = ceil(82.492,56 / 10.000) * 10.000 = 90.000 pcs!
        $this->assertEquals(90000, (int) $ai->po);
        $this->assertEquals('po', $ai->status);
    }

    public function test_klasifikasi_abc_crud_and_options_endpoint(): void
    {
        $this->actingAs($this->purchasing);

        // Test options endpoint
        $optRes = $this->getJson(route('klasifikasi-abc.options'));
        $optRes->assertOk();
        $optRes->assertJsonStructure(['success', 'data']);
        $this->assertNotEmpty($optRes->json('data'));

        // Test create new ABC class
        $createRes = $this->postJson(route('klasifikasi-abc.store'), [
            'kode' => 'khusus_x',
            'nama' => 'Khusus X Super Critical',
            'tambahan_buffer_hari' => 7,
            'warna_badge' => 'red',
            'deskripsi' => 'Pengadaan komponen khusus uji',
            'is_active' => true,
        ]);
        $createRes->assertOk();
        $id = $createRes->json('data.id');

        // Test update ABC class
        $updateRes = $this->putJson(route('klasifikasi-abc.update', $id), [
            'kode' => 'khusus_x',
            'nama' => 'Khusus X Updated',
            'tambahan_buffer_hari' => 8,
            'warna_badge' => 'amber',
            'is_active' => true,
        ]);
        $updateRes->assertOk();
        $this->assertEquals(8, $updateRes->json('data.tambahan_buffer_hari'));

        // Test delete ABC class
        $delRes = $this->deleteJson(route('klasifikasi-abc.destroy', $id));
        $delRes->assertOk();
        $this->assertDatabaseMissing('klasifikasi_abc', ['id' => $id]);
    }

    public function test_impor_data_expands_variants_into_distinct_rows_and_prefills_po_items(): void
    {
        $this->actingAs($this->purchasing);

        // Generate data impor
        $this->postJson(route('analisa.generate-impor'))->assertOk();

        // Ambil data impor
        $res = $this->getJson(route('analisa.impor.data'));
        $res->assertOk();

        $rows = collect($res->json('data'));
        $botolRows = $rows->where('produk_id', $this->botol->id);

        // Menghasilkan 1 baris per produk master tanpa varian
        $this->assertCount(1, $botolRows);
        $row = $botolRows->first();
        $this->assertNotNull($row);
        $this->assertEquals($this->botol->sku, $row['sku']);
        $this->assertGreaterThan(0, (float) $row['safety_stock']);
    }
}

