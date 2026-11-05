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

        $this->assertTrue($operasionalUser->can('request.view'));
        $this->assertTrue($operasionalUser->can('request.create'));
        $this->assertTrue($operasionalUser->can('request.submit'));
        $this->assertTrue($operasionalUser->can('batch.create'));
        $this->assertFalse($operasionalUser->can('request.approve'));
        $this->assertFalse($operasionalUser->can('purchasing.approve'));
    }

    public function test_fulfillment_role_can_request_and_transfer_goods(): void
    {
        $fulfillmentUser = User::factory()->create();
        $fulfillmentUser->assignRole('fulfillment');

        $this->assertTrue($fulfillmentUser->can('request.view'));
        $this->assertTrue($fulfillmentUser->can('request.create'));
        $this->assertTrue($fulfillmentUser->can('transfer.view'));
        $this->assertTrue($fulfillmentUser->can('transfer.receive'));
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
}

