<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
    }

    public function test_unauthenticated_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('purchasing.index'))->assertRedirect(route('login'));
        $this->get(route('batches.index'))->assertRedirect(route('login'));
        $this->get(route('rt.index'))->assertRedirect(route('login'));
        $this->get(route('analisa.index'))->assertRedirect(route('login'));
        $this->get(route('stok.index'))->assertRedirect(route('login'));
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_manager_has_universal_access_and_approvals(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        // Core business menus
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('purchasing.index'))->assertOk();
        $this->get(route('batches.index'))->assertOk();
        $this->get(route('rt.index'))->assertOk();
        $this->get(route('analisa.index'))->assertOk();
        $this->get(route('stok.index'))->assertOk();
        $this->get(route('reports.index'))->assertOk();
        $this->get(route('users.index'))->assertOk();

        // Approval permissions
        $this->assertTrue($manager->can('purchasing.approve'));
        $this->assertTrue($manager->can('rt.approve'));
        $this->assertTrue($manager->can('purchasing.cancel'));
        $this->assertTrue($manager->can('batch.cancel'));
    }

    public function test_purchasing_permissions_and_boundaries(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->actingAs($purchasing);

        // Allowed menus
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('purchasing.index'))->assertOk();
        $this->get(route('purchasing.create'))->assertOk();
        $this->get(route('analisa.index'))->assertOk();
        $this->get(route('stok.index'))->assertOk();
        $this->get(route('produk.index'))->assertOk();

        // Forbidden menus & actions
        $this->get(route('batches.index'))->assertForbidden();
        $this->get(route('batches.create'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
        $this->assertFalse($purchasing->can('purchasing.approve'));
        $this->assertFalse($purchasing->can('batch.create'));
    }

    public function test_gudang_permissions_and_boundaries(): void
    {
        $gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $this->actingAs($gudang);

        // Allowed menus
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('rt.index'))->assertOk();
        $this->get(route('stok.index'))->assertOk();
        $this->get(route('purchasing.index'))->assertOk(); // to receive incoming goods

        // Forbidden menus & actions
        $this->get(route('purchasing.create'))->assertForbidden();
        $this->get(route('batches.index'))->assertForbidden();
        $this->get(route('analisa.index'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
        $this->assertFalse($gudang->can('purchasing.approve'));
    }

    public function test_operasional_permissions_and_boundaries(): void
    {
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->actingAs($operasional);

        // Allowed menus
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('batches.index'))->assertOk();
        $this->get(route('batches.create'))->assertOk();
        $this->get(route('rt.index'))->assertOk();
        $this->get(route('stok.index'))->assertOk();

        // Forbidden menus & actions
        $this->get(route('purchasing.index'))->assertForbidden();
        $this->get(route('analisa.index'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
        $this->assertFalse($operasional->can('rt.approve'));
    }

    public function test_fulfillment_permissions_and_boundaries(): void
    {
        $fulfillment = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();
        $this->actingAs($fulfillment);

        // Allowed menus
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('analisa.index'))->assertOk();
        $this->get(route('rt.index'))->assertOk();
        $this->get(route('stok.index'))->assertOk();

        // Forbidden menus & actions
        $this->get(route('batches.index'))->assertForbidden();
        $this->get(route('purchasing.index'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
    }
}
