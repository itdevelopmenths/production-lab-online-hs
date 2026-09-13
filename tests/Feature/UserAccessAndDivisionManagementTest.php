<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\LocationScopeService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserAccessAndDivisionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->adminUser = User::factory()->create(['email' => 'admin_test@heavenscent.id', 'divisi' => 'manajemen']);
        $this->adminUser->assignRole('manager');
    }

    public function test_user_can_be_created_with_division_and_restricted_warehouses(): void
    {
        $gudangA = Gudang::create([
            'kode' => 'WH-TEST-A',
            'nama' => 'Gudang Bahan Mentah A',
            'tipe' => 'bahan_baku',
            'status' => 'aktif',
        ]);

        $gudangB = Gudang::create([
            'kode' => 'WH-TEST-B',
            'nama' => 'Gudang Bahan Mentah B',
            'tipe' => 'bahan_baku',
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'name' => 'Staf Gudang Khusus',
            'email' => 'staf_khusus@heavenscent.id',
            'divisi' => 'gudang',
            'role' => 'gudang',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'warehouse_access_type' => 'restricted',
            'gudang_ids' => [$gudangA->id, $gudangB->id],
            'primary_gudang_id' => $gudangB->id,
            'permissions' => ['rt.approve'], // Direct custom permission
        ]);

        $response->assertRedirect(route('users.index'));

        $user = User::where('email', 'staf_khusus@heavenscent.id')->firstOrFail();
        $this->assertEquals('gudang', $user->divisi);
        $this->assertEquals('Gudang & Logistik', $user->divisiLabel());
        $this->assertTrue($user->hasRole('gudang'));

        // Verify warehouse assignment
        $this->assertCount(2, $user->gudangs);
        $this->assertEquals($gudangB->id, $user->primaryGudang()->id);

        // Verify direct permission
        $this->assertTrue($user->can('rt.approve'));

        // Verify LocationScopeService limits access strictly to Gudang A and B
        $scopeService = app(LocationScopeService::class);
        $allowedIds = $scopeService->getAccessibleWarehouseIds($user);
        $this->assertNotNull($allowedIds);
        $this->assertEqualsCanonicalizing([$gudangA->id, $gudangB->id], $allowedIds);
        $this->assertTrue($scopeService->canAccessWarehouse($user, $gudangA->id));
        $this->assertTrue($scopeService->canAccessWarehouse($user, $gudangB->id));
        $this->assertFalse($scopeService->canAccessWarehouse($user, 99999));
    }

    public function test_user_created_with_global_access_has_no_restricted_warehouses(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'name' => 'Auditor Logistik',
            'email' => 'auditor@heavenscent.id',
            'divisi' => 'manajemen',
            'role' => 'gudang',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'warehouse_access_type' => 'global',
            'permissions' => ['stok.view.all'], // Direct permission to view all locations
        ]);

        $response->assertRedirect(route('users.index'));

        $user = User::where('email', 'auditor@heavenscent.id')->firstOrFail();
        $this->assertCount(0, $user->gudangs);
        $this->assertTrue($user->can('stok.view.all'));

        $scopeService = app(LocationScopeService::class);
        $allowedIds = $scopeService->getAccessibleWarehouseIds($user);
        $this->assertNull($allowedIds, 'Global user with stok.view.all must have unrestricted warehouse scope (null)');
    }

    public function test_user_created_with_default_all_gudang_has_unrestricted_scope_without_special_permission(): void
    {
        // Admin creates a normal gudang staff with DEFAULT global access and NO special permissions
        $response = $this->actingAs($this->adminUser)->post(route('users.store'), [
            'name' => 'Staf Gudang Global Default',
            'email' => 'gudang_global_default@heavenscent.id',
            'divisi' => 'gudang',
            'role' => 'gudang',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'warehouse_access_type' => 'global',
        ]);

        $response->assertRedirect(route('users.index'));

        $user = User::where('email', 'gudang_global_default@heavenscent.id')->firstOrFail();
        $this->assertEquals('global', $user->warehouse_access_type);
        $this->assertTrue($user->isGlobalWarehouseAccess());

        $scopeService = app(LocationScopeService::class);
        $allowedIds = $scopeService->getAccessibleWarehouseIds($user);
        $this->assertNull($allowedIds, 'Default all-gudang user must have global unrestricted access (null)');
        $this->assertTrue($scopeService->isGlobal($user));
    }

    public function test_user_update_allows_switching_access_and_updating_division(): void
    {
        $gudang = Gudang::create([
            'kode' => 'WH-TEST-C',
            'nama' => 'Gudang Lab Testing',
            'tipe' => 'operasional',
            'status' => 'aktif',
        ]);

        $user = User::factory()->create([
            'email' => 'tech_lab@heavenscent.id',
            'divisi' => 'produksi',
        ]);
        $user->assignRole('operasional');
        $user->gudangs()->sync([$gudang->id => ['is_primary' => true]]);

        // Update user to QC division, change role, and switch to global
        $response = $this->actingAs($this->adminUser)->put(route('users.update', $user), [
            'name' => 'Lead QC Chemist',
            'email' => 'tech_lab@heavenscent.id',
            'divisi' => 'qc',
            'role' => 'operasional',
            'warehouse_access_type' => 'global',
            'permissions' => ['stok.opname'],
        ]);

        $response->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertEquals('qc', $user->divisi);
        $this->assertEquals('Quality Control (QC)', $user->divisiLabel());
        $this->assertCount(0, $user->gudangs);
        $this->assertTrue($user->can('stok.opname'));
    }

    public function test_users_datatable_supports_division_and_role_filtering(): void
    {
        User::factory()->create(['name' => 'Purchaser One', 'email' => 'po1@test.com', 'divisi' => 'purchasing'])->assignRole('purchasing');
        User::factory()->create(['name' => 'Chemist One', 'email' => 'co1@test.com', 'divisi' => 'produksi'])->assignRole('operasional');

        // Test filter by divisi = purchasing
        $response = $this->actingAs($this->adminUser)->getJson(route('users.data', ['divisi' => 'purchasing']));
        $response->assertOk();
        $data = $response->json('data');

        $emails = collect($data)->pluck('email')->toArray();
        $this->assertContains('po1@test.com', $emails);
        $this->assertNotContains('co1@test.com', $emails);

        // Test filter by role = operasional
        $response2 = $this->actingAs($this->adminUser)->getJson(route('users.data', ['role' => 'operasional']));
        $response2->assertOk();
        $data2 = $response2->json('data');

        $emails2 = collect($data2)->pluck('email')->toArray();
        $this->assertContains('co1@test.com', $emails2);
        $this->assertNotContains('po1@test.com', $emails2);
    }
}
