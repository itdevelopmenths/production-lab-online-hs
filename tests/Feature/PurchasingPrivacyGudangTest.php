<?php

namespace Tests\Feature;

use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingPrivacyGudangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
        $this->seed();
    }

    public function test_gudang_role_cannot_view_prices_in_purchasing_index_and_data_response(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $supplier = Supplier::firstOrFail();
        $item = Produk::bahan()->firstOrFail();

        $po = PurchaseOrder::create([
            'no_po' => 'PO-PRIVACY-001',
            'no_invoice' => 'INV-PRIVACY-001',
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'grand_total' => 2500000,
            'status' => 'dikirim_ke_gudang',
            'created_by' => $purchasing->id,
        ]);
        $po->items()->create([
            'produk_id' => $item->id,
            'qty' => 100,
            'harga_total' => 2500000,
            'hpp_per_satuan' => 25000,
        ]);

        // 1. Gudang accesses DataTables API -> price fields must be masked as '—'
        $this->actingAs($gudang);
        $res = $this->getJson(route('purchasing.data'));
        $res->assertOk();
        $data = $res->json('data');

        $found = collect($data)->firstWhere('no_po', 'PO-PRIVACY-001');
        $this->assertNotNull($found);
        $this->assertEquals('—', $found['total_nilai']);
        $this->assertEquals('—', $found['sisa']);
        $this->assertEquals('—', $found['status_pembayaran']);

        // 2. Gudang attempts to access AP tab endpoint -> Forbidden 403
        $this->get(route('purchasing.data-ap'))->assertForbidden();

        // 3. Gudang views show page -> prices and payment form must be hidden
        $showRes = $this->get(route('purchasing.show', $po));
        $showRes->assertOk();
        $showRes->assertDontSee('Status Pembayaran (AP)');
        $showRes->assertDontSee('Catat Pembayaran');
        $showRes->assertDontSee('2.500.000');
        $showRes->assertSee('Konfirmasi Barang Datang di Gudang');

        // 4. Purchasing accesses same show page -> prices and payment form must be visible
        $this->actingAs($purchasing);
        $purchasingRes = $this->get(route('purchasing.show', $po));
        $purchasingRes->assertOk();
        $purchasingRes->assertSee('Status Pembayaran (AP)');
        $purchasingRes->assertSee('Catat Pembayaran');
        $purchasingRes->assertSee('2.500.000');
    }
}
