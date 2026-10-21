<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_user_can_register_and_receive_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Mona Ali',
            'email' => 'mona@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('trace_code', 'AUTH-REG-200')
            ->assertJsonPath('code', '201')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'bearer');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->headers->get('X-Trace-Id'));
        $this->assertDatabaseHas('users', ['email' => 'mona@example.com']);
    }

    public function test_registration_validation_returns_an_indexed_error_code(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertStatus(422)
            ->assertJsonPath('trace_code', 'AUTH-REG-001')
            ->assertJsonStructure(['errors' => ['name', 'email', 'password']]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'mona@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'mona@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('trace_code', 'AUTH-LGN-002')
            ->assertJsonPath('code', '401');
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('trace_code', 'AUTH-TKN-001');
    }

    public function test_authenticated_user_can_view_profile_and_logout(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('trace_code', 'AUTH-ME-200')
            ->assertJsonPath('data.email', $user->email);

        $this->asUser($user)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('trace_code', 'AUTH-OUT-200');
    }

    public function test_clients_without_a_json_accept_header_still_get_the_envelope(): void
    {
        $this->get('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('trace_code', 'AUTH-TKN-001');

        $this->withHeader('Authorization', 'Bearer abc.def.ghi')
            ->get('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('trace_code', 'AUTH-TKN-001');
    }

    public function test_expired_token_is_reported_and_can_still_be_refreshed(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->travel(2)->hours();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('trace_code', 'AUTH-TKN-002');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->assertJsonPath('trace_code', 'AUTH-TKN-200');
    }

    public function test_refresh_issues_a_new_token(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/refresh');

        $response->assertOk()->assertJsonPath('trace_code', 'AUTH-TKN-200');
        $this->assertNotSame($token, $response->json('data.token'));
    }
}
