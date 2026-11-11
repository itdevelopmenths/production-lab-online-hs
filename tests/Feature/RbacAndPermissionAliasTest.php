<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RbacAndPermissionAliasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_manager_has_full_bypass_on_all_abilities(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->assertTrue($manager->can('request.create'));
        $this->assertTrue($manager->can('request.approve'));
        $this->assertTrue($manager->can('transfer.ship'));
        $this->assertTrue($manager->can('transfer.receive'));
        $this->assertTrue($manager->can('purchasing.approve'));
        $this->assertTrue($manager->can('batch.release'));
        $this->assertTrue($manager->can('analisa.manage'));
    }

    public function test_gudang_role_has_transfer_capabilities_and_cannot_approve_requests(): void
    {
        $gudangUser = User::factory()->create();
        $gudangUser->assignRole('gudang');

        $this->assertTrue($gudangUser->can('transfer.view'));
        $this->assertTrue($gudangUser->can('transfer.ship'));
        $this->assertTrue($gudangUser->can('transfer.receive'));
        $this->assertTrue($gudangUser->can('request.view'));
        $this->assertFalse($gudangUser->can('request.approve'));
        $this->assertFalse($gudangUser->can('purchasing.approve'));
    }

    public function test_operasional_role_has_request_capabilities_and_cannot_approve_requests(): void
    {
        $operasionalUser = User::factory()->create();
        $operasionalUser->assignRole('operasional');
        $operasionalUser = User::findOrFail($operasionalUser->id);

        $this->assertTrue($operasionalUser->can('request.view'));
        $this->assertTrue($operasionalUser->can('request.bahan.create'));
        $this->assertTrue($operasionalUser->can('request.create'));
        $this->assertTrue($operasionalUser->can('request.submit'));
        $this->assertTrue($operasionalUser->can('batch.create'));
        $this->assertTrue($operasionalUser->can('transfer.bahan.receive'));
        $this->assertTrue($operasionalUser->can('transfer.produk_jadi.create'));
        $this->assertTrue($operasionalUser->can('transfer.produk_jadi.ship'));
        $this->assertTrue($operasionalUser->can('transfer.create'));
        $this->assertTrue($operasionalUser->can('transfer.ship'));
        $this->assertTrue($operasionalUser->can('transfer.receive'));

        $this->assertFalse($operasionalUser->can('request.produk_jadi.create'));
        $this->assertFalse($operasionalUser->can('transfer.bahan.create'));
        $this->assertFalse($operasionalUser->can('transfer.bahan.ship'));
        $this->assertFalse($operasionalUser->can('transfer.produk_jadi.receive'));
        $this->assertFalse($operasionalUser->can('transfer.cancel'));
        $this->assertFalse($operasionalUser->can('request.approve'));
        $this->assertFalse($operasionalUser->can('purchasing.approve'));
    }

    public function test_fulfillment_role_can_request_and_transfer_goods(): void
    {
        $fulfillmentUser = User::factory()->create();
        $fulfillmentUser->assignRole('fulfillment');

        $this->assertTrue($fulfillmentUser->can('request.view'));
        $this->assertTrue($fulfillmentUser->can('request.produk_jadi.create'));
        $this->assertTrue($fulfillmentUser->can('request.create'));
        $this->assertTrue($fulfillmentUser->can('transfer.view'));
        $this->assertTrue($fulfillmentUser->can('transfer.produk_jadi.create'));
        $this->assertTrue($fulfillmentUser->can('transfer.produk_jadi.ship'));
        $this->assertTrue($fulfillmentUser->can('transfer.produk_jadi.receive'));
        $this->assertTrue($fulfillmentUser->can('transfer.receive'));
        $this->assertFalse($fulfillmentUser->can('request.bahan.create'));
        $this->assertFalse($fulfillmentUser->can('transfer.bahan.create'));
        $this->assertFalse($fulfillmentUser->can('batch.create'));
    }

    public function test_legacy_rt_permissions_alias_to_request_and_transfer_gates(): void
    {
        $customUser = User::factory()->create();
        // Beri direct permission rt.create saja
        $customUser->givePermissionTo('rt.create');

        $this->assertTrue($customUser->can('request.create'));
        $this->assertTrue($customUser->can('transfer.create'));
        $this->assertFalse($customUser->can('request.approve'));
        $this->assertFalse($customUser->can('transfer.ship'));
    }

    public function test_request_bahan_permission_does_not_leak_transfer_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('request.bahan.create');

        $this->assertTrue($user->can('request.bahan.create'));
        $this->assertTrue($user->can('request.create'));
        $this->assertTrue($user->can('rt.create'));

        $this->assertFalse($user->can('request.produk_jadi.create'));
        $this->assertFalse($user->can('transfer.create'));
        $this->assertFalse($user->can('transfer.bahan.create'));
        $this->assertFalse($user->can('transfer.produk_jadi.create'));
    }

    public function test_nested_gate_checks_do_not_suffer_reentrancy_bypass(): void
    {
        $produkJadiOnlyUser = User::factory()->create();
        $produkJadiOnlyUser->givePermissionTo('transfer.produk_jadi.create');

        $globalTransferUser = User::factory()->create();
        $globalTransferUser->givePermissionTo('transfer.create');

        // Order A -> B and B -> A must stay consistent without static checking corruption
        $this->assertFalse($produkJadiOnlyUser->can('transfer.bahan.create'));
        $this->assertTrue($produkJadiOnlyUser->can('transfer.produk_jadi.create'));
        $this->assertTrue($produkJadiOnlyUser->can('transfer.produk_jadi.create'));
        $this->assertFalse($produkJadiOnlyUser->can('transfer.bahan.create'));

        $this->assertTrue($globalTransferUser->can('transfer.bahan.create'));
        $this->assertTrue($globalTransferUser->can('transfer.produk_jadi.create'));
    }

    public function test_migration_syncs_new_permissions_and_strips_rt_create_from_existing_operasional_role(): void
    {
        $operasionalRole = Role::where('name', 'operasional')->firstOrFail();
        // Simulasikan database produksi lama yang masih memakai rt.create, transfer.ship, transfer.receive
        $operasionalRole->syncPermissions([
            'request.view', 'request.create', 'request.submit', 'request.cancel',
            'transfer.view', 'transfer.ship', 'transfer.receive',
            'rt.view', 'rt.create', 'rt.submit', 'rt.ship', 'rt.receive',
        ]);

        $migrationPath = database_path('migrations/2026_11_11_000002_add_granular_bahan_produk_jadi_permissions.php');
        $this->assertFileExists($migrationPath);

        $migration = require $migrationPath;
        $migration->up();

        // Jalankan kedua kali untuk memverifikasi idempotensi
        $migration->up();

        $freshRole = Role::where('name', 'operasional')->firstOrFail();
        $assignedNames = $freshRole->permissions()->pluck('name')->all();

        foreach ([
            'request.bahan.create',
            'request.produk_jadi.create',
            'transfer.bahan.create',
            'transfer.bahan.ship',
            'transfer.bahan.receive',
            'transfer.produk_jadi.create',
            'transfer.produk_jadi.ship',
            'transfer.produk_jadi.receive',
        ] as $permKey) {
            $this->assertDatabaseHas('permissions', ['name' => $permKey]);
        }

        $this->assertContains('request.bahan.create', $assignedNames);
        $this->assertContains('transfer.bahan.receive', $assignedNames);
        $this->assertContains('transfer.produk_jadi.create', $assignedNames);
        $this->assertContains('transfer.produk_jadi.ship', $assignedNames);

        $this->assertNotContains('rt.create', $assignedNames);
        $this->assertNotContains('rt.ship', $assignedNames);
        $this->assertNotContains('rt.receive', $assignedNames);
        $this->assertNotContains('request.create', $assignedNames);
        $this->assertNotContains('transfer.create', $assignedNames);
        $this->assertNotContains('transfer.ship', $assignedNames);
        $this->assertNotContains('transfer.receive', $assignedNames);
        $this->assertNotContains('transfer.bahan.create', $assignedNames);
        $this->assertNotContains('transfer.bahan.ship', $assignedNames);
        $this->assertNotContains('transfer.cancel', $assignedNames);
    }
}

