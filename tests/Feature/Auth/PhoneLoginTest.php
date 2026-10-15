<?php

namespace Tests\Feature\Auth;

use App\Domain\Access\Role;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/** Users log in with their email or their phone; the phone is required and unique, the email optional. */
class PhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    /** Seeded for every test: the in-memory database is shared by all test classes, so seeding at migrate time would only reach the first. */
    protected function afterRefreshingDatabase(): void
    {
        $this->seed(AccessSeeder::class);
    }

    public function test_login_by_phone_on_web_and_api(): void
    {
        $user = User::factory()->doctor()->create(['phone' => '01712345678']);

        // Separators are fine, as on every phone field.
        $this->post('/login', ['login' => '01712-345 678', 'password' => 'password'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user, 'web');
        $this->post('/logout');

        $this->postJson('/api/v1/auth/login', ['login' => '01712345678', 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['access_token']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_login_by_email_still_works_and_the_api_accepts_the_old_email_key(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'password'])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_unknown_phone_wrong_password_and_inactive_get_the_same_answer(): void
    {
        $user = User::factory()->create(['phone' => '01712345678']);
        $inactive = User::factory()->inactive()->create(['phone' => '01812345678']);

        foreach ([['01999999999', 'password'], ['01712345678', 'nope'], [$inactive->phone, 'password']] as [$login, $password]) {
            $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => $password])
                ->assertStatus(401)->assertJsonPath('errors.login.0', trans('auth.failed'));
        }

        $this->from('/login')->post('/login', ['login' => '01999999999', 'password' => 'password'])
            ->assertRedirect('/login')->assertSessionHasErrors('login');
        $this->assertGuest('web');
        $this->assertNotNull($user->fresh());
    }

    public function test_failed_phone_logins_are_rate_limited_whatever_the_separators(): void
    {
        $user = User::factory()->create(['phone' => '01712345678']);

        foreach (['01712345678', '01712-345678', '01712 345 678', '(01712) 345678', '01712.345.678'] as $typed) {
            $this->postJson('/api/v1/auth/login', ['login' => $typed, 'password' => 'nope'])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['login' => $user->phone, 'password' => 'password'])->assertStatus(429);
    }

    public function test_user_without_email_is_created_and_logs_in_by_phone(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $h = $this->bearer($admin);

        $this->postJson('/api/v1/users', [
            'name' => 'Rafiq', 'email' => '', 'phone' => '01911000001', 'password' => 'secret-pass', 'roles' => [Role::ASSISTANT],
        ], $h)->assertCreated()->assertJsonPath('data.email', null);

        // Many users may have no email.
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Sultana', 'phone' => '01911000002', 'password' => 'secret-pass', 'roles' => [Role::ASSISTANT],
        ])->assertRedirect('/admin/users');

        $this->assertSame(2, User::query()->whereNull('email')->count());
        $this->post('/logout');

        $this->postJson('/api/v1/auth/login', ['login' => '01911000001', 'password' => 'secret-pass'])->assertOk();
    }

    public function test_phone_is_required_and_unique_and_email_stays_unique(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $taken = User::factory()->create(['phone' => '01711111111', 'email' => 'taken@example.com']);
        $h = $this->bearer($admin);
        $base = ['name' => 'New', 'password' => 'secret-pass', 'roles' => []];

        $this->postJson('/api/v1/users', $base, $h)->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson('/api/v1/users', [...$base, 'phone' => '01711-111111'], $h)
            ->assertUnprocessable()->assertJsonPath('errors.phone.0', 'Another user already has this phone number.');
        $this->postJson('/api/v1/users', [...$base, 'phone' => '01722222222', 'email' => 'taken@example.com'], $h)
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        // Update: leaving the phone out keeps it; clearing it or taking another user's is refused.
        $other = User::factory()->create(['phone' => '01733333333']);
        $this->putJson("/api/v1/users/{$other->id}", ['name' => 'Renamed'], $h)->assertOk();
        $this->assertSame('01733333333', $other->fresh()->phone);
        $this->putJson("/api/v1/users/{$other->id}", ['phone' => ''], $h)->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->putJson("/api/v1/users/{$other->id}", ['phone' => $taken->phone], $h)->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->putJson("/api/v1/users/{$other->id}", ['phone' => '01733333333', 'email' => ''], $h)->assertOk();
        $this->assertNull($other->fresh()->email);
    }

    public function test_user_form_marks_phone_required_and_email_optional(): void
    {
        $mark = '<span class="text-red-600" aria-hidden="true">*</span>';

        $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/users/create')->assertOk()
            ->assertSee('Phone '.$mark.'</label>', false)
            ->assertSee('Email</label>', false);
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
