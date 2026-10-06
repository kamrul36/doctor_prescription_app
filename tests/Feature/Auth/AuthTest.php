<?php

namespace Tests\Feature\Auth;

use App\Domain\Audit\AuditLog;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = AccessSeeder::class;

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
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
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

        $response->assertStatus(401)->assertHeader('Content-Type', 'application/problem+json');
        // The account is the subject, not the actor: whoever tried is unknown.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_failed',
            'user_id' => null,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(401)->assertJsonPath('errors.email.0', trans('auth.failed'));
    }

    public function test_login_is_locked_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'nope'])->assertStatus(401);
        }

        // Even the right password is refused while locked out.
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(429)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('Retry-After')
            ->assertJson(['status' => 429, 'title' => 'Too Many Requests']);
    }

    public function test_successful_login_resets_the_failure_count(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'nope']);
        }
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'nope'])->assertStatus(401);
        }
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_me_requires_a_token(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_me_returns_authenticated_user_with_roles_and_permissions(): void
    {
        $user = User::factory()->assistant()->create();

        $response = $this->getJson('/api/v1/auth/me', $this->bearer($user));

        $response->assertOk()
            ->assertJsonFragment(['email' => $user->email])
            ->assertJsonPath('data.roles', ['assistant']);

        $permissions = $response->json('data.permissions');
        $this->assertContains('payments.record', $permissions);
        $this->assertNotContains('cases.finalize', $permissions);
    }

    public function test_deactivated_user_token_is_rejected(): void
    {
        $user = User::factory()->doctor()->create();
        $headers = $this->bearer($user);

        $user->update(['is_active' => false]);
        $this->app['auth']->forgetGuards(); // the guard cached the user during login

        $this->getJson('/api/v1/auth/me', $headers)->assertStatus(401);
    }

    public function test_refresh_returns_a_new_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/refresh', [], $this->bearer($user));

        $response->assertOk()->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    }

    public function test_logout_invalidates_the_token(): void
    {
        $user = User::factory()->create();
        $headers = $this->bearer($user);

        $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();

        $this->getJson('/api/v1/auth/me', $headers)->assertStatus(401);
        $this->assertSame(1, AuditLog::where('action', 'auth.logout')->count());
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('access_token');

        return ['Authorization' => "Bearer {$token}"];
    }
}
