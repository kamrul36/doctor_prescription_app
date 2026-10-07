<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebLoginTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = AccessSeeder::class;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in');
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->doctor()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user, 'web');

        $this->get('/')->assertOk()->assertSee($user->name);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_wrong_password_shows_an_error(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_lockout_message_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'nope']);
        }

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        $this->assertGuest('web');
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->doctor()->create();
        $this->actingAs($user, 'web')->get('/')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_session_from_before_deactivation_stays_revoked_after_reactivation(): void
    {
        $user = User::factory()->doctor()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->get('/')->assertOk();

        // Deactivated and reactivated without the session making a request in between.
        $user->update(['is_active' => false]);
        $user->update(['is_active' => true]);
        $this->app['auth']->forgetGuards(); // the guard cached the user during login

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_deactivation_rotates_the_remember_token(): void
    {
        $user = User::factory()->doctor()->create(['remember_token' => 'old-token']);

        $user->update(['is_active' => false]);

        $this->assertNotSame('old-token', $user->fresh()->getRememberToken());
    }
}
