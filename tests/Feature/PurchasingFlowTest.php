<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ISO/IEC 25010 Quality Assurance:
 * Purchasing Flow Scenarios from Worst-Case (Negative/Edge) to Best-Case (Golden Flow).
 */
class PurchasingFlowTest extends TestCase
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

    public function test_best_case_full_purchasing_lifecycle_from_draft_to_received_and_paid(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $supplier = Supplier::active()->firstOrFail();
        $items = Produk::bahan()->active()->take(2)->get();
        $this->assertCount(2, $items);

        $gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();

        // 1. Purchasing creates draft multi-item PO
        $this->actingAs($purchasing);

        $poData = [
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'eta' => now()->addDays(7)->toDateString(),
            'sumber_dana' => 'BCA Operasional',
            'items' => [
                [
                    'produk_id' => $items[0]->id,
                    'qty' => 100,
                    'harga_total' => 1000000,
                ],
                [
                    'produk_id' => $items[1]->id,
                    'qty' => 50,
                    'harga_total' => 500000,
                ],
            ],
        ];

        $response = $this->post(route('purchasing.store'), $poData);
        $po = PurchaseOrder::latest()->firstOrFail();
        $response->assertRedirect(route('purchasing.show', $po));
        $this->assertEquals('draft', $po->status);
        $this->assertCount(2, $po->items);

        // 2. Purchasing submits PO to Manager
        $this->post(route('purchasing.submit', $po))->assertSessionHas('success');
        $po->refresh();
        $this->assertEquals('diajukan', $po->status);

        // 3. Manager approves PO -> status becomes dikirim_ke_gudang
        $this->actingAs($manager);
        $this->post(route('purchasing.approve', $po))->assertSessionHas('success');
        $po->refresh();
        $this->assertEquals('dikirim_ke_gudang', $po->status);

        // 4. Gudang receives the goods at Gudang Pusat
        $this->actingAs($gudangUser);

        $stokService = app(StokService::class);
        $initialStock1 = $stokService->saldo($items[0]->id, $gudangPusat->id);
        $initialStock2 = $stokService->saldo($items[1]->id, $gudangPusat->id);

        $poItem1 = $po->items[0];
        $poItem2 = $po->items[1];

        $receiveData = [
            'gudang_id' => $gudangPusat->id,
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'items' => [
                [
                    'po_item_id' => $poItem1->id,
                    'qty_diterima' => 100,
                ],
                [
                    'po_item_id' => $poItem2->id,
                    'qty_diterima' => 50,
                ],
            ],
        ];

        $this->post(route('purchasing.receive', $po), $receiveData)->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals('selesai', $po->status);
        $this->assertCount(1, $po->barangDatang);

        // Verify stock increases in Gudang Pusat
        $newStock1 = $stokService->saldo($items[0]->id, $gudangPusat->id);
        $newStock2 = $stokService->saldo($items[1]->id, $gudangPusat->id);

        $this->assertEquals($initialStock1 + 100, $newStock1);
        $this->assertEquals($initialStock2 + 50, $newStock2);

        // Verify Kartu Stok has audit trail
        $kartu1 = KartuStok::where('produk_id', $items[0]->id)
            ->where('gudang_id', $gudangPusat->id)
            ->where('referensi_tipe', 'purchase_order')
            ->latest('id')
            ->first();
        $this->assertNotNull($kartu1);
        $this->assertEquals('in', $kartu1->tipe);
        $this->assertEquals(100, (float) $kartu1->qty);

        // 5. Purchasing records payments (termin & pelunasan)
        $this->actingAs($purchasing);

        $this->post(route('purchasing.pay', $po), [
            'skema' => 'termin',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 500000,
        ])->assertSessionHas('success');
        $this->assertFalse($po->isLunas());

        $this->post(route('purchasing.pay', $po), [
            'skema' => 'pelunasan',
            'tanggal_bayar' => now()->addDays(5)->toDateString(),
            'nominal' => 1000000,
        ])->assertSessionHas('success');

        $po->refresh();
        $this->assertTrue($po->isLunas());
        $this->assertEquals(0, $po->sisaTagihan());
    }

    // ==========================================
    // 🔍 2. BOUNDARY / EDGE-CASE SCENARIOS
    // ==========================================

    public function test_boundary_case_decimal_precision_and_split_payments(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $supplier = Supplier::active()->firstOrFail();
        $item = Produk::bahan()->active()->firstOrFail();

        $this->actingAs($purchasing);

        // Fractional quantity with 2 decimals
        $response = $this->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [
                ['produk_id' => $item->id, 'qty' => 12.75, 'harga_total' => 637500],
            ],
        ]);
        $po = PurchaseOrder::latest()->firstOrFail();
        $this->assertEquals(12.75, (float) $po->items->first()->qty);
        $this->assertEquals(50000, $po->items->first()->hargaPerSatuan());

        // Split payments in 3 installments
        $this->post(route('purchasing.pay', $po), [
            'skema' => 'termin',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 200000,
        ]);
        $this->assertEquals(437500, $po->sisaTagihan());

        $this->post(route('purchasing.pay', $po), [
            'skema' => 'termin',
            'tanggal_bayar' => now()->addDay()->toDateString(),
            'nominal' => 237500,
        ]);
        $this->assertEquals(200000, $po->sisaTagihan());

        $this->post(route('purchasing.pay', $po), [
            'skema' => 'pelunasan',
            'tanggal_bayar' => now()->addDays(2)->toDateString(),
            'nominal' => 200000,
        ]);
        $po->refresh();
        $this->assertTrue($po->isLunas());
        $this->assertEquals(0, $po->sisaTagihan());
    }

    // ==========================================
    // ⚠️ 3. WORST-CASE / NEGATIVE SCENARIOS
    // ==========================================

    public function test_worst_case_negative_and_zero_quantity_rejected(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $supplier = Supplier::active()->firstOrFail();
        $item = Produk::bahan()->active()->firstOrFail();

        $this->actingAs($purchasing);

        // Zero quantity
        $response = $this->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [
                ['produk_id' => $item->id, 'qty' => 0, 'harga_total' => 100000],
            ],
        ]);
        $response->assertSessionHasErrors('items.0.qty');

        // Negative quantity
        $response2 = $this->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [
                ['produk_id' => $item->id, 'qty' => -5, 'harga_total' => 100000],
            ],
        ]);
        $response2->assertSessionHasErrors('items.0.qty');

        // Negative total price
        $response3 = $this->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [
                ['produk_id' => $item->id, 'qty' => 10, 'harga_total' => -500],
            ],
        ]);
        $response3->assertSessionHasErrors('items.0.harga_total');
    }

    public function test_worst_case_duplicate_items_in_same_po_rejected(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $supplier = Supplier::active()->firstOrFail();
        $item = Produk::bahan()->active()->firstOrFail();

        $this->actingAs($purchasing);

        // Duplicate item
        $response = $this->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [
                ['produk_id' => $item->id, 'qty' => 10, 'harga_total' => 100000],
                ['produk_id' => $item->id, 'qty' => 20, 'harga_total' => 200000],
            ],
        ]);
        $response->assertSessionHasErrors('items.0.produk_id');
    }

    public function test_worst_case_nonexistent_foreign_keys_rejected(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->actingAs($purchasing);

        // Non-existent supplier
        $response = $this->post(route('purchasing.store'), [
            'supplier_id' => 999999,
            'tanggal' => now()->toDateString(),
            'items' => [
                ['produk_id' => 1, 'qty' => 10, 'harga_total' => 100000],
            ],
        ]);
        $response->assertSessionHasErrors('supplier_id');

        // Non-existent product
        $supplier = Supplier::active()->firstOrFail();
        $response2 = $this->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [
                ['produk_id' => 999999, 'qty' => 10, 'harga_total' => 100000],
            ],
        ]);
        $response2->assertSessionHasErrors('items.0.produk_id');
    }

    public function test_worst_case_invalid_status_transitions_abort(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $supplier = Supplier::active()->firstOrFail();
        $item = Produk::bahan()->active()->firstOrFail();
        $gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();

        $this->actingAs($purchasing);
        $this->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [
                ['produk_id' => $item->id, 'qty' => 10, 'harga_total' => 100000],
            ],
        ]);
        $po = PurchaseOrder::latest()->firstOrFail();

        // Cannot approve draft directly
        $this->actingAs($manager);
        $this->post(route('purchasing.approve', $po))->assertStatus(422);

        // Cannot receive draft directly
        $this->actingAs($gudangUser);
        $this->post(route('purchasing.receive', $po), [
            'gudang_id' => $gudangPusat->id,
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'items' => [
                ['po_item_id' => $po->items[0]->id, 'qty_diterima' => 10],
            ],
        ])->assertStatus(422);
    }

    public function test_worst_case_cannot_cancel_already_completed_po(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $supplier = Supplier::active()->firstOrFail();

        $po = PurchaseOrder::create([
            'no_po' => 'PO-COMPLETED-01',
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'status' => 'selesai',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager);
        $response = $this->post(route('purchasing.cancel', $po));
        $response->assertStatus(422);

        $po->refresh();
        $this->assertEquals('selesai', $po->status);
    }

    // ==========================================
    // 🛡️ 4. LAZY LOADING IMMUNITY & RENDERING
    // ==========================================

    public function test_purchasing_show_view_renders_without_lazy_loading_violation(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $supplier = Supplier::active()->firstOrFail();
        $item = Produk::bahan()->active()->firstOrFail();

        $this->actingAs($purchasing);
        $this->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [
                ['produk_id' => $item->id, 'qty' => 10, 'harga_total' => 100000],
            ],
        ]);
        $po = PurchaseOrder::latest()->firstOrFail();

        $response = $this->get(route('purchasing.show', $po));
        $response->assertOk();
        $response->assertSee($po->no_po);
    }
}
