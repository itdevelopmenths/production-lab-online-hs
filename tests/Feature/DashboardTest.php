<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\RequestTransfer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_dashboard_displays_stats_and_item_perlu_order(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $response = $this->get('/');
        $response->assertStatus(200);

        // Memastikan kartu Item Perlu Order muncul dengan nilai terhitung
        $response->assertSee('Item Perlu Order');
        $response->assertSee('Analisa Stok');
    }

    public function test_dashboard_filters_actionable_items_by_role(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $supplier = Supplier::firstOrFail();

        // 1. PO berstatus 'diajukan' (menunggu approval Manager)
        $poDiajukan = PurchaseOrder::create([
            'no_po' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'tanggal' => now(),
            'status' => 'diajukan',
            'created_by' => $purchasing->id,
        ]);

        // 2. PO berstatus 'dikirim_ke_gudang' (menunggu konfirmasi Gudang)
        $poGudang = PurchaseOrder::create([
            'no_po' => 'PO-TEST-002',
            'supplier_id' => $supplier->id,
            'tanggal' => now(),
            'status' => 'dikirim_ke_gudang',
            'created_by' => $purchasing->id,
        ]);

        // Login sebagai Manager: harus melihat PO-TEST-001 yang menunggu approval
        $this->actingAs($manager);
        $resManager = $this->get('/');
        $resManager->assertStatus(200);
        $resManager->assertSee('PO-TEST-001');

        // Login sebagai Gudang: harus melihat PO-TEST-002 yang siap diterima
        $this->actingAs($gudang);
        $resGudang = $this->get('/');
        $resGudang->assertStatus(200);
        $resGudang->assertSee('PO-TEST-002');
    }
}
