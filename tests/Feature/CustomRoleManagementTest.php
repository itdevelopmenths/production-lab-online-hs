<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionCatalogService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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

    public function test_updating_role_auto_creates_missing_catalog_permissions_and_rejects_unknown_keys(): void
    {
        $this->actingAs($this->manager);

        $customRole = Role::create([
            'name' => 'ops_coordinator',
            'display_name' => 'Koordinator Operasional',
            'is_system' => false,
            'guard_name' => 'web',
        ]);

        // Simulasikan kondisi database belum di-seed ulang sehingga permission baru belum ada di tabel permissions
        $newlyAddedCatalogKeys = ['request.view', 'transfer.view', 'purchasing.audit'];
        Permission::whereIn('name', $newlyAddedCatalogKeys)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionsPayload = [
            'produk.view', 'produk.create', 'produk.edit', 'produk.delete',
            'gudang.view', 'gudang.create', 'gudang.edit', 'gudang.delete',
            'supplier.view', 'bom.view', 'stok.view',
            // Indeks 11, 12, 13 (permission katalog yang sempat terhapus dari DB)
            'request.view', 'transfer.view', 'purchasing.audit',
        ];

        $this->put(route('roles.update', $customRole), [
            'name' => 'ops_coordinator',
            'display_name' => 'Koordinator Operasional',
            'description' => 'Update izin dengan sinkronisasi otomatis',
            'permissions' => $permissionsPayload,
        ])->assertRedirect(route('roles.index'))
            ->assertSessionHasNoErrors();

        foreach ($newlyAddedCatalogKeys as $permName) {
            $this->assertDatabaseHas('permissions', ['name' => $permName]);
            $this->assertTrue($customRole->fresh()->hasPermissionTo($permName));
        }

        // Pastikan permission liar di luar katalog tetap ditolak validasi
        $this->put(route('roles.update', $customRole), [
            'name' => 'ops_coordinator',
            'display_name' => 'Koordinator Operasional',
            'permissions' => ['produk.view', 'hacked.nonexistent.permission'],
        ])->assertSessionHasErrors('permissions.1');
    }

    public function test_updating_role_with_all_catalog_permissions_succeeds(): void
    {
        $this->actingAs($this->manager);

        $systemRole = Role::where('name', 'gudang')->firstOrFail();
        $allCatalogKeys = app(PermissionCatalogService::class)->getAllPermissionKeys();

        $this->put(route('roles.update', $systemRole), [
            'display_name' => $systemRole->display_name,
            'description' => $systemRole->description,
            'permissions' => $allCatalogKeys,
        ])->assertRedirect(route('roles.index'))
            ->assertSessionHasNoErrors();

        $this->assertEquals(count($allCatalogKeys), $systemRole->fresh()->permissions()->count());
    }

    public function test_granular_bahan_and_produk_jadi_permissions_visible_in_role_ui_and_assignable(): void
    {
        $this->actingAs($this->manager);

        $expectedKeys = [
            'request.bahan.create',
            'request.produk_jadi.create',
            'transfer.bahan.create',
            'transfer.bahan.ship',
            'transfer.bahan.receive',
            'transfer.produk_jadi.create',
            'transfer.produk_jadi.ship',
            'transfer.produk_jadi.receive',
        ];

        $createPage = $this->get(route('roles.create'))->assertOk();
        foreach ($expectedKeys as $key) {
            $createPage->assertSee($key);
        }

        $selected = ['request.bahan.create', 'transfer.bahan.receive', 'transfer.produk_jadi.create'];

        $this->post(route('roles.store'), [
            'name' => 'custom_lab_helper',
            'display_name' => 'Asisten Lab Kustom',
            'description' => 'Role uji permission granular',
            'permissions' => $selected,
        ])->assertRedirect(route('roles.index'))
            ->assertSessionHasNoErrors();

        $createdRole = Role::where('name', 'custom_lab_helper')->firstOrFail();
        $assigned = $createdRole->permissions()->pluck('name')->sort()->values()->all();
        sort($selected);

        $this->assertSame($selected, $assigned);
        $this->assertNotContains('rt.create', $assigned);
    }

    public function test_catalog_has_no_dead_report_export_and_audit_view_label_is_descriptive(): void
    {
        $catalog = app(PermissionCatalogService::class);
        $keys = $catalog->getAllPermissionKeys();

        $this->assertNotContains('report.export', $keys);
        $this->assertContains('audit.view', $keys);

        $label = $catalog->getGroupedCatalog()['Inventori & Analisis']['report']['actions']['audit.view'];
        $this->assertStringContainsString('Seluruh Modul', $label);
    }

    public function test_update_preserves_non_catalog_permissions(): void
    {
        $this->actingAs($this->manager);

        foreach (['reports.view', 'gudang.audit'] as $legacy) {
            Permission::firstOrCreate(['name' => $legacy, 'guard_name' => 'web']);
        }
        $role = Role::create(['name' => 'legacy_holder', 'display_name' => 'Legacy Holder', 'is_system' => false, 'guard_name' => 'web']);
        $role->syncPermissions(['purchasing.view', 'reports.view', 'gudang.audit']);

        $this->put(route('roles.update', $role), [
            'name' => 'legacy_holder',
            'display_name' => 'Legacy Holder',
            'permissions' => ['purchasing.view'],
        ])->assertRedirect(route('roles.index'));

        $role = $role->fresh();
        $this->assertTrue($role->hasPermissionTo('purchasing.view'));
        $this->assertTrue($role->hasPermissionTo('reports.view'));
        $this->assertTrue($role->hasPermissionTo('gudang.audit'));
    }

    public function test_update_still_removes_catalog_permission_when_unchecked(): void
    {
        $this->actingAs($this->manager);

        $role = Role::create(['name' => 'audit_holder', 'display_name' => 'Audit Holder', 'is_system' => false, 'guard_name' => 'web']);
        $role->syncPermissions(['purchasing.view', 'audit.view']);

        $this->put(route('roles.update', $role), [
            'name' => 'audit_holder',
            'display_name' => 'Audit Holder',
            'permissions' => ['purchasing.view'],
        ])->assertRedirect(route('roles.index'));

        $role = $role->fresh();
        $this->assertTrue($role->hasPermissionTo('purchasing.view'));
        $this->assertFalse($role->hasPermissionTo('audit.view'));
    }

    public function test_manager_role_permissions_can_be_updated(): void
    {
        $this->actingAs($this->manager);

        $managerRole = Role::where('name', 'manager')->firstOrFail();

        $this->put(route('roles.update', $managerRole), [
            'display_name' => 'Manager Renamed',
            'permissions' => ['purchasing.view', 'role.manage'],
        ])->assertRedirect(route('roles.index'));

        $managerRole = $managerRole->fresh();
        $this->assertSame('Manager Renamed', $managerRole->display_name);
        $this->assertTrue($managerRole->hasPermissionTo('purchasing.view'));
        $this->assertFalse($managerRole->hasPermissionTo('audit.view'));
    }

    public function test_manager_role_edit_page_is_editable(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('roles.edit', Role::where('name', 'manager')->firstOrFail()))
            ->assertOk()
            ->assertSeeText('Simpan Perubahan')
            ->assertDontSeeText('Manager memiliki akses penuh');
    }

    public function test_other_system_role_edit_page_still_editable(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('roles.edit', Role::where('name', 'gudang')->firstOrFail()))
            ->assertOk()
            ->assertSeeText('Simpan Perubahan')
            ->assertDontSeeText('Manager memiliki akses penuh');
    }

    public function test_manager_role_manage_cannot_be_revoked(): void
    {
        $this->actingAs($this->manager);

        $managerRole = Role::where('name', 'manager')->firstOrFail();

        $this->put(route('roles.update', $managerRole), [
            'display_name' => 'Manager (Administrator)',
            'permissions' => ['purchasing.view'],
        ])->assertRedirect(route('roles.index'));

        $managerRole = $managerRole->fresh();
        $this->assertTrue($managerRole->hasPermissionTo('role.manage'));
        $this->assertTrue($managerRole->hasPermissionTo('purchasing.view'));
    }

    public function test_revoking_permission_from_manager_takes_effect_for_manager_users(): void
    {
        $this->actingAs($this->manager);

        $managerRole = Role::where('name', 'manager')->firstOrFail();
        $keep = $managerRole->permissions()->pluck('name')->reject(fn ($n) => $n === 'audit.view')->values()->all();

        $this->put(route('roles.update', $managerRole), [
            'display_name' => 'Manager (Administrator)',
            'permissions' => $keep,
        ])->assertRedirect(route('roles.index'));

        $manager = $this->manager->fresh();
        $this->assertFalse($manager->can('audit.view'));
        $this->assertTrue($manager->can('role.manage'));
        $this->assertTrue($manager->can('purchasing.view'));
    }

    public function test_reseeding_does_not_overwrite_manager_permission_edits(): void
    {
        $managerRole = Role::where('name', 'manager')->firstOrFail();
        $managerRole->revokePermissionTo('audit.view');

        $this->seed(PermissionSeeder::class);

        $this->assertFalse($managerRole->fresh()->hasPermissionTo('audit.view'));
        $this->assertTrue($managerRole->fresh()->hasPermissionTo('role.manage'));
    }

    public function test_manager_edit_page_marks_role_manage_as_locked(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('roles.edit', Role::where('name', 'manager')->firstOrFail()))
            ->assertOk()
            ->assertSeeText('Kelola Peran selalu aktif untuk Manager')
            ->assertSee('data-locked-permission="role.manage"', false);
    }

    protected function legacyRole(): Role
    {
        Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'legacy_role', 'display_name' => 'Legacy Role', 'is_system' => false, 'guard_name' => 'web']);
        $role->syncPermissions(['purchasing.view', 'reports.view']);

        return $role;
    }

    public function test_edit_page_lists_legacy_permissions_outside_catalog(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('roles.edit', $this->legacyRole()))
            ->assertOk()
            ->assertSeeText('Wewenang lama (di luar katalog)')
            ->assertSee('value="reports.view"', false);
    }

    public function test_unchecking_legacy_permission_revokes_it(): void
    {
        $this->actingAs($this->manager);
        $role = $this->legacyRole();

        $this->put(route('roles.update', $role), [
            'name' => 'legacy_role',
            'display_name' => 'Legacy Role',
            'permissions' => ['purchasing.view'],
            'legacy_present' => '1',
        ])->assertRedirect(route('roles.index'));

        $this->assertFalse($role->fresh()->hasPermissionTo('reports.view'));
    }

    public function test_checked_legacy_permission_is_kept(): void
    {
        $this->actingAs($this->manager);
        $role = $this->legacyRole();

        $this->put(route('roles.update', $role), [
            'name' => 'legacy_role',
            'display_name' => 'Legacy Role',
            'permissions' => ['purchasing.view'],
            'legacy_present' => '1',
            'legacy_permissions' => ['reports.view'],
        ])->assertRedirect(route('roles.index'));

        $this->assertTrue($role->fresh()->hasPermissionTo('reports.view'));
    }
}
