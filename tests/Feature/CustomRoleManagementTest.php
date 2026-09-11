<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CustomRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed();

        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->regularUser = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
    }

    public function test_manager_can_view_roles_index_and_creation_page(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('roles.index'))
            ->assertOk()
            ->assertSeeText('Peran & Wewenang');

        $this->get(route('roles.create'))
            ->assertOk()
            ->assertSeeText('Tambah Peran Baru')
            ->assertSeeText('Matriks Hak Akses & Wewenang');
    }

    public function test_unauthorized_user_cannot_access_role_management(): void
    {
        $this->actingAs($this->regularUser);

        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('roles.create'))->assertForbidden();
        $this->post(route('roles.store'), ['name' => 'hacker_role'])->assertForbidden();
    }

    public function test_roles_datatable_endpoint_returns_valid_json(): void
    {
        $this->actingAs($this->manager);

        $response = $this->getJson(route('roles.data'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'display_name',
                        'role_badge',
                        'system_badge',
                        'permissions_summary',
                        'users_count_badge',
                        'action',
                    ],
                ],
            ]);
    }

    public function test_can_create_custom_role_with_granular_permissions(): void
    {
        $this->actingAs($this->manager);

        $payload = [
            'name' => 'qc_supervisor',
            'display_name' => 'Supervisor Mutu & Formula',
            'description' => 'Bertanggung jawab atas formula BOM dan pengawasan batch produksi',
            'permissions' => [
                'produk.view',
                'bom.view',
                'bom.manage',
                'batch.view',
                'batch.create',
            ],
        ];

        $response = $this->post(route('roles.store'), $payload);

        $response->assertRedirect(route('roles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('roles', [
            'name' => 'qc_supervisor',
            'display_name' => 'Supervisor Mutu & Formula',
            'is_system' => false,
        ]);

        $createdRole = Role::findByName('qc_supervisor');
        $this->assertCount(5, $createdRole->permissions);
        $this->assertTrue($createdRole->hasPermissionTo('batch.create'));
        $this->assertFalse($createdRole->hasPermissionTo('purchasing.approve'));
    }

    public function test_user_assigned_to_custom_role_has_expected_access_boundaries(): void
    {
        // 1. Create custom role with only batch permissions
        $customRole = Role::create([
            'name' => 'lab_technician',
            'display_name' => 'Teknisi Lab',
            'is_system' => false,
            'guard_name' => 'web',
        ]);
        $customRole->syncPermissions(['batch.view', 'batch.create']);

        // 2. Create user and assign the custom role
        $technician = User::factory()->create([
            'name' => 'Joko Teknisi',
            'email' => 'joko@heavenscent.id',
        ]);
        $technician->assignRole('lab_technician');

        // 3. Test access boundaries
        $this->actingAs($technician);
        $this->get(route('batches.index'))->assertOk();
        $this->get(route('purchasing.index'))->assertForbidden();
        $this->get(route('analisa.index'))->assertForbidden();
    }

    public function test_updating_role_permissions_invalidates_cache_and_takes_effect_immediately(): void
    {
        $this->actingAs($this->manager);

        $customRole = Role::create([
            'name' => 'junior_purchaser',
            'display_name' => 'Purchasing Junior',
            'is_system' => false,
            'guard_name' => 'web',
        ]);
        $customRole->syncPermissions(['purchasing.view']);

        $user = User::factory()->create(['email' => 'junior@heavenscent.id']);
        $user->assignRole('junior_purchaser');

        // User initially cannot access batch
        $this->actingAs($user);
        $this->get(route('batches.index'))->assertForbidden();

        // Manager updates the role to grant batch.view
        $this->actingAs($this->manager);
        $this->put(route('roles.update', $customRole), [
            'name' => 'junior_purchaser',
            'display_name' => 'Purchasing Junior & Produksi',
            'description' => 'Ditambah wewenang melihat batch',
            'permissions' => ['purchasing.view', 'batch.view'],
        ])->assertRedirect(route('roles.index'));

        // User can now immediately access batch.index without stale cache
        $this->actingAs($user->fresh());
        $this->get(route('batches.index'))->assertOk();
    }

    public function test_cannot_delete_system_role(): void
    {
        $this->actingAs($this->manager);

        $systemRole = Role::where('name', 'purchasing')->firstOrFail();
        $this->assertTrue($systemRole->is_system);

        $response = $this->deleteJson(route('roles.destroy', $systemRole));

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('roles', ['name' => 'purchasing']);
    }

    public function test_cannot_delete_custom_role_with_active_users(): void
    {
        $this->actingAs($this->manager);

        $customRole = Role::create([
            'name' => 'qa_auditor',
            'display_name' => 'QA Auditor',
            'is_system' => false,
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->assignRole('qa_auditor');

        $response = $this->deleteJson(route('roles.destroy', $customRole));

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('roles', ['name' => 'qa_auditor']);
    }

    public function test_can_delete_unused_custom_role(): void
    {
        $this->actingAs($this->manager);

        $customRole = Role::create([
            'name' => 'temporary_tester',
            'display_name' => 'Peran Uji Coba Sementara',
            'is_system' => false,
            'guard_name' => 'web',
        ]);

        $response = $this->deleteJson(route('roles.destroy', $customRole));

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('roles', ['name' => 'temporary_tester']);
    }
}
