<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleSwitcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
    }

    public function test_authenticated_user_can_switch_to_all_standard_roles(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->first();
        $this->actingAs($manager);

        $roles = [
            'purchasing' => 'purchasing@heavenscent.id',
            'gudang' => 'gudang@heavenscent.id',
            'operasional' => 'operasional@heavenscent.id',
            'fulfillment' => 'fulfillment@heavenscent.id',
            'manager' => 'manager@heavenscent.id',
        ];

        foreach ($roles as $role => $expectedEmail) {
            $response = $this->post(route('switch-role'), [
                'role' => $role,
            ]);

            $response->assertRedirect(route('dashboard'));
            $response->assertSessionHas('success');

            $currentUser = auth()->user();
            $this->assertNotNull($currentUser);
            $this->assertEquals($expectedEmail, $currentUser->email);
            $this->assertTrue($currentUser->hasRole($role));
        }
    }

    public function test_guest_user_can_quick_login_via_role_switcher(): void
    {
        $this->assertGuest();

        $response = $this->post(route('switch-role'), [
            'role' => 'gudang',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $currentUser = auth()->user();
        $this->assertEquals('gudang@heavenscent.id', $currentUser->email);
        $this->assertTrue($currentUser->hasRole('gudang'));
    }

    public function test_role_switcher_rejects_invalid_role(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->first();
        $this->actingAs($manager);

        $response = $this->post(route('switch-role'), [
            'role' => 'super_hacker_role',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertEquals('manager@heavenscent.id', auth()->user()->email);
    }

    public function test_role_switcher_is_forbidden_in_production(): void
    {
        $this->withoutMiddleware();
        $this->app['env'] = 'production';

        $response = $this->post(route('switch-role'), [
            'role' => 'manager',
        ]);

        $response->assertStatus(403);
    }

    public function test_role_switching_affects_authorization_and_menu_access(): void
    {
        // 1. Switch to Purchasing
        $this->post(route('switch-role'), ['role' => 'purchasing']);
        $this->assertTrue(auth()->user()->can('purchasing.view'));
        $this->assertFalse(auth()->user()->can('batch.create'));

        // Purchasing can access purchasing index, but forbidden to access batch creation
        $this->get(route('purchasing.index'))->assertOk();
        $this->get(route('batches.create'))->assertForbidden();

        // 2. Switch to Operasional
        $this->post(route('switch-role'), ['role' => 'operasional']);
        $this->assertTrue(auth()->user()->can('batch.view'));
        $this->assertTrue(auth()->user()->can('batch.create'));
        $this->assertFalse(auth()->user()->can('purchasing.create'));

        // Operasional can access batch create, but cannot create purchase order
        $this->get(route('batches.create'))->assertOk();
        $this->get(route('purchasing.create'))->assertForbidden();
    }
}
