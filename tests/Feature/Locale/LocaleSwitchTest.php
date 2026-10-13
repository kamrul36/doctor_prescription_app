<?php

namespace Tests\Feature\Locale;

use App\Domain\Patient\Gender;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/** The বাংলা | EN UI switch (spec §6). The suite runs in English; these tests set Bangla themselves. */
class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessSeeder::class);
    }

    public function test_bangla_is_the_shipped_default(): void
    {
        $saved = [getenv('APP_LOCALE'), $_ENV['APP_LOCALE'] ?? null, $_SERVER['APP_LOCALE'] ?? null];
        putenv('APP_LOCALE');
        unset($_ENV['APP_LOCALE'], $_SERVER['APP_LOCALE']);

        try {
            $config = require base_path('config/app.php');
        } finally {
            putenv('APP_LOCALE='.$saved[0]);
            $_ENV['APP_LOCALE'] = $saved[1];
            $_SERVER['APP_LOCALE'] = $saved[2];
        }

        $this->assertSame('bn', $config['locale']);
        $this->assertSame('en', $config['fallback_locale']);
        $this->assertSame(['bn', 'en'], $config['supported_locales']);
    }

    public function test_a_guest_sees_the_default_language_and_can_switch(): void
    {
        config(['app.locale' => 'bn']);

        $this->get('/login')->assertOk()->assertSee('<html lang="bn">', false)->assertSee('সাইন ইন')
            ->assertSee(route('locale.update'), false);

        $this->from('/login')->put('/locale', ['locale' => 'en'])->assertRedirect('/login')->assertSessionHas('locale', 'en');
        $this->get('/login')->assertSee('<html lang="en">', false)->assertSee('Sign in')->assertDontSee('সাইন ইন');
    }

    public function test_a_user_without_a_choice_gets_the_default_and_the_choice_is_saved_on_the_account(): void
    {
        config(['app.locale' => 'bn']);
        $user = User::factory()->doctor()->create();

        $this->actingAs($user)->get('/')->assertSee('প্রেসক্রিপশন')->assertSee('লগ আউট');

        $this->from('/')->put('/locale', ['locale' => 'en'])->assertRedirect('/');
        $this->assertSame('en', $user->fresh()->locale);

        // A new session (other browser) still uses the saved choice.
        $this->flushSession();
        $this->actingAs($user->fresh())->get('/')->assertSee('Log out')->assertDontSee('লগ আউট');
    }

    public function test_an_unknown_language_is_rejected(): void
    {
        $user = User::factory()->doctor()->create();

        $this->actingAs($user)->from('/')->put('/locale', ['locale' => 'fr'])->assertRedirect('/')->assertSessionHasErrors('locale');
        $this->assertNull($user->fresh()->locale);
    }

    public function test_web_validation_messages_and_toasts_follow_the_language(): void
    {
        $user = User::factory()->doctor()->create(['locale' => 'bn']);

        $this->actingAs($user)->from('/patients/create')->post('/patients', [])
            ->assertSessionHasErrors(['name' => 'নাম ঘরটি পূরণ করা আবশ্যক।']);

        // The scripts get the Bangla dictionary (validation messages typed in the browser).
        $this->get('/patients/create')->assertSee('window.APP_I18N', false)->assertSee('This field is required.', false);
    }

    public function test_dates_and_enum_labels_follow_the_language(): void
    {
        $user = User::factory()->doctor()->create(['locale' => 'bn']);

        $this->actingAs($user)->get('/patients/create')->assertSee('পুরুষ')->assertSee('মহিলা');
        $this->assertSame('পুরুষ', Gender::Male->label());
    }

    public function test_api_uses_the_saved_choice_then_accept_language(): void
    {
        $user = User::factory()->doctor()->create();
        $h = $this->bearer($user);

        $this->postJson('/api/v1/patients', [], $h + ['Accept-Language' => 'bn-BD,bn;q=0.9'])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'নাম ঘরটি পূরণ করা আবশ্যক।');
        $this->postJson('/api/v1/patients', [], $h + ['Accept-Language' => 'en'])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'The name field is required.');

        $this->getJson('/api/v1/auth/me', $h)->assertOk()->assertJsonPath('data.locale', null);
        $this->putJson('/api/v1/auth/me/locale', ['locale' => 'bn'], $h)->assertOk()->assertJsonPath('data.locale', 'bn');
        $this->putJson('/api/v1/auth/me/locale', ['locale' => 'fr'], $h)->assertUnprocessable()->assertJsonValidationErrors('locale');

        // The saved choice wins over the header every browser sends.
        $this->postJson('/api/v1/patients', [], $this->bearer($user->fresh()) + ['Accept-Language' => 'en-US,en;q=0.9'])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'নাম ঘরটি পূরণ করা আবশ্যক।');
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        $this->app['auth']->forgetGuards();
        foreach (['tymon.jwt', 'tymon.jwt.auth', 'tymon.jwt.parser'] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
        JWTAuth::clearResolvedInstances();
        JWTAuth::unsetToken();

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
