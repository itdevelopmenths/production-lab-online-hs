<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_session_ping_returns_401_for_unauthenticated_user(): void
    {
        $response = $this->getJson(route('session.ping'));

        $response->assertStatus(401)
            ->assertJson([
                'authenticated' => false,
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    public function test_session_ping_returns_authenticated_status_and_csrf_token_for_logged_in_user(): void
    {
        $user = User::factory()->create([
            'warehouse_access_type' => 'global',
        ]);
        $user->assignRole('manager');

        $response = $this->actingAs($user)->getJson(route('session.ping'));

        $response->assertOk()
            ->assertJson([
                'authenticated' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ])
            ->assertJsonStructure([
                'authenticated',
                'csrf_token',
                'user' => ['id', 'name', 'email', 'role'],
                'timestamp',
            ]);
    }

    public function test_unauthenticated_ajax_request_returns_clean_401_json(): void
    {
        $response = $this->getJson(route('dashboard'));

        $response->assertStatus(401)
            ->assertJson([
                'code' => 'UNAUTHENTICATED',
            ]);
    }
}
