<?php

namespace Tests\Feature;

use App\Models\BarangDatang;
use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderTermin;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchasingCostCalculator;
use App\Services\StokService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * ════════════════════════════════════════════════════════════════════════════
 * COMPREHENSIVE QA TEST SUITE: PURCHASING & AP MODULE
 * Standards: ISO/IEC 25010 & ISTQB Professional QA Specifications
 *
 * Total Scenarios: 35 Test Cases
 *   - TC-CALC (01–05): Mathematical Accuracy & HPP Proration
 *   - TC-VAL  (01–06): Functional Suitability & Validation Guardrails
 *   - TC-STATE (01–07): Finite State Machine & Workflow Integrity
 *   - TC-GRN  (01–05): Goods Receipt & Inventory Movement Audit
 *   - TC-PAY  (01–06): Accounts Payable, Payment Allocation & Financial Guardrails
 *   - TC-SEC  (01–04): Security, RBAC & Privacy Protection
 *   - TC-PERF (01–02): Reliability & Query Efficiency (No N+1)
 * ════════════════════════════════════════════════════════════════════════════
 */
class PurchasingComprehensiveQATest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $purchasing;
    protected User $gudangUser;
    protected User $operasional;
    protected Supplier $supplier;
    protected Gudang $gudangPusat;
    protected Gudang $gudangLab;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading(true);
        $this->seed();

        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $this->operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();

        $this->supplier = Supplier::active()->firstOrFail();
        $this->gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $this->gudangLab = Gudang::where('kode', 'GD-OPS')->firstOrFail();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. TC-CALC: MATHEMATICAL ACCURACY & HPP PRORATION (5 Tests)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * TC-CALC-01: Alokasi prorata biaya global (Diskon, PPN, Ongkir, Adjustment)
     * ke multi-item dengan harga bervariasi.
     * Syarat QA: sum(item.net_total) == po.grand_total.
     */
    public function test_calc_01_multi_item_global_cost_proration_and_grand_total_match(): void
    {
        $calculator = app(PurchasingCostCalculator::class);
        $items = [
            ['produk_id' => 101, 'qty' => 10, 'harga_total' => 1000000],
            ['produk_id' => 102, 'qty' => 20, 'harga_total' => 2000000],
            ['produk_id' => 103, 'qty' => 30, 'harga_total' => 3000000],
        ];

        // Subtotal = 6.000.000
        // Rasio: Item 1 = 1/6 (16.67%), Item 2 = 2/6 (33.33%), Item 3 = 3/6 (50%)
        $headerDiskon = 60000;
        $headerPpn = 120000;
        $headerOngkir = 180000;
        $headerAdj = -10000; // Adjustment pembulatan minus

        $result = $calculator->calculatePoTotals($items, $headerDiskon, $headerPpn, $headerOngkir, $headerAdj);

        $this->assertEquals(6000000, $result['subtotal_produk']);
        $this->assertEquals(60000, $result['diskon_total']);
        $this->assertEquals(120000, $result['ppn_nominal']);
        $this->assertEquals(180000, $result['ongkos_kirim']);
        $this->assertEquals(-10000, $result['adjustment']);
        $this->assertEquals(6230000, $result['grand_total']);

        // Verifikasi item 1: 1.000.000 - 10.000 + 20.000 + 30.000 - 1.666,67 = 1.038.333,33
        $item1 = $result['items'][0];
        $this->assertEquals(10000, $item1['diskon']);
        $this->assertEquals(20000, $item1['ppn']);
        $this->assertEquals(30000, $item1['ongkir']);
        $this->assertGreaterThan(0, $item1['hpp_per_satuan']);

        // Total seluruh net items harus tepat sama dengan grand total (toleransi floating-point pembulatan sen <= Rp 1)
        $sumNet = array_sum(array_column($result['items'], 'net_total'));
        $this->assertEqualsWithDelta($result['grand_total'], $sumNet, 1.0);
    }

    /**
     * TC-CALC-02: Alokasi biaya global pada PO single-item.
     * Biaya global harus dialokasikan 100% ke item tersebut tanpa kehilangan sen.
     */
    public function test_calc_02_single_item_global_cost_full_allocation(): void
    {
        $calculator = app(PurchasingCostCalculator::class);
        $items = [
            ['produk_id' => 201, 'qty' => 50, 'harga_total' => 2500000],
        ];

        $result = $calculator->calculatePoTotals($items, 50000, 110000, 75000, 500);

        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];
        $expectedNet = 2500000 - 50000 + 110000 + 75000 + 500; // 2.635.500
        $this->assertEquals($expectedNet, $item['net_total']);
        $this->assertEquals($expectedNet, $result['grand_total']);
        $this->assertEquals(round($expectedNet / 50, 4), $item['hpp_per_satuan']);
    }

    /**
     * TC-CALC-03: Zero Global Cost.
     * PO murni tanpa diskon/ppn/ongkir/adj menghasilkan net_total = harga_total dan hpp = harga / qty.
     */
    public function test_calc_03_zero_global_costs_net_equals_gross_price(): void
    {
        $calculator = app(PurchasingCostCalculator::class);
        $items = [
            ['produk_id' => 301, 'qty' => 100, 'harga_total' => 5000000],
            ['produk_id' => 302, 'qty' => 20, 'harga_total' => 1000000],
        ];

        $result = $calculator->calculatePoTotals($items, 0, 0, 0, 0);

        $this->assertEquals(6000000, $result['grand_total']);
        $this->assertEquals(5000000, $result['items'][0]['net_total']);
        $this->assertEquals(50000, $result['items'][0]['hpp_per_satuan']);
        $this->assertEquals(1000000, $result['items'][1]['net_total']);
        $this->assertEquals(50000, $result['items'][1]['hpp_per_satuan']);
    }

    /**
     * TC-CALC-04: Presisi perhitungan HPP mikro (4 angka desimal).
     * Dibutuhkan untuk formula parfum/lab yang menggunakan satuan gram atau mililiter.
     */
    public function test_calc_04_micro_fractional_hpp_precision_up_to_four_decimals(): void
    {
        $calculator = app(PurchasingCostCalculator::class);
        $items = [
            ['produk_id' => 401, 'qty' => 3333.33, 'harga_total' => 150000],
        ];

        $result = $calculator->calculatePoTotals($items, 0, 0, 0, 0);
        $hpp = $result['items'][0]['hpp_per_satuan'];

        // 150000 / 3333.33 = 45.000045...
        $this->assertEquals(45.0, round($hpp, 2));
        $this->assertIsFloat($hpp);
    }

    /**
     * TC-CALC-05: Penanganan aman saat subtotal produk = 0.
     * Sistem tidak boleh crash dengan error Division by Zero.
     */
    public function test_calc_05_safe_division_by_zero_handling_when_subtotal_is_zero(): void
    {
        $calculator = app(PurchasingCostCalculator::class);
        $items = [
            ['produk_id' => 501, 'qty' => 0, 'harga_total' => 0],
            ['produk_id' => 502, 'qty' => 0, 'harga_total' => 0],
        ];

        $result = $calculator->calculatePoTotals($items, 10000, 2000, 5000, 0);

        $this->assertEquals(0, $result['subtotal_produk']);
        $this->assertIsArray($result['items']);
        $this->assertEquals(0, $result['items'][0]['hpp_per_satuan']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. TC-VAL: FUNCTIONAL SUITABILITY & VALIDATION GUARDRAILS (6 Tests)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * TC-VAL-01: Pembuatan PO tanpa baris item harus ditolak (min:1).
     */
    public function test_val_01_empty_items_rejected_with_validation_error(): void
    {
        $this->actingAs($this->purchasing);

        $response = $this->post(route('purchasing.store'), [
            'supplier_id' => $this->supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [],
        ]);

        $response->assertSessionHasErrors(['items']);
    }

    /**
     * TC-VAL-02: Pembuatan PO dengan duplikasi produk yang sama dalam 1 PO ditolak.
     */
    public function test_val_02_duplicate_product_in_items_rejected(): void
    {
        $this->actingAs($this->purchasing);
        $item = Produk::bahan()->active()->firstOrFail();

        $response = $this->post(route('purchasing.store'), [
            'supplier_id' => $this->supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [
                ['produk_id' => $item->id, 'qty' => 10, 'harga_total' => 100000],
                ['produk_id' => $item->id, 'qty' => 20, 'harga_total' => 200000],
            ],
        ]);

        $response->assertSessionHasErrors();
    }

    /**
     * TC-VAL-03: Kuantitas 0 atau negatif, dan harga negatif harus ditolak server.
     */
    public function test_val_03_zero_or_negative_quantity_and_price_rejected(): void
    {
        $this->actingAs($this->purchasing);
        $item = Produk::bahan()->active()->firstOrFail();

        $response = $this->post(route('purchasing.store'), [
            'supplier_id' => $this->supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [
                ['produk_id' => $item->id, 'qty' => 0, 'harga_total' => -50000],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.qty', 'items.0.harga_total']);
    }

    /**
     * TC-VAL-04: ID Supplier atau Gudang yang tidak ada di database ditolak (exists).
     */
    public function test_val_04_non_existent_foreign_keys_rejected(): void
    {
        $this->actingAs($this->purchasing);
        $item = Produk::bahan()->active()->firstOrFail();

        $response = $this->post(route('purchasing.store'), [
            'supplier_id' => '00000000-0000-0000-0000-000000000000',
            'gudang_id' => '00000000-0000-0000-0000-000000000000',
            'tanggal' => now()->toDateString(),
            'items' => [
                ['produk_id' => $item->id, 'qty' => 10, 'harga_total' => 100000],
            ],
        ]);

        $response->assertSessionHasErrors(['supplier_id', 'gudang_id']);
    }

    /**
     * TC-VAL-05: Skema termin memerlukan data tanggal tempo dan nominal > 0.
     */
    public function test_val_05_termin_validation_required_when_skema_termin(): void
    {
        $this->actingAs($this->purchasing);
        $item = Produk::bahan()->active()->firstOrFail();

        $response = $this->post(route('purchasing.store'), [
            'supplier_id' => $this->supplier->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'termin',
            'items' => [
                ['produk_id' => $item->id, 'qty' => 10, 'harga_total' => 100000],
            ],
            'termins' => [
                ['tanggal_tempo' => '', 'nominal_tagihan' => 0, 'keterangan' => 'DP'],
            ],
        ]);

        $response->assertSessionHasErrors(['termins.0.tanggal_tempo', 'termins.0.nominal_tagihan']);
    }

    /**
     * TC-VAL-06: Otomasi no_po dan no_invoice saat tidak diisi manual oleh pengguna.
     */
    public function test_val_06_automated_po_number_and_invoice_generation(): void
    {
        $this->actingAs($this->purchasing);
        $item = Produk::bahan()->active()->firstOrFail();

        $response = $this->post(route('purchasing.store'), [
            'supplier_id' => $this->supplier->id,
            'tanggal' => now()->toDateString(),
            'no_invoice' => '', // Kosongkan agar otomatis
            'items' => [
                ['produk_id' => $item->id, 'qty' => 10, 'harga_total' => 500000],
            ],
        ]);

        $po = PurchaseOrder::latest()->firstOrFail();
        $response->assertRedirect(route('purchasing.show', $po));

        $this->assertStringStartsWith('PO-', $po->no_po);
        $this->assertStringStartsWith('INV/PO/', $po->no_invoice);
        $this->assertEquals('draft', $po->status);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. TC-STATE: FINITE STATE MACHINE & WORKFLOW INTEGRITY (7 Tests)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * TC-STATE-01: Alur Kerja Valid (Golden Flow): Draft -> Diajukan -> Dikirim ke Gudang -> Selesai.
     */
    public function test_state_01_valid_golden_lifecycle_draft_to_submitted_approved_received(): void
    {
        $po = $this->createTestPo();
        $this->assertEquals('draft', $po->status);

        // 1. Submit (Draft -> Diajukan)
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.submit', $po))
            ->assertSessionHas('success');
        $po->refresh();
        $this->assertEquals('diajukan', $po->status);

        // 2. Approve (Diajukan -> Dikirim ke Gudang)
        $this->actingAs($this->manager)
            ->post(route('purchasing.approve', $po))
            ->assertSessionHas('success');
        $po->refresh();
        $this->assertEquals('dikirim_ke_gudang', $po->status);

        // 3. Receive (Dikirim ke Gudang -> Selesai)
        $this->actingAs($this->gudangUser)
            ->post(route('purchasing.receive', $po), [
                'gudang_id' => $this->gudangPusat->id,
                'tanggal_terima' => now()->toDateString(),
                'kondisi' => 'baik',
                'items' => [
                    ['po_item_id' => $po->items[0]->id, 'qty_diterima' => $po->items[0]->qty],
                ],
            ])
            ->assertSessionHas('success');
        $po->refresh();
        $this->assertEquals('selesai', $po->status);
    }

    /**
     * TC-STATE-02: Pembatalan PO diizinkan pada status Draft.
     */
    public function test_state_02_cancellation_allowed_from_draft(): void
    {
        $po = $this->createTestPo(['status' => 'draft']);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.cancel', $po))
            ->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals('dibatalkan', $po->status);
    }

    /**
     * TC-STATE-03: Pembatalan PO diizinkan pada status Diajukan.
     */
    public function test_state_03_cancellation_allowed_from_diajukan(): void
    {
        $po = $this->createTestPo(['status' => 'diajukan']);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.cancel', $po))
            ->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals('dibatalkan', $po->status);
    }

    /**
     * TC-STATE-04: Pembatalan PO diizinkan pada status Dikirim ke Gudang sebelum barang diterima.
     */
    public function test_state_04_cancellation_allowed_from_dikirim_ke_gudang(): void
    {
        $po = $this->createTestPo(['status' => 'dikirim_ke_gudang']);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.cancel', $po))
            ->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals('dibatalkan', $po->status);
    }

    /**
     * TC-STATE-05: Pembatalan PO berstatus Selesai dilarang keras (HTTP 422).
     */
    public function test_state_05_cancellation_strictly_rejected_when_po_is_selesai(): void
    {
        $po = $this->createTestPo(['status' => 'selesai']);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.cancel', $po))
            ->assertStatus(422);

        $po->refresh();
        $this->assertEquals('selesai', $po->status);
    }

    /**
     * TC-STATE-06: Transisi status ilegal ditolak dengan HTTP 422:
     * - Dilarang approve langsung dari Draft
     * - Dilarang receive dari Draft atau Diajukan
     * - Dilarang submit PO yang sudah Diajukan
     */
    public function test_state_06_illegal_status_transitions_rejected_with_422(): void
    {
        $po = $this->createTestPo(['status' => 'draft']);

        // 1. Approve dari draft -> 422
        $this->actingAs($this->manager)
            ->post(route('purchasing.approve', $po))
            ->assertStatus(422);

        // 2. Receive dari draft -> 422
        $this->actingAs($this->gudangUser)
            ->post(route('purchasing.receive', $po), [
                'gudang_id' => $this->gudangPusat->id,
                'tanggal_terima' => now()->toDateString(),
                'kondisi' => 'baik',
                'items' => [
                    ['po_item_id' => $po->items[0]->id, 'qty_diterima' => 10],
                ],
            ])
            ->assertStatus(422);

        // 3. Submit ulang saat sudah Diajukan -> 422
        $po->update(['status' => 'diajukan']);
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.submit', $po))
            ->assertStatus(422);
    }

    /**
     * TC-STATE-07: Quick Edit tanggal & ETA ditolak pada PO yang sudah Selesai atau Dibatalkan.
     */
    public function test_state_07_quick_edit_date_rejected_on_finalized_po(): void
    {
        $poSelesai = $this->createTestPo(['status' => 'selesai']);
        $poBatal = $this->createTestPo(['status' => 'dibatalkan']);

        $this->actingAs($this->purchasing);

        $responseSelesai = $this->putJson(route('purchasing.quick-dates', $poSelesai), [
            'tanggal' => now()->toDateString(),
        ]);
        $responseSelesai->assertStatus(422)
            ->assertJson(['success' => false]);

        $responseBatal = $this->putJson(route('purchasing.quick-dates', $poBatal), [
            'tanggal' => now()->toDateString(),
        ]);
        $responseBatal->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. TC-GRN: GOODS RECEIPT & INVENTORY MOVEMENT AUDIT (5 Tests)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * TC-GRN-01: Penerimaan penuh (100% Match):
     * Saldo stok bertambah akurat, mutasi stok & kartu stok tercatat, status PO menjadi selesai.
     */
    public function test_grn_01_full_receipt_increases_stock_and_creates_kartu_stok(): void
    {
        $po = $this->createTestPo(['status' => 'dikirim_ke_gudang', 'gudang_id' => $this->gudangPusat->id]);
        $item = $po->items->first();
        $stokService = app(StokService::class);
        $initialStock = $stokService->saldo($item->produk_id, $this->gudangPusat->id);

        $this->actingAs($this->gudangUser)
            ->post(route('purchasing.receive', $po), [
                'gudang_id' => $this->gudangPusat->id,
                'tanggal_terima' => now()->toDateString(),
                'kondisi' => 'baik',
                'items' => [
                    ['po_item_id' => $item->id, 'qty_diterima' => $item->qty],
                ],
            ])
            ->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals('selesai', $po->status);

        // Cek pertambahan stok
        $currentStock = $stokService->saldo($item->produk_id, $this->gudangPusat->id);
        $this->assertEquals($initialStock + (float) $item->qty, $currentStock);

        // Verifikasi entri KartuStok
        $kartu = KartuStok::where('produk_id', $item->produk_id)
            ->where('gudang_id', $this->gudangPusat->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($kartu);
        $this->assertEquals('in', $kartu->tipe);
        $this->assertEquals($item->qty, (float) $kartu->qty);
        $this->assertEquals($currentStock, (float) $kartu->saldo_setelah);
    }

    /**
     * TC-GRN-02: Penerimaan parsial (Under-delivery):
     * Selisih negatif dicatat, keterangan selisih tersimpan, stok bertambah hanya sebesar qty riil diterima.
     */
    public function test_grn_02_partial_under_delivery_receipt_records_negative_discrepancy(): void
    {
        $po = $this->createTestPo(['status' => 'dikirim_ke_gudang']);
        $item = $po->items->first(); // Order 100
        $stokService = app(StokService::class);
        $initialStock = $stokService->saldo($item->produk_id, $this->gudangPusat->id);

        $this->actingAs($this->gudangUser)
            ->post(route('purchasing.receive', $po), [
                'gudang_id' => $this->gudangPusat->id,
                'tanggal_terima' => now()->toDateString(),
                'kondisi' => 'baik',
                'items' => [
                    [
                        'po_item_id' => $item->id,
                        'qty_diterima' => 80,
                        'keterangan_selisih' => 'Supplier kekurangan kemasan 20 unit',
                    ],
                ],
            ])
            ->assertSessionHas('success');

        // Verifikasi entitas BarangDatangItem
        $bardat = BarangDatang::where('po_id', $po->id)->firstOrFail();
        $bardatItem = $bardat->items()->where('po_item_id', $item->id)->firstOrFail();

        $this->assertEquals(80, (float) $bardatItem->qty_diterima);
        $this->assertEquals(-20, (float) $bardatItem->selisih);
        $this->assertEquals('Supplier kekurangan kemasan 20 unit', $bardatItem->keterangan_selisih);

        // Stok bertambah 80
        $this->assertEquals($initialStock + 80, $stokService->saldo($item->produk_id, $this->gudangPusat->id));
    }

    /**
     * TC-GRN-03: Penerimaan berlebih (Over-delivery):
     * Selisih positif tercatat, stok bertambah sesuai kuantitas fisik yang tiba.
     */
    public function test_grn_03_over_delivery_receipt_records_positive_discrepancy(): void
    {
        $po = $this->createTestPo(['status' => 'dikirim_ke_gudang']);
        $item = $po->items->first();

        $this->actingAs($this->gudangUser)
            ->post(route('purchasing.receive', $po), [
                'gudang_id' => $this->gudangPusat->id,
                'tanggal_terima' => now()->toDateString(),
                'kondisi' => 'baik',
                'items' => [
                    [
                        'po_item_id' => $item->id,
                        'qty_diterima' => 125, // Order 100, datang 125 (+25 bonus supplier)
                        'keterangan_selisih' => 'Bonus sample dari supplier',
                    ],
                ],
            ])
            ->assertSessionHas('success');

        $bardat = BarangDatang::where('po_id', $po->id)->firstOrFail();
        $bardatItem = $bardat->items()->firstOrFail();

        $this->assertEquals(125, (float) $bardatItem->qty_diterima);
        $this->assertEquals(25, (float) $bardatItem->selisih);
    }

    /**
     * TC-GRN-04: Pencatatan kondisi barang rusak sebagian.
     */
    public function test_grn_04_damaged_goods_condition_recorded(): void
    {
        $po = $this->createTestPo(['status' => 'dikirim_ke_gudang']);
        $item = $po->items->first();

        $this->actingAs($this->gudangUser)
            ->post(route('purchasing.receive', $po), [
                'gudang_id' => $this->gudangPusat->id,
                'tanggal_terima' => now()->toDateString(),
                'kondisi' => 'rusak_sebagian',
                'items' => [
                    ['po_item_id' => $item->id, 'qty_diterima' => $item->qty],
                ],
            ])
            ->assertSessionHas('success');

        $bardat = BarangDatang::where('po_id', $po->id)->firstOrFail();
        $this->assertEquals('rusak_sebagian', $bardat->kondisi);
    }

    /**
     * TC-GRN-05: Penerimaan barang menambah stok hanya pada gudang tujuan yang dipilih.
     * Tidak boleh ada kebocoran stok ke gudang lain.
     */
    public function test_grn_05_stock_mutation_isolated_to_specified_warehouse(): void
    {
        $po = $this->createTestPo(['status' => 'dikirim_ke_gudang', 'gudang_id' => $this->gudangLab->id]);
        $item = $po->items->first();
        $stokService = app(StokService::class);

        $initialPusat = $stokService->saldo($item->produk_id, $this->gudangPusat->id);
        $initialLab = $stokService->saldo($item->produk_id, $this->gudangLab->id);

        $this->actingAs($this->gudangUser)
            ->post(route('purchasing.receive', $po), [
                'gudang_id' => $this->gudangLab->id,
                'tanggal_terima' => now()->toDateString(),
                'kondisi' => 'baik',
                'items' => [
                    ['po_item_id' => $item->id, 'qty_diterima' => $item->qty],
                ],
            ]);

        // Gudang Lab bertambah, Gudang Pusat tetap tidak berubah
        $this->assertEquals($initialLab + (float) $item->qty, $stokService->saldo($item->produk_id, $this->gudangLab->id));
        $this->assertEquals($initialPusat, $stokService->saldo($item->produk_id, $this->gudangPusat->id));
    }

    /**
     * TC-GRN-06: Form penerimaan barang terkunci sesuai lokasi PO dan backend
     * memaksakan mutasi masuk ke gudang PO meskipun ada manipulasi payload.
     */
    public function test_grn_06_goods_receipt_strictly_routes_to_po_location_even_if_tampered_and_view_is_locked(): void
    {
        $po = $this->createTestPo(['status' => 'dikirim_ke_gudang', 'gudang_id' => $this->gudangLab->id]);
        $item = $po->items->first();
        $stokService = app(StokService::class);

        $initialPusat = $stokService->saldo($item->produk_id, $this->gudangPusat->id);
        $initialLab = $stokService->saldo($item->produk_id, $this->gudangLab->id);

        $this->actingAs($this->gudangUser);

        // 1. Verifikasi UI: Tampil sebagai input readonly berisi nama gudang tanpa dropdown select
        $resShow = $this->get(route('purchasing.show', $po));
        $resShow->assertOk();
        $resShow->assertSee($this->gudangLab->nama);
        $resShow->assertSee('readonly', false);
        $resShow->assertDontSee('<select name="gudang_id"', false);

        // 2. Manipulasi payload: Coba kirim gudang_id = gudangPusat
        $this->post(route('purchasing.receive', $po), [
            'gudang_id' => $this->gudangPusat->id, // Manipulasi mencoba membelokkan stok ke pusat
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'items' => [
                ['po_item_id' => $item->id, 'qty_diterima' => $item->qty],
            ],
        ])->assertSessionHas('success');

        // 3. Verifikasi backend: Stok tetap dipaksa masuk ke gudang PO (Gudang Lab), BUKAN Gudang Pusat
        $this->assertEquals($initialLab + (float) $item->qty, $stokService->saldo($item->produk_id, $this->gudangLab->id));
        $this->assertEquals($initialPusat, $stokService->saldo($item->produk_id, $this->gudangPusat->id));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. TC-PAY: ACCOUNTS PAYABLE & PAYMENT GUARDRAILS (6 Tests)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * TC-PAY-01: Pembayaran penuh (Lump Sum):
     * Status pembayaran PO berubah menjadi 'lunas', sisa tagihan 0.
     */
    public function test_pay_01_full_lump_sum_payment_marks_po_as_lunas(): void
    {
        $po = $this->createTestPo(['grand_total' => 2000000]);
        $this->assertEquals(2000000, $po->sisaTagihan());

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), [
                'skema' => 'pelunasan',
                'tanggal_bayar' => now()->toDateString(),
                'nominal' => 2000000,
            ])
            ->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals('lunas', $po->status_pembayaran);
        $this->assertEquals(0, $po->sisaTagihan());
        $this->assertTrue($po->isLunas());
    }

    /**
     * TC-PAY-02: Pembayaran sebagian (Parsial):
     * Status pembayaran PO berubah menjadi 'parsial', sisa tagihan berkurang tepat.
     */
    public function test_pay_02_partial_payment_marks_po_as_parsial(): void
    {
        $po = $this->createTestPo(['grand_total' => 3000000]);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), [
                'skema' => 'tempo',
                'tanggal_bayar' => now()->toDateString(),
                'nominal' => 1000000,
            ])
            ->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals('parsial', $po->status_pembayaran);
        $this->assertEquals(2000000, $po->sisaTagihan());
    }

    /**
     * TC-PAY-03: Pembayaran termin spesifik (Targeted Termin):
     * Mengisi termin_id mengupdate nominal_dibayar dan status termin target.
     */
    public function test_pay_03_targeted_termin_payment_updates_specific_termin_status(): void
    {
        $po = $this->createTestPo(['grand_total' => 2000000, 'skema_bayar' => 'termin']);
        $termin1 = $po->termins()->create([
            'termin_ke' => 1,
            'tanggal_tempo' => now()->addDays(5),
            'nominal_tagihan' => 1000000,
            'nominal_dibayar' => 0,
            'status' => 'belum_dibayar',
            'keterangan' => 'DP 50%',
        ]);
        $termin2 = $po->termins()->create([
            'termin_ke' => 2,
            'tanggal_tempo' => now()->addDays(15),
            'nominal_tagihan' => 1000000,
            'nominal_dibayar' => 0,
            'status' => 'belum_dibayar',
            'keterangan' => 'Pelunasan 50%',
        ]);

        // Bayar Termin 1 secara spesifik
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), [
                'skema' => 'termin',
                'tanggal_bayar' => now()->toDateString(),
                'nominal' => 1000000,
                'termin_id' => $termin1->id,
            ]);

        $termin1->refresh();
        $termin2->refresh();

        $this->assertEquals(1000000, (float) $termin1->nominal_dibayar);
        $this->assertEquals('lunas', $termin1->status);

        $this->assertEquals(0, (float) $termin2->nominal_dibayar);
        $this->assertEquals('belum_dibayar', $termin2->status);
    }

    /**
     * TC-PAY-04: Alokasi pembayaran termin otomatis secara berurutan (FIFO):
     * Pembayaran tanpa termin_id mengalir ke termin tertua yang belum lunas.
     */
    public function test_pay_04_fifo_termin_payment_allocates_across_unpaid_termins(): void
    {
        $po = $this->createTestPo(['grand_total' => 2000000, 'skema_bayar' => 'termin']);
        $termin1 = $po->termins()->create([
            'termin_ke' => 1,
            'tanggal_tempo' => now()->addDays(5),
            'nominal_tagihan' => 600000,
            'nominal_dibayar' => 0,
            'status' => 'belum_dibayar',
        ]);
        $termin2 = $po->termins()->create([
            'termin_ke' => 2,
            'tanggal_tempo' => now()->addDays(15),
            'nominal_tagihan' => 1400000,
            'nominal_dibayar' => 0,
            'status' => 'belum_dibayar',
        ]);

        // Bayar 1.000.000 tanpa termin_id (FIFO)
        // Termin 1 (600rb) lunas, sisa 400rb masuk ke Termin 2
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), [
                'skema' => 'termin',
                'tanggal_bayar' => now()->toDateString(),
                'nominal' => 1000000,
            ]);

        $termin1->refresh();
        $termin2->refresh();

        $this->assertEquals(600000, (float) $termin1->nominal_dibayar);
        $this->assertEquals('lunas', $termin1->status);

        $this->assertEquals(400000, (float) $termin2->nominal_dibayar);
        $this->assertEquals('parsial', $termin2->status);
    }

    /**
     * TC-PAY-05: Pencegahan Overpayment (Kelebihan Bayar):
     * Membayar nominal lebih besar dari sisa tagihan ditolak keras dengan ValidationException.
     */
    public function test_pay_05_overpayment_strictly_rejected_with_validation_exception(): void
    {
        $po = $this->createTestPo(['grand_total' => 1000000]);

        $this->actingAs($this->purchasing);

        $response = $this->post(route('purchasing.pay', $po), [
            'skema' => 'tempo',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 1500000, // Lebih bayar 500.000
        ]);

        $response->assertSessionHasErrors(['nominal']);
        $this->assertEquals(1000000, $po->sisaTagihan());
    }

    /**
     * TC-PAY-06: Validasi skema 'Pelunasan Penuh' harus melunasi sisa tagihan tepat:
     * Menginput nominal kurang bayar saat memilih skema pelunasan ditolak.
     */
    public function test_pay_06_underpayment_on_pelunasan_scheme_rejected(): void
    {
        $po = $this->createTestPo(['grand_total' => 1000000]);

        $this->actingAs($this->purchasing);

        $response = $this->post(route('purchasing.pay', $po), [
            'skema' => 'pelunasan',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 500000, // Kurang dari sisa tagihan 1.000.000
        ]);

        $response->assertSessionHasErrors(['nominal']);
    }

    /**
     * TC-PAY-07: Pembayaran skema Tunai (Cash / Penuh) dan validasi tampilan show:
     * - PO cash dapat dibayar dengan skema 'cash' hingga lunas.
     * - Pembayaran 'cash' kurang dari sisa tagihan ditolak keras.
     * - View show.blade.php merender badge skema transaksi sesuai skema_bayar.
     */
    public function test_pay_07_cash_payment_scheme_and_show_view_rendering(): void
    {
        $poCash = $this->createTestPo(['grand_total' => 1500000, 'skema_bayar' => 'cash']);

        // 1. Verifikasi show view merender badge tunai dan opsi form
        $this->actingAs($this->purchasing)
            ->get(route('purchasing.show', $poCash))
            ->assertOk()
            ->assertSee('Tunai (Cash / Penuh)')
            ->assertSee('value="cash"', false)
            ->assertDontSee('Jadwal Termin Tagihan:');

        // 2. Pembayaran cash kurang bayar ditolak
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $poCash), [
                'skema' => 'cash',
                'tanggal_bayar' => now()->toDateString(),
                'nominal' => 1000000, // Kurang dari 1.500.000
            ])
            ->assertSessionHasErrors(['nominal']);

        // 3. Pembayaran cash penuh sukses melunasi PO
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $poCash), [
                'skema' => 'cash',
                'tanggal_bayar' => now()->toDateString(),
                'nominal' => 1500000,
            ])
            ->assertSessionHas('success');

        $poCash->refresh();
        $this->assertEquals('lunas', $poCash->status_pembayaran);
        $this->assertTrue($poCash->isLunas());
        $this->assertEquals('cash', $poCash->payments->first()->skema);

        // 4. Verifikasi view untuk tempo dan termin
        $poTempo = $this->createTestPo(['grand_total' => 1000000, 'skema_bayar' => 'tempo']);
        $this->actingAs($this->purchasing)
            ->get(route('purchasing.show', $poTempo))
            ->assertOk()
            ->assertSee('Tempo (Jatuh Tempo Tunggal)')
            ->assertDontSee('Jadwal Termin Tagihan:');

        $poTermin = $this->createTestPo(['grand_total' => 1000000, 'skema_bayar' => 'termin']);
        $poTermin->termins()->create([
            'termin_ke' => 1,
            'tanggal_tempo' => now()->addDays(7),
            'nominal_tagihan' => 1000000,
            'nominal_dibayar' => 0,
            'status' => 'belum_dibayar',
        ]);
        $this->actingAs($this->purchasing)
            ->get(route('purchasing.show', $poTermin))
            ->assertOk()
            ->assertSee('Termin (Cicilan Bertahap)')
            ->assertSee('Jadwal Termin Tagihan:');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. TC-SEC: SECURITY, RBAC & PRIVACY PROTECTION (4 Tests)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * TC-SEC-01: Hak Akses Berbasis Peran (RBAC):
     * - Staff Gudang dilarang membuat PO (403).
     * - Staff Purchasing dilarang meng-approve PO (403).
     * - Staff Operasional dilarang mencatat pembayaran (403).
     */
    public function test_sec_01_rbac_unauthorized_roles_forbidden_from_actions(): void
    {
        $po = $this->createTestPo(['status' => 'diajukan']);

        // 1. Gudang coba buat PO -> 403
        $this->actingAs($this->gudangUser)
            ->post(route('purchasing.store'), ['supplier_id' => $this->supplier->id])
            ->assertForbidden();

        // 2. Purchasing coba approve -> 403 (Manager only)
        $this->actingAs($this->purchasing)
            ->post(route('purchasing.approve', $po))
            ->assertForbidden();

        // 3. Operasional coba catat pembayaran -> 403
        $this->actingAs($this->operasional)
            ->post(route('purchasing.pay', $po), [
                'skema' => 'tempo',
                'tanggal_bayar' => now()->toDateString(),
                'nominal' => 100000,
            ])
            ->assertForbidden();
    }

    /**
     * TC-SEC-02: Sensor Harga Gudang (Price Privacy):
     * User Gudang tanpa izin purchasing.price.view dilarang mengakses data-ap (403),
     * dan variabel canSeePrice bernilai false pada tampilan detail PO.
     */
    public function test_sec_02_price_privacy_masks_financial_data_from_warehouse_role(): void
    {
        $po = $this->createTestPo();

        $this->actingAs($this->gudangUser);

        // Akses endpoint AP ditolak
        $this->get(route('purchasing.data-ap'))
            ->assertForbidden();

        // Akses show diperbolehkan (untuk keperluan cek item/gudang), tapi canSeePrice = false
        $response = $this->get(route('purchasing.show', $po));
        $response->assertOk();
        $response->assertViewHas('canSeePrice', false);
    }

    /**
     * TC-SEC-03: Proteksi Unauthenticated:
     * Pengguna tanpa autentikasi dialihkan ke halaman login.
     */
    public function test_sec_03_unauthenticated_requests_redirect_to_login(): void
    {
        $this->get(route('purchasing.index'))
            ->assertRedirect(route('login'));

        $this->post(route('purchasing.store'), [])
            ->assertRedirect(route('login'));
    }

    /**
     * TC-SEC-04: Manager memiliki hak administratif penuh pada purchasing.
     */
    public function test_sec_04_manager_has_full_administrative_access(): void
    {
        $po = $this->createTestPo(['status' => 'diajukan']);

        $this->actingAs($this->manager);

        // Manager can approve
        $this->post(route('purchasing.approve', $po))
            ->assertSessionHas('success');

        // Manager can cancel
        $this->post(route('purchasing.cancel', $po))
            ->assertSessionHas('success');

        // Manager can view AP data
        $this->getJson(route('purchasing.data-ap'))
            ->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. TC-PERF: RELIABILITY & QUERY EFFICIENCY (NO N+1) (2 Tests)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * TC-PERF-01: Datatables PO Index dieksekusi dalam batas query budget konstan.
     * Tidak terjadi N+1 query saat memuat daftar PO.
     */
    public function test_perf_01_datatables_po_index_executes_within_constant_query_budget(): void
    {
        // Buat 10 PO tambahan
        for ($i = 0; $i < 10; $i++) {
            $this->createTestPo(['no_po' => "PO-PERF-{$i}"]);
        }

        $this->actingAs($this->purchasing);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson(route('purchasing.data'));
        $response->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Datatables harus berjalan dalam query yang sangat hemat (kurang dari 12 query total, bebas N+1)
        $this->assertLessThan(12, count($queries), "Terdeteksi potensi N+1 query pada /purchasing/data!");
    }

    /**
     * TC-PERF-02: Datatables AP Monitoring menggunakan agregasi subquery efisien.
     */
    public function test_perf_02_datatables_ap_monitoring_executes_efficient_aggregated_queries(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $po = $this->createTestPo(['no_po' => "PO-AP-{$i}"]);
            $po->payments()->create([
                'skema' => 'tempo',
                'tanggal_bayar' => now(),
                'nominal' => 250000,
            ]);
        }

        $this->actingAs($this->purchasing);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson(route('purchasing.data-ap'));
        $response->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(10, $queryCount, "Terdeteksi potensi N+1 query pada /purchasing/data-ap!");
    }

    /**
     * TC-PERF-03: Filter Datatables PO dan AP berdasarkan status_bayar dan skema.
     */
    public function test_filter_by_status_bayar_and_ap_in_datatables(): void
    {
        $poLunas = $this->createTestPo(['no_po' => 'PO-FILTER-LUNAS', 'status_pembayaran' => 'lunas', 'skema_bayar' => 'cash']);
        $poBelum = $this->createTestPo(['no_po' => 'PO-FILTER-BELUM', 'status_pembayaran' => 'belum_lunas', 'skema_bayar' => 'tempo']);

        $this->actingAs($this->purchasing);

        // 1. Verifikasi view index merender kontrol filter (filter status AP terpusat di tab AP)
        $this->get(route('purchasing.index'))
            ->assertOk()
            ->assertSee('id="filter-status-po"', false)
            ->assertSee('id="filter-status-bayar-ap"', false)
            ->assertSee('id="btn-reset-po"', false)
            ->assertSee('id="btn-reset-ap"', false);

        // 2. Filter data PO by status_bayar=lunas
        $resLunas = $this->getJson(route('purchasing.data', ['status_bayar' => 'lunas']));
        $resLunas->assertOk();
        $this->assertStringContainsString('PO-FILTER-LUNAS', json_encode($resLunas->json()));
        $this->assertStringNotContainsString('PO-FILTER-BELUM', json_encode($resLunas->json()));

        // 3. Filter data PO by status_bayar=belum_lunas
        $resBelum = $this->getJson(route('purchasing.data', ['status_bayar' => 'belum_lunas']));
        $resBelum->assertOk();
        $this->assertStringContainsString('PO-FILTER-BELUM', json_encode($resBelum->json()));
        $this->assertStringNotContainsString('PO-FILTER-LUNAS', json_encode($resBelum->json()));

        // 4. Filter data AP by status_bayar=lunas
        $resApLunas = $this->getJson(route('purchasing.data-ap', ['status_bayar' => 'lunas']));
        $resApLunas->assertOk();
        $this->assertStringContainsString('PO-FILTER-LUNAS', json_encode($resApLunas->json()));
        $this->assertStringNotContainsString('PO-FILTER-BELUM', json_encode($resApLunas->json()));

        // 5. Filter data AP by skema_bayar=tempo
        $resApTempo = $this->getJson(route('purchasing.data-ap', ['skema_bayar' => 'tempo']));
        $resApTempo->assertOk();
        $this->assertStringContainsString('PO-FILTER-BELUM', json_encode($resApTempo->json()));
        $this->assertStringNotContainsString('PO-FILTER-LUNAS', json_encode($resApTempo->json()));
    }

    /**
     * TC-PERF-04: Konsistensi sisa tagihan antara index (DataTables) dan view show.
     */
    public function test_sisa_tagihan_data_consistency_between_index_and_show(): void
    {
        // PO Belum Lunas (Total: 3.500.000, Dibayar: 0, Sisa: 3.500.000)
        $poBelum = $this->createTestPo([
            'no_po' => 'PO-CONSIST-01',
            'grand_total' => 3500000,
            'status_pembayaran' => 'belum_lunas',
        ]);

        // PO Parsial (Total: 4.000.000, Dibayar: 1.500.000, Sisa: 2.500.000)
        $poParsial = $this->createTestPo([
            'no_po' => 'PO-CONSIST-02',
            'grand_total' => 4000000,
            'status_pembayaran' => 'parsial',
        ]);
        $poParsial->payments()->create([
            'skema' => 'tempo',
            'tanggal_bayar' => now(),
            'nominal' => 1500000,
        ]);

        $this->actingAs($this->purchasing);

        // 1. Verifikasi show view menghasilkan sisa tagihan yang tepat
        $this->get(route('purchasing.show', $poBelum))
            ->assertOk()
            ->assertSee('3.500.000');

        $this->get(route('purchasing.show', $poParsial))
            ->assertOk()
            ->assertSee('2.500.000');

        // 2. Verifikasi DataTables /purchasing/data menghasilkan sisa tagihan yang sama persis
        $resData = $this->getJson(route('purchasing.data'));
        $resData->assertOk();
        $items = collect($resData->json('data'));

        $rowBelum = $items->firstWhere('no_po', 'PO-CONSIST-01');
        $this->assertNotNull($rowBelum);
        $this->assertEquals('3.500.000', $rowBelum['sisa']);
        $this->assertEquals(number_format($poBelum->sisaTagihan(), 0, ',', '.'), $rowBelum['sisa']);

        $rowParsial = $items->firstWhere('no_po', 'PO-CONSIST-02');
        $this->assertNotNull($rowParsial);
        $this->assertEquals('2.500.000', $rowParsial['sisa']);
        $this->assertEquals(number_format($poParsial->sisaTagihan(), 0, ',', '.'), $rowParsial['sisa']);

        // 3. Verifikasi DataTables /purchasing/data-ap juga konsisten
        $resAp = $this->getJson(route('purchasing.data-ap'));
        $resAp->assertOk();
        $itemsAp = collect($resAp->json('data'));

        $rowApBelum = $itemsAp->firstWhere('no_po', 'PO-CONSIST-01');
        $this->assertNotNull($rowApBelum);
        $this->assertEquals('3.500.000', $rowApBelum['sisa']);

        $rowApParsial = $itemsAp->firstWhere('no_po', 'PO-CONSIST-02');
        $this->assertNotNull($rowApParsial);
        $this->assertEquals('2.500.000', $rowApParsial['sisa']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. TC-EDIT: PO EDIT & UPDATE LIFECYCLE (Draft & Approval Stages)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * TC-EDIT-01: Verifikasi staf purchasing dapat mengakses halaman edit PO
     * pada status 'draft', 'diajukan', dan 'disetujui' (sebelum barang tiba).
     */
    public function test_edit_01_purchasing_can_access_edit_page_for_draft_diajukan_and_disetujui_po(): void
    {
        $this->actingAs($this->purchasing);

        // 1. Status Draft
        $poDraft = $this->createTestPo(['status' => 'draft']);
        $resDraft = $this->get(route('purchasing.edit', $poDraft));
        $resDraft->assertOk();
        $resDraft->assertViewIs('purchasing.edit');
        $resDraft->assertSee($poDraft->no_po);

        // 2. Status Diajukan (menunggu approval manager)
        $poDiajukan = $this->createTestPo(['status' => 'diajukan']);
        $resDiajukan = $this->get(route('purchasing.edit', $poDiajukan));
        $resDiajukan->assertOk();
        $resDiajukan->assertViewIs('purchasing.edit');
        $resDiajukan->assertSee($poDiajukan->no_po);

        // 3. Status Disetujui (sebelum barang datang fisik)
        $poDisetujui = $this->createTestPo(['status' => 'disetujui']);
        $resDisetujui = $this->get(route('purchasing.edit', $poDisetujui));
        $resDisetujui->assertOk();
        $resDisetujui->assertViewIs('purchasing.edit');
        $resDisetujui->assertSee($poDisetujui->no_po);
    }

    /**
     * TC-EDIT-02: Verifikasi update item PO, kalkulasi biaya global dan prorata HPP.
     */
    public function test_edit_02_purchasing_can_update_po_items_and_recalculate_totals_and_prorated_hpp(): void
    {
        $this->actingAs($this->purchasing);

        $po = $this->createTestPo(['status' => 'diajukan', 'grand_total' => 1000000]);

        $produkBahan = Produk::bahan()->active()->take(2)->get();
        $p1 = $produkBahan[0];
        $p2 = $produkBahan[1];

        $payload = [
            'no_invoice' => 'INV-EDITED-999',
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudangPusat->id,
            'tanggal' => now()->toDateString(),
            'eta' => now()->addDays(5)->toDateString(),
            'sumber_dana' => 'BCA Operasional',
            'skema_bayar' => 'cash',
            'diskon_total' => 50000,
            'ppn_nominal' => 100000,
            'ongkos_kirim' => 150000,
            'adjustment' => 0,
            'items' => [
                ['produk_id' => $p1->id, 'qty' => 10, 'harga_total' => 1000000],
                ['produk_id' => $p2->id, 'qty' => 20, 'harga_total' => 2000000],
            ],
        ];

        $res = $this->put(route('purchasing.update', $po), $payload);
        $res->assertRedirect(route('purchasing.show', $po));
        $res->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals('INV-EDITED-999', $po->no_invoice);
        $this->assertEquals(3000000, (float) $po->subtotal_produk);
        $this->assertEquals(50000, (float) $po->diskon_total);
        $this->assertEquals(100000, (float) $po->ppn_nominal);
        $this->assertEquals(150000, (float) $po->ongkos_kirim);
        $this->assertEquals(3200000, (float) $po->grand_total);

        // Verifikasi items diganti dan HPP dialokasikan secara prorata
        $items = $po->items;
        $this->assertCount(2, $items);

        $it1 = $items->firstWhere('produk_id', $p1->id);
        $it2 = $items->firstWhere('produk_id', $p2->id);

        $this->assertNotNull($it1);
        $this->assertNotNull($it2);

        // Porsi P1 = 1/3, Net = 1.000.000 - 16.666,67 + 33.333,33 + 50.000 = 1.066.666,67
        // HPP P1 = 1.066.666,67 / 10 = 106.666,67
        $this->assertEquals(10, (float) $it1->qty);
        $this->assertEquals(1000000, (float) $it1->harga_total);
        $this->assertGreaterThan(100000, (float) $it1->hpp_per_satuan);

        // Total net items = grand total
        $this->assertEquals(3200000, round($it1->netTotal() + $it2->netTotal(), 2));
    }

    /**
     * TC-EDIT-03: Update skema bayar ke termin dan sinkronisasi jadwal cicilan.
     */
    public function test_edit_03_purchasing_can_update_skema_bayar_and_termins(): void
    {
        $this->actingAs($this->purchasing);

        $po = $this->createTestPo(['status' => 'draft', 'skema_bayar' => 'cash']);
        $p = Produk::bahan()->active()->firstOrFail();

        $payload = [
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudangPusat->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'termin',
            'items' => [
                ['produk_id' => $p->id, 'qty' => 50, 'harga_total' => 2000000],
            ],
            'termins' => [
                [
                    'tanggal_tempo' => now()->addDays(14)->toDateString(),
                    'nominal_tagihan' => 1000000,
                    'keterangan' => 'Termin 1 (DP 50%)',
                ],
                [
                    'tanggal_tempo' => now()->addDays(30)->toDateString(),
                    'nominal_tagihan' => 1000000,
                    'keterangan' => 'Termin 2 (Pelunasan 50%)',
                ],
            ],
        ];

        $res = $this->put(route('purchasing.update', $po), $payload);
        $res->assertRedirect(route('purchasing.show', $po));

        $po->refresh();
        $this->assertEquals('termin', $po->skema_bayar);
        $this->assertCount(2, $po->termins);
        $this->assertEquals(2000000, (float) $po->termins()->sum('nominal_tagihan'));
    }

    /**
     * TC-EDIT-04: PO tidak dapat diedit jika barang sudah datang atau status final (selesai/dibatalkan).
     */
    public function test_edit_04_cannot_edit_po_if_goods_already_received_or_status_final(): void
    {
        $this->actingAs($this->purchasing);

        // 1. PO Selesai
        $poSelesai = $this->createTestPo(['status' => 'selesai']);
        $this->get(route('purchasing.edit', $poSelesai))
            ->assertRedirect(route('purchasing.show', $poSelesai))
            ->assertSessionHas('error');

        $resPut = $this->put(route('purchasing.update', $poSelesai), [
            'supplier_id' => $this->supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [['produk_id' => 1, 'qty' => 1, 'harga_total' => 100]],
        ]);
        $resPut->assertSessionHasErrors(['status']);
    }

    /**
     * TC-EDIT-05: Verifikasi PO dengan barang datang fisik ditolak untuk edit.
     */
    public function test_edit_05_cannot_edit_po_with_existing_barang_datang(): void
    {
        $this->actingAs($this->purchasing);

        $po = $this->createTestPo(['status' => 'disetujui']);

        // Simulasikan barang datang
        BarangDatang::create([
            'po_id' => $po->id,
            'gudang_id' => $this->gudangPusat->id,
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'created_by' => $this->gudangUser->id,
        ]);

        $this->get(route('purchasing.edit', $po))
            ->assertRedirect(route('purchasing.show', $po))
            ->assertSessionHas('error');

        $resPut = $this->put(route('purchasing.update', $po), [
            'supplier_id' => $this->supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [['produk_id' => 1, 'qty' => 1, 'harga_total' => 100]],
        ]);
        $resPut->assertSessionHasErrors(['status']);
    }

    /**
     * TC-EDIT-06: Safeguard integritas keuangan - grand total baru tidak boleh lebih kecil dari total yang sudah dibayar.
     */
    public function test_edit_06_cannot_reduce_grand_total_below_paid_amount(): void
    {
        $this->actingAs($this->purchasing);

        $po = $this->createTestPo(['status' => 'diajukan', 'grand_total' => 2000000, 'skema_bayar' => 'tempo']);

        // Simulasikan pembayaran DP Rp 1.500.000 (skema tempo mengizinkan pembayaran parsial)
        $payRes = $this->post(route('purchasing.pay', $po), [
            'skema' => 'tempo',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 1500000,
        ]);
        $payRes->assertSessionHasNoErrors();
        $this->assertEquals(1500000, (float) $po->fresh()->totalDibayar());

        $p = Produk::bahan()->active()->firstOrFail();

        // Coba turunkan total PO menjadi Rp 1.000.000 (lebih kecil dari Rp 1.500.000)
        $payload = [
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudangPusat->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'cash',
            'items' => [
                ['produk_id' => $p->id, 'qty' => 10, 'harga_total' => 1000000],
            ],
        ];

        $resPut = $this->put(route('purchasing.update', $po), $payload);
        $resPut->assertSessionHasErrors(['items']);
    }

    /**
     * TC-EDIT-07: Verifikasi RBAC - User tanpa izin purchasing.edit ditolak (403).
     */
    public function test_edit_07_unauthorized_user_cannot_access_edit_or_update(): void
    {
        $po = $this->createTestPo(['status' => 'draft']);

        // Gudang user tidak punya purchasing.edit
        $this->actingAs($this->gudangUser);

        $this->get(route('purchasing.edit', $po))->assertForbidden();
        $this->put(route('purchasing.update', $po), [])->assertForbidden();
    }

    /**
     * TC-EDIT-08: Verifikasi DataTables dan Show Page menampilkan aksi edit secara dinamis.
     */
    public function test_edit_08_datatables_and_show_views_render_edit_actions_only_for_eligible_pos(): void
    {
        $this->actingAs($this->purchasing);

        $poDraft = $this->createTestPo(['no_po' => 'PO-EDIT-DRAFT', 'status' => 'draft']);
        $poFinal = $this->createTestPo(['no_po' => 'PO-EDIT-FINAL', 'status' => 'selesai']);

        // 1. DataTables endpoint
        $res = $this->getJson(route('purchasing.data'));
        $res->assertOk();

        $rows = collect($res->json('data'));
        $rowDraft = $rows->firstWhere('no_po', 'PO-EDIT-DRAFT');
        $rowFinal = $rows->firstWhere('no_po', 'PO-EDIT-FINAL');

        $this->assertNotNull($rowDraft);
        $this->assertStringContainsString(route('purchasing.edit', $poDraft), $rowDraft['action']);

        $this->assertNotNull($rowFinal);
        $this->assertStringNotContainsString(route('purchasing.edit', $poFinal), $rowFinal['action']);

        // 2. Show Page view
        $showDraft = $this->get(route('purchasing.show', $poDraft));
        $showDraft->assertOk();
        $showDraft->assertSee(route('purchasing.edit', $poDraft));

        $showFinal = $this->get(route('purchasing.show', $poFinal));
        $showFinal->assertOk();
        $showFinal->assertDontSee(route('purchasing.edit', $poFinal));
    }

    /**
     * TC-SHOW-01: Verifikasi tampilan HPP / Unit di halaman purchasing.show
     * Presisi desimal ditampilkan secara akurat (bulat, 2 desimal, hingga 4 desimal mikro).
     */
    public function test_show_view_renders_hpp_accurately_with_decimal_places(): void
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-SHOW-HPP-TEST',
            'no_invoice' => 'INV/TEST/HPP',
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudangPusat->id,
            'tanggal' => now()->toDateString(),
            'status' => 'draft',
            'status_pembayaran' => 'belum_lunas',
            'skema_bayar' => 'cash',
            'subtotal_produk' => 372635,
            'grand_total' => 372635,
            'created_by' => $this->purchasing->id,
        ]);

        $item1 = Produk::where('sku', 'ALK-01')->firstOrFail();
        $item2 = Produk::where('sku', 'OIL-GOH')->firstOrFail();
        $item3 = Produk::where('sku', 'BTL-P50')->firstOrFail();

        // Item 1: HPP dengan 2 desimal (Rp 526,35)
        $po->items()->create([
            'produk_id' => $item1->id,
            'qty' => 100,
            'harga_total' => 52635,
            'hpp_per_satuan' => 526.35,
        ]);

        // Item 2: HPP mikro dengan 4 desimal (Rp 106.666,6667)
        $po->items()->create([
            'produk_id' => $item2->id,
            'qty' => 3,
            'harga_total' => 320000,
            'hpp_per_satuan' => 106666.6667,
        ]);

        // Item 3: HPP bulat murni tanpa pecahan (Rp 15.000)
        $po->items()->create([
            'produk_id' => $item3->id,
            'qty' => 10,
            'harga_total' => 150000,
            'hpp_per_satuan' => 15000,
        ]);

        $res = $this->actingAs($this->purchasing)->get(route('purchasing.show', $po));
        $res->assertOk();

        // Pastikan HPP tertera dengan format angka desimal yang benar
        $res->assertSee('Rp 526,35');
        $res->assertSee('Rp 106.666,6667');
        $res->assertSee('Rp 15.000');
        $res->assertDontSee('Rp 15.000,00');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TEST HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    protected function createTestPo(array $attributes = []): PurchaseOrder
    {
        $item = Produk::bahan()->active()->firstOrFail();

        $defaultTotal = 1000000;
        $grandTotal = $attributes['grand_total'] ?? $defaultTotal;

        $po = PurchaseOrder::create(array_merge([
            'no_po' => 'PO-' . now()->year . '-' . sprintf('%04d', mt_rand(1, 9999)),
            'no_invoice' => 'INV/' . mt_rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudangPusat->id,
            'tanggal' => now()->toDateString(),
            'eta' => now()->addDays(7)->toDateString(),
            'sumber_dana' => 'BCA Operasional',
            'skema_bayar' => 'cash',
            'subtotal_produk' => $grandTotal,
            'diskon_total' => 0,
            'ppn_nominal' => 0,
            'ongkos_kirim' => 0,
            'adjustment' => 0,
            'grand_total' => $grandTotal,
            'status' => 'draft',
            'status_pembayaran' => 'belum_lunas',
            'created_by' => $this->purchasing->id,
        ], $attributes));

        $po->items()->create([
            'produk_id' => $item->id,
            'qty' => 100,
            'harga_total' => $grandTotal,
            'diskon' => 0,
            'ppn' => 0,
            'ongkir' => 0,
            'adjustment' => 0,
            'hpp_per_satuan' => round($grandTotal / 100, 4),
        ]);

        return $po->fresh(['items', 'supplier', 'gudang']);
    }
}
