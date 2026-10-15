<?php

namespace Tests\Feature\Patient;

use App\Domain\Patient\Models\Patient;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/** Optional address, and whose phone number it is (the patient's own or a relative's). */
class PatientContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessSeeder::class);
        $this->actingAs(User::factory()->doctor()->create());
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return ['name' => 'Abdur Rahim', 'gender' => 'male', 'age_years' => '40', 'phone' => '01711-000 111', ...$overrides];
    }

    public function test_the_address_is_optional(): void
    {
        $this->post('/patients', $this->payload())->assertSessionHasNoErrors();

        $patient = Patient::firstOrFail();
        $this->assertNull($patient->address);
        $this->assertTrue($patient->phone_is_personal);
        $this->assertNull($patient->phone_relation);
        $this->get("/patients/{$patient->id}")->assertOk()->assertSee('01711000111');
    }

    public function test_a_phone_that_is_not_the_patients_own_needs_a_relation(): void
    {
        $this->post('/patients', $this->payload(['phone_is_personal' => '0']))->assertSessionHasErrors('phone_relation');
        $this->post('/patients', $this->payload(['phone_is_personal' => '0', 'phone_relation' => '  ']))->assertSessionHasErrors('phone_relation');
        $this->assertSame(0, Patient::count());

        $this->post('/patients', $this->payload(['phone_is_personal' => '0', 'phone_relation' => ' Father ']))->assertSessionHasNoErrors();

        $patient = Patient::firstOrFail();
        $this->assertFalse($patient->phone_is_personal);
        $this->assertSame('Father', $patient->phone_relation);
        $this->get("/patients/{$patient->id}")->assertSee('01711000111 (Father)');
    }

    public function test_the_relation_is_cleared_when_the_number_is_the_patients_own(): void
    {
        $this->post('/patients', $this->payload(['phone_is_personal' => '0', 'phone_relation' => 'Wife']));
        $patient = Patient::firstOrFail();

        $this->put("/patients/{$patient->id}", $this->payload(['phone_is_personal' => '1', 'phone_relation' => 'Wife']))
            ->assertSessionHasNoErrors();

        $patient->refresh();
        $this->assertTrue($patient->phone_is_personal);
        $this->assertNull($patient->phone_relation);
    }

    public function test_the_checkbox_is_remembered_when_the_form_comes_back_with_errors(): void
    {
        $this->from('/patients/create')->post('/patients', $this->payload(['phone_is_personal' => '0', 'name' => '']))
            ->assertRedirect('/patients/create');

        $this->get('/patients/create')->assertOk()->assertSee('personal: false', false);
    }

    public function test_patients_without_a_phone_are_found_and_listed(): void
    {
        Patient::factory()->create(['name' => 'No Phone', 'phone' => null, 'address' => null]);
        Patient::factory()->create(['name' => 'With Phone', 'phone' => '01811223344']);

        $this->get('/patients')->assertOk()->assertSee('No Phone')->assertSee('With Phone');
        $this->get('/patients?q=0181122')->assertOk()->assertSee('With Phone')->assertDontSee('No Phone');
        $this->get('/patients?q=No+Phone')->assertOk()->assertSee('No Phone');
    }

    public function test_the_api_exposes_the_phone_owner(): void
    {
        $doctor = User::factory()->doctor()->create();
        $token = JWTAuth::fromUser($doctor);

        $this->postJson('/api/v1/patients', $this->payload(['phone_is_personal' => false, 'phone_relation' => 'Son']), ['Authorization' => "Bearer {$token}"])
            ->assertCreated()
            ->assertJsonPath('data.phone_is_personal', false)
            ->assertJsonPath('data.phone_relation', 'Son')
            ->assertJsonPath('data.address', null);
    }
}
