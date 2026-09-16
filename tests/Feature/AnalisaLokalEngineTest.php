<?php

namespace Tests\Feature;

use App\Models\AnalisaLokal;
use App\Models\AnalisaLokalInput;
use App\Models\Gudang;
use App\Models\LeadTimeLokal;
use App\Models\LeadTimeLokalStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RekomendasiOrderLokal;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AnalisaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalisaLokalEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchasing;
    protected Produk $alkohol;
    protected Supplier $supplier;
    protected Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Model::preventLazyLoading(true);

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->alkohol = Produk::where('sku', 'ALK-01')->firstOrFail();
        $this->supplier = Supplier::where('kategori', 'lokal')->firstOrFail();
        $this->gudang = Gudang::where('tipe', 'bahan_baku')->firstOrFail();
    }

    public function test_generate_lokal_persists_to_all_four_tables_with_accurate_formulas(): void
    {
        $service = app(AnalisaService::class);
        $result = $service->generateLokal($this->alkohol->id, $this->purchasing->id);

        $this->assertGreaterThanOrEqual(1, $result['generated_count']);

        // 1. Table 1: lead_time_lokal_stage
        $avgStage = LeadTimeLokalStage::where('produk_id', $this->alkohol->id)
            ->where('skenario', 'average')->first();
        $maxStage = LeadTimeLokalStage::where('produk_id', $this->alkohol->id)
            ->where('skenario', 'max')->first();

        $this->assertNotNull($avgStage);
        $this->assertNotNull($maxStage);
        $this->assertEquals(13, $avgStage->totalHari());
        $this->assertEquals(22, $maxStage->totalHari());

        // 2. Table 2: lead_time_lokal
        $leadTime = LeadTimeLokal::where('produk_id', $this->alkohol->id)->first();
        $this->assertNotNull($leadTime);
        $this->assertEquals(13, $leadTime->total_average_lead_time);
        $this->assertEquals(22, $leadTime->total_max_lead_time);
        $this->assertEquals(2, $leadTime->tambahan_buffer_hari);
        // Safety Stock = (22 - 13) + 2 = 11
        $this->assertEquals(11, $leadTime->safety_stock);

        // 3. Table 3: analisa_lokal
        $analisa = AnalisaLokal::where('produk_id', $this->alkohol->id)->first();
        $this->assertNotNull($analisa);
        $this->assertEquals(13, $analisa->total_average_lead_time);
        $this->assertEquals(11, $analisa->safety_stock);
        // ADU = 39303.25 / 30 = 1310.1083
        $this->assertEqualsWithDelta(1310.1083, (float) $analisa->adu, 0.001);
        // Batas Minimum = 1310.1083 * (13 + 11) = 31442.60
        $this->assertEqualsWithDelta(31442.60, (float) $analisa->batas_minimum, 0.01);
        // Target Stock = 1310.1083 * (13 + 11 + 15) = 51094.23
        $this->assertEqualsWithDelta(51094.23, (float) $analisa->target_stock, 0.01);
        $this->assertEquals($this->purchasing->id, $analisa->generated_by);

        // 4. Table 4: rekomendasi_order_lokal
        $rek = RekomendasiOrderLokal::where('produk_id', $this->alkohol->id)->first();
        $this->assertNotNull($rek);
        $this->assertEquals(8000, (float) $rek->stok_saat_ini);
        $this->assertEquals(0, (float) $rek->akan_datang);
        // Selisih = 8000 - 31442.60 = -23442.60
        $this->assertEqualsWithDelta(-23442.60, (float) $rek->selisih, 0.01);
        $this->assertEquals('order', $rek->status);
        // MOQ ALK-01 = 5000 -> CEILING(23442.60, 5000) = 25000
        $this->assertEquals(25000, (float) $rek->rumus_moq);
        // Rekomendasi order (base UOM factor 1) = 25000
        $this->assertEquals(25000, (float) $rek->rekomendasi_order);
        // Harga ml = 526 -> Total Nominal = 25000 * 526 = 13,150,000
        $this->assertEquals(25000 * 526, (float) $rek->total_nominal_order);
    }

    public function test_api_generate_lokal_endpoint(): void
    {
        $this->actingAs($this->purchasing);

        $response = $this->postJson(route('analisa.generate-lokal'), [
            'produk_id' => $this->alkohol->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonPath('data.generated_count', 1);

        $this->assertDatabaseHas('analisa_lokal', [
            'produk_id' => $this->alkohol->id,
            'generated_by' => $this->purchasing->id,
        ]);
    }

    public function test_api_lokal_data_checking_endpoint_returns_4_tables_and_metadata(): void
    {
        $this->actingAs($this->purchasing);

        $response = $this->getJson(route('analisa.lokal.data'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'produk_id',
                    'sku',
                    'nama',
                    'satuan',
                    'total_avg_lead_time',
                    'total_max_lead_time',
                    'safety_stock',
                    'terjual_rata_rata_4bulan',
                    'adu',
                    'review_period',
                    'batas_minimum',
                    'target_stock',
                    'stok_saat_ini',
                    'akan_datang',
                    'tersedia',
                    'selisih',
                    'rumus_moq',
                    'status',
                    'rekomendasi_order',
                    'harga_per_satuan',
                    'total_nominal_order',
                    'generated_at',
                    'generated_by',
                ],
            ],
            'tables' => [
                'stages',
                'lead_time',
                'analisa',
                'rekomendasi',
            ],
            'meta' => [
                'last_generated_at',
                'last_generated_by',
            ],
        ]);
    }

    public function test_update_lead_time_stages_endpoint_recalculates_summary(): void
    {
        $this->actingAs($this->purchasing);

        $avgStage = LeadTimeLokalStage::where('produk_id', $this->alkohol->id)
            ->where('skenario', 'average')->firstOrFail();
        $maxStage = LeadTimeLokalStage::where('produk_id', $this->alkohol->id)
            ->where('skenario', 'max')->firstOrFail();

        $payload = [
            'produk_id' => $this->alkohol->id,
            'tambahan_buffer_hari' => 4, // updated from 2 to 4
            'stages' => [
                [
                    'id' => $avgStage->id,
                    'perencanaan' => 2, // was 1
                    'approval' => 1,
                    'supplier_confirm' => 2,
                    'payment' => 1,
                    'po' => 1,
                    'pengemasan' => 2,
                    'pengiriman' => 3,
                    'unloading' => 1,
                    'input' => 1,
                ], // total = 14
                [
                    'id' => $maxStage->id,
                    'perencanaan' => 3, // was 2
                    'approval' => 2,
                    'supplier_confirm' => 3,
                    'payment' => 2,
                    'po' => 1,
                    'pengemasan' => 4,
                    'pengiriman' => 6,
                    'unloading' => 1,
                    'input' => 1,
                ], // total = 23
            ],
        ];

        $response = $this->postJson(route('analisa.stages-lokal.update'), $payload);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonPath('data.total_average_lead_time', 14);
        $response->assertJsonPath('data.total_max_lead_time', 23);
        $response->assertJsonPath('data.tambahan_buffer_hari', 4);
        // Safety stock = (23 - 14) + 4 = 13
        $response->assertJsonPath('data.safety_stock', 13);

        $this->assertDatabaseHas('lead_time_lokal', [
            'produk_id' => $this->alkohol->id,
            'total_average_lead_time' => 14,
            'total_max_lead_time' => 23,
            'tambahan_buffer_hari' => 4,
            'safety_stock' => 13,
        ]);
    }

    public function test_conversion_to_purchase_uom_in_rekomendasi_order(): void
    {
        // Beri produk faktor konversi 1000 (mis. 1 Jerigen = 1000 ml)
        $this->alkohol->update([
            'faktor_konversi' => 1000,
            'satuan_beli' => 'jerigen',
            'harga_hpp' => 500,
        ]);

        $service = app(AnalisaService::class);
        $service->generateLokal($this->alkohol->id, $this->purchasing->id);

        $rek = RekomendasiOrderLokal::where('produk_id', $this->alkohol->id)->first();
        $this->assertNotNull($rek);
        $this->assertEquals(25000, (float) $rek->rumus_moq);
        // 25000 ml / 1000 = 25 jerigen
        $this->assertEquals(25, (float) $rek->rekomendasi_order);
    }

    public function test_create_draft_po_from_selected_rekomendasi_order(): void
    {
        $this->actingAs($this->purchasing);

        $payload = [
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudang->id,
            'items' => [
                [
                    'produk_id' => $this->alkohol->id,
                    'qty' => 25000,
                    'qty_satuan_beli' => 25,
                    'satuan_beli' => 'jerigen',
                    'faktor_konversi' => 1000,
                    'harga_satuan' => 526000,
                    'harga_total' => 13150000,
                ],
            ],
        ];

        $response = $this->post(route('analisa.create-po'), $payload);

        $po = PurchaseOrder::latest('id')->firstOrFail();
        $response->assertRedirect(route('purchasing.show', $po));

        $this->assertEquals('draft', $po->status);
        $this->assertTrue((bool) $po->dari_analisa);
        $this->assertEquals($this->supplier->id, $po->supplier_id);
        $this->assertEquals($this->gudang->id, $po->gudang_id);
        $this->assertEquals($this->purchasing->id, $po->created_by);

        $this->assertDatabaseHas('purchase_order_items', [
            'po_id' => $po->id,
            'produk_id' => $this->alkohol->id,
            'qty' => 25000,
            'qty_satuan_beli' => 25,
            'satuan_beli' => 'jerigen',
            'faktor_konversi' => 1000,
            'harga_total' => 13150000,
        ]);
    }

    public function test_get_create_po_page_renders_with_prefilled_items(): void
    {
        $this->actingAs($this->purchasing);

        $service = app(AnalisaService::class);
        $service->generateLokal($this->alkohol->id, $this->purchasing->id);

        $rek = RekomendasiOrderLokal::where('produk_id', $this->alkohol->id)->firstOrFail();

        // 1. Akses tanpa filter ids (default status order)
        $response = $this->get(route('analisa.create-po'));
        $response->assertOk();
        $response->assertViewIs('analisa.create-po');
        $response->assertViewHas(['suppliers', 'gudang', 'prefilledItems']);

        // 2. Akses dengan query ids spesifik
        $responseFiltered = $this->get(route('analisa.create-po', ['ids' => $rek->id]));
        $responseFiltered->assertOk();
        $responseFiltered->assertViewHas('prefilledItems', function ($items) use ($rek) {
            return count($items) === 1 && $items[0]['id'] === $rek->id;
        });
    }
}

