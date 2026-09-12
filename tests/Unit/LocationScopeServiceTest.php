<?php

namespace Tests\Unit;

use App\Models\Gudang;
use App\Models\User;
use App\Services\Authorization\LocationScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationScopeServiceTest extends TestCase
{
    use RefreshDatabase;

    private LocationScopeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->service = new LocationScopeService();
    }

    public function test_manager_has_global_warehouse_access(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();

        $this->assertNull($this->service->getAccessibleWarehouseIds($manager));
        $this->assertTrue($this->service->isGlobal($manager));

        $anyWarehouse = Gudang::firstOrFail();
        $this->assertTrue($this->service->canAccessWarehouse($manager, $anyWarehouse->id));
    }

    public function test_user_with_explicit_gudang_assignment(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['gudang']);

        $gPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $user->gudangs()->attach($gPusat->id, ['is_primary' => true]);

        $accessible = $this->service->getAccessibleWarehouseIds($user);
        $this->assertIsArray($accessible);
        $this->assertCount(1, $accessible);
        $this->assertContains($gPusat->id, $accessible);
        $this->assertTrue($this->service->canAccessWarehouse($user, $gPusat->id));

        $gOps = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $this->assertFalse($this->service->canAccessWarehouse($user, $gOps->id));
    }

    public function test_role_fallback_scoping_when_no_explicit_pivot_rows(): void
    {
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $operasionalUser = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $fulfillmentUser = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();

        // 1. Gudang user -> tipe: bahan_baku
        $gudangIds = $this->service->getAccessibleWarehouseIds($gudangUser);
        $this->assertIsArray($gudangIds);
        $gBahan = Gudang::where('tipe', 'bahan_baku')->firstOrFail();
        $this->assertContains($gBahan->id, $gudangIds);

        // 2. Operasional user -> tipe: operasional
        $opsIds = $this->service->getAccessibleWarehouseIds($operasionalUser);
        $this->assertIsArray($opsIds);
        $gOps = Gudang::where('tipe', 'operasional')->firstOrFail();
        $this->assertContains($gOps->id, $opsIds);

        // 3. Fulfillment user -> fulfillment warehouses
        $ffIds = $this->service->getAccessibleWarehouseIds($fulfillmentUser);
        $this->assertIsArray($ffIds);
        $ffPusat = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();
        $this->assertContains($ffPusat->id, $ffIds);
    }
}
