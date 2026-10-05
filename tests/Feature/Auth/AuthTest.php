<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_me_requires_a_token(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('access_token');

        $response = $this->getJson('/api/v1/auth/me', [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertOk()->assertJsonFragment(['email' => $user->email]);
    }

    public function test_refresh_returns_a_new_token(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('access_token');

        $response = $this->postJson('/api/v1/auth/refresh', [], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertOk()->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    }

    public function test_logout_invalidates_the_token(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('access_token');

        $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => "Bearer {$token}",
        ])->assertOk();

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => "Bearer {$token}",
        ])->assertStatus(401);
    }
}
