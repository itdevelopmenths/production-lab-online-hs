<?php

namespace Tests\Feature;

use App\Models\Produk;
use App\Models\RekomendasiOrderLokal;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AnalisaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalisaSupplierRelationTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchasing;
    protected Produk $alkohol;
    protected Produk $botol;
    protected Supplier $supplierLokal;
    protected Supplier $supplierImpor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Model::preventLazyLoading(true);

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->alkohol = Produk::where('sku', 'ALK-01')->firstOrFail();
        $this->botol = Produk::where('sku', 'BTL-P50')->firstOrFail();
        $this->supplierLokal = Supplier::where('nama', 'PT Alkohol Nusantara')->firstOrFail();
        $this->supplierImpor = Supplier::where('nama', 'Glass Bottle Import Co.')->firstOrFail();
    }

    public function test_produk_belongs_to_supplier_and_supplier_has_many_produk(): void
    {
        $this->assertNotNull($this->alkohol->supplier_id);
        $this->assertEquals($this->supplierLokal->id, $this->alkohol->supplier_id);
        $this->assertInstanceOf(Supplier::class, $this->alkohol->supplier);
        $this->assertEquals('PT Alkohol Nusantara', $this->alkohol->supplier->nama);

        $this->assertNotNull($this->botol->supplier_id);
        $this->assertEquals($this->supplierImpor->id, $this->botol->supplier_id);
        $this->assertInstanceOf(Supplier::class, $this->botol->supplier);
        $this->assertEquals('Glass Bottle Import Co.', $this->botol->supplier->nama);

        // Test reverse relation
        $this->assertTrue($this->supplierLokal->produk->contains('id', $this->alkohol->id));
        $this->assertTrue($this->supplierImpor->produk->contains('id', $this->botol->id));
    }

    public function test_lokal_data_endpoint_returns_supplier_id_and_nama(): void
    {
        app(AnalisaService::class)->generateLokal($this->alkohol->id, $this->purchasing->id);

        $response = $this->actingAs($this->purchasing)
            ->getJson(route('analisa.lokal.data'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'produk_id',
                        'sku',
                        'nama',
                        'supplier_id',
                        'supplier_nama',
                        'supplier_kategori',
                    ],
                ],
            ]);

        $item = collect($response->json('data'))->firstWhere('produk_id', $this->alkohol->id);
        $this->assertNotNull($item);
        $this->assertEquals($this->supplierLokal->id, $item['supplier_id']);
        $this->assertEquals('PT Alkohol Nusantara', $item['supplier_nama']);
        $this->assertEquals('lokal', $item['supplier_kategori']);
    }

    public function test_impor_data_endpoint_returns_supplier_id_and_nama(): void
    {
        app(AnalisaService::class)->generateImpor($this->botol->id, $this->purchasing->id);

        $response = $this->actingAs($this->purchasing)
            ->getJson(route('analisa.impor.data'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'produk_id',
                        'sku',
                        'nama',
                        'supplier_id',
                        'supplier_nama',
                        'supplier_kategori',
                    ],
                ],
            ]);

        $item = collect($response->json('data'))->firstWhere('produk_id', $this->botol->id);
        $this->assertNotNull($item);
        $this->assertEquals($this->supplierImpor->id, $item['supplier_id']);
        $this->assertEquals('Glass Bottle Import Co.', $item['supplier_nama']);
        $this->assertEquals('impor', $item['supplier_kategori']);
    }

    public function test_create_po_form_auto_preselects_supplier_when_selected_items_share_supplier(): void
    {
        app(AnalisaService::class)->generateLokal($this->alkohol->id, $this->purchasing->id);
        $rek = RekomendasiOrderLokal::where('produk_id', $this->alkohol->id)->firstOrFail();

        $response = $this->actingAs($this->purchasing)
            ->get(route('analisa.create-po', ['ids' => $rek->id]));

        $response->assertOk();
        $response->assertViewHas('defaultSupplierId', $this->supplierLokal->id);
        $response->assertSee('PT Alkohol Nusantara');
    }

    public function test_analisa_index_blade_renders_supplier_filter_and_supplier_badges(): void
    {
        $response = $this->actingAs($this->purchasing)
            ->get(route('analisa.index'));

        $response->assertOk();
        $response->assertSee('supplierFilter');
        $response->assertSee('Semua Supplier');
        $response->assertSee('PT Alkohol Nusantara');
        $response->assertSee('Glass Bottle Import Co.');
        $response->assertSee('r.supplier_nama');
        $response->assertSee('stg.supplier_nama');
        $response->assertSee('Generate Analisa');
        $response->assertSee('Checking (Generate Ulang)');
        $response->assertSee('Finalisasi ke Riwayat');
    }

    public function test_lokal_data_stages_contains_supplier_info(): void
    {
        app(AnalisaService::class)->generateLokal($this->alkohol->id, $this->purchasing->id);

        $response = $this->actingAs($this->purchasing)
            ->getJson(route('analisa.lokal.data'));

        $response->assertOk();
        $stages = collect($response->json('tables.stages'))->firstWhere('produk_id', $this->alkohol->id);
        $this->assertNotNull($stages);
        $this->assertEquals($this->supplierLokal->id, $stages['supplier_id']);
        $this->assertEquals('PT Alkohol Nusantara', $stages['supplier_nama']);
    }

    public function test_finalisasi_records_supplier_info_in_detail_payload(): void
    {
        $service = app(AnalisaService::class);
        $service->generateLokal($this->alkohol->id, $this->purchasing->id);
        $result = $service->finalisasi('bahan_lokal', $this->purchasing->id);

        $this->assertGreaterThanOrEqual(1, $result['count']);

        $riwayat = \App\Models\RiwayatAnalisa::where('session_id', $result['session_id'])
            ->where('item_label', $this->alkohol->sku)
            ->firstOrFail();

        $this->assertNotNull($riwayat->detail_payload);
        $this->assertEquals($this->supplierLokal->id, $riwayat->detail_payload['supplier_id']);
        $this->assertEquals('PT Alkohol Nusantara', $riwayat->detail_payload['supplier_nama']);
    }

    public function test_analisa_index_blade_renders_manual_input_modals_and_action_buttons(): void
    {
        $response = $this->actingAs($this->purchasing)
            ->get(route('analisa.index'));

        $response->assertOk();
        $response->assertSee('openStageModal');
        $response->assertSee('openManualLokalModal');
        $response->assertSee('openManualImporModal');
        $response->assertSee('Form Input Tahap Lead Time', false);
        $response->assertSee('Form Input Bahan Baku Lokal', false);
        $response->assertSee('Form Input Bahan Baku Impor', false);
    }

    public function test_update_manual_lokal_updates_input_and_triggers_live_recalculation(): void
    {
        $response = $this->actingAs($this->purchasing)
            ->postJson(route('analisa.update-manual-lokal'), [
                'produk_id' => $this->alkohol->id,
                'terjual_rata_rata_4bulan' => 600,
                'review_period' => 10,
                'tambahan_buffer_hari' => 4,
                'stok_saat_ini' => 150,
                'harga_ml_pcs' => 250,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $input = \App\Models\AnalisaLokalInput::where('produk_id', $this->alkohol->id)->first();
        $this->assertNotNull($input);
        $this->assertEquals(600, $input->terjual_rata_rata_4bulan);
        $this->assertEquals(10, $input->review_period);
        $this->assertEquals(150, $input->stok_saat_ini);
        $this->assertEquals(250, $input->harga_per_satuan);

        $lt = \App\Models\LeadTimeLokal::where('produk_id', $this->alkohol->id)->first();
        $this->assertNotNull($lt);
        $this->assertEquals(4, $lt->tambahan_buffer_hari);

        $analisa = \App\Models\AnalisaLokal::where('produk_id', $this->alkohol->id)->first();
        $this->assertNotNull($analisa);
        $this->assertEquals(20.0, (float) $analisa->adu); // 600 / 30
        $this->assertEquals(10, $analisa->review_period);

        $rek = \App\Models\RekomendasiOrderLokal::where('produk_id', $this->alkohol->id)->first();
        $this->assertNotNull($rek);
        $this->assertEquals(150, (float) $rek->stok_saat_ini);
        $this->assertEquals(250, (float) $rek->harga_ml_pcs);
    }

    public function test_update_manual_impor_updates_parameters_and_triggers_live_recalculation(): void
    {
        $response = $this->actingAs($this->purchasing)
            ->postJson(route('analisa.update-manual-impor'), [
                'produk_id' => $this->botol->id,
                'lead_time_average' => 45,
                'lead_time_max' => 65,
                'out' => 1200,
                'review_period' => 20,
                'klasifikasi_abc' => 'a',
                'stok_saat_ini' => 200,
                'inbound_before_eta' => 100,
                'harga_per_satuan' => 15000,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $lt = \App\Models\LeadTimeImpor::where('produk_id', $this->botol->id)->first();
        $this->assertNotNull($lt);
        $this->assertEquals(45, (float) $lt->lead_time_average);
        $this->assertEquals(65, (float) $lt->lead_time_max);

        $ai = \App\Models\AnalisaImpor::where('produk_id', $this->botol->id)->first();
        $this->assertNotNull($ai);
        $this->assertEquals(1200, (float) $ai->out);
        $this->assertEquals(round(1200 / 122, 4), (float) $ai->adu_base); // 1200 / 122 days
        $this->assertEquals(20, $ai->review_period);
        $this->assertEquals('a', $ai->klasifikasi_abc);
        $this->assertEquals(200, (float) $ai->stok_saat_ini);
        $this->assertEquals(100, (float) $ai->inbound_before_eta);
        $this->assertEquals(15000, (float) $ai->harga_per_satuan);
        $this->assertEquals(24, (float) $ai->buffer_days); // (65 - 45) + 4 (bonus A) = 24
    }

    public function test_update_lead_time_stages_with_manual_stages_and_buffer(): void
    {
        $service = app(AnalisaService::class);
        $service->generateLokal($this->alkohol->id, $this->purchasing->id);

        $stages = \App\Models\LeadTimeLokalStage::where('produk_id', $this->alkohol->id)->get();
        $avgStage = $stages->firstWhere('skenario', 'average');
        $maxStage = $stages->firstWhere('skenario', 'max');

        $response = $this->actingAs($this->purchasing)
            ->postJson(route('analisa.stages-lokal.update'), [
                'produk_id' => $this->alkohol->id,
                'tambahan_buffer_hari' => 5,
                'stages' => [
                    [
                        'id' => $avgStage->id,
                        'skenario' => 'average',
                        'perencanaan' => 1,
                        'approval' => 1,
                        'supplier_confirm' => 2,
                        'payment' => 1,
                        'po' => 1,
                        'pengemasan' => 4,
                        'pengiriman' => 3,
                        'unloading' => 1,
                        'input' => 1,
                    ],
                    [
                        'id' => $maxStage->id,
                        'skenario' => 'max',
                        'perencanaan' => 2,
                        'approval' => 2,
                        'supplier_confirm' => 4,
                        'payment' => 2,
                        'po' => 2,
                        'pengemasan' => 7,
                        'pengiriman' => 5,
                        'unloading' => 2,
                        'input' => 2,
                    ]
                ]
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $updatedAvg = $avgStage->fresh();
        $this->assertEquals(2, $updatedAvg->supplier_confirm);
        $this->assertEquals(1, $updatedAvg->po);
        $this->assertEquals(4, $updatedAvg->pengemasan);
        $this->assertEquals(1, $updatedAvg->unloading);

        $lt = \App\Models\LeadTimeLokal::where('produk_id', $this->alkohol->id)->first();
        $this->assertEquals(5, $lt->tambahan_buffer_hari);
    }
}

