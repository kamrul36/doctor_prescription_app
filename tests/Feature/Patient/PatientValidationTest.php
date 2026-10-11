<?php

namespace Tests\Feature\Patient;

use App\Domain\Patient\Models\Patient;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/** Every patient field rejects a wrong format, on the web and the API, and the form mirrors the rules (spec §10.1). */
class PatientValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessSeeder::class);
        Carbon::setTestNow('2026-10-07 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidValues(): array
    {
        return [
            'phone with letters' => [['phone' => '01711abc111'], 'phone'],
            'phone too short' => [['phone' => '123'], 'phone'],
            'alternate phone too long' => [['alt_phone' => '1234567890123456'], 'alt_phone'],
            'age over 130' => [['dob' => null, 'age_years' => '131'], 'age_years'],
            'age not a number' => [['dob' => null, 'age_years' => 'ten'], 'age_years'],
            'date of birth in the future' => [['dob' => '2026-10-08'], 'dob'],
            'date of birth not a date' => [['dob' => '31-31-2020'], 'dob'],
            'unknown gender' => [['gender' => 'robot'], 'gender'],
            'unknown blood group' => [['blood_group' => 'C+'], 'blood_group'],
            'name too long' => [['name' => str_repeat('a', 256)], 'name'],
            'address too long' => [['address' => str_repeat('a', 1001)], 'address'],
        ];
    }

    /**
     * @dataProvider invalidValues
     *
     * @param  array<string, mixed>  $overrides
     */
    public function test_a_wrong_format_is_rejected_on_web_and_api(array $overrides, string $field): void
    {
        $user = User::factory()->doctor()->create();
        $payload = [...$this->payload(), ...$overrides];

        $this->actingAs($user)->from('/patients/create')->post('/patients', $payload)
            ->assertRedirect('/patients/create')
            ->assertSessionHasErrors($field);

        $this->postJson('/api/v1/patients', $payload, ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertSame(0, Patient::count());
    }

    public function test_the_form_mirrors_the_rules_and_shows_server_errors_under_the_fields(): void
    {
        $user = User::factory()->doctor()->create();

        $this->actingAs($user)->get('/patients/create')->assertOk()
            ->assertSee('x-data="validatedForm(', false)
            ->assertSee('name="phone" value="" required maxlength="20" data-rule="phone"', false)
            ->assertSee('data-rule="integer" data-min="0" data-max="130"', false)
            ->assertSee('x-text="err(\'phone\')"', false);

        $this->from('/patients/create')->post('/patients', [...$this->payload(), 'phone' => '12'])->assertRedirect();
        $this->get('/patients/create')
            ->assertSee("window.toast?.error('Enter a phone number of 6 to 15 digits", false)
            ->assertSee('x-data="validatedForm(JSON.parse(', false);
    }

    public function test_a_saved_patient_shows_a_success_toast(): void
    {
        $user = User::factory()->doctor()->create();

        $response = $this->actingAs($user)->post('/patients', $this->payload());
        $response->assertSessionHas('success');

        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee('window.toast?.success(', false)
            ->assertSee('Patient registered with code 26000001.');
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['name' => 'Abdur Rahim', 'gender' => 'male', 'dob' => '1990-05-10', 'phone' => '01711-000 111', 'address' => 'Mirpur, Dhaka'];
    }
}
