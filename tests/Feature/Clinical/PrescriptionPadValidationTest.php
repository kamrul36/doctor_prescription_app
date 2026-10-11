<?php

namespace Tests\Feature\Clinical;

use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\Specialty;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\PracticeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/** Pad fields reject a wrong format on the web and the API, and the pad mirrors the rules (spec §10.1). */
class PrescriptionPadValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessSeeder::class, PracticeSeeder::class, CatalogSeeder::class]);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidValues(): array
    {
        $medicine = ['name' => 'Napa 500', 'dose_morning' => '1', 'dose_noon' => '0', 'dose_night' => '1', 'duration_value' => '7', 'duration_unit' => 'day'];

        return [
            'phone with letters' => [['patient' => ['phone' => '0171abc5678']], 'patient.phone'],
            'phone too short' => [['patient' => ['phone' => '017']], 'patient.phone'],
            'age over 130' => [['patient' => ['age_years' => '200']], 'patient.age_years'],
            'unknown sex' => [['patient' => ['gender' => 'x']], 'patient.gender'],
            'complaint duration 0' => [['complaints' => [['text' => 'Fever', 'duration_value' => '0', 'duration_unit' => 'day']]], 'complaints.0.duration_value'],
            'blood pressure out of range' => [['vitals' => ['bp_sys' => '500']], 'vitals.bp_sys'],
            'temperature not a number' => [['vitals' => ['temp' => 'hot']], 'vitals.temp'],
            'dose slot not a number' => [['medicines' => [[...$medicine, 'dose_morning' => 'x']]], 'medicines.0.dose'],
            'duration over the limit' => [['medicines' => [[...$medicine, 'duration_value' => '1000']]], 'medicines.0.duration_value'],
            'fee with three decimals' => [['plan_items' => [['description' => 'Scaling', 'fee' => '12.345']]], 'plan_items.0.fee'],
            'follow-up over a year' => [['follow_up_value' => '400', 'follow_up_unit' => 'day'], 'follow_up_value'],
            'follow-up date in the past' => [['follow_up_date' => '2000-01-01'], 'follow_up_date'],
            'diagnosis too long' => [['diagnosis' => str_repeat('a', 2001)], 'diagnosis'],
        ];
    }

    /**
     * @dataProvider invalidValues
     *
     * @param  array<string, mixed>  $overrides
     */
    public function test_a_wrong_format_is_rejected_on_web_and_api(array $overrides, string $field): void
    {
        [$user, $chamber] = $this->doctor();
        $payload = $this->payload($chamber, $overrides);

        $this->actingAs($user)->from('/prescriptions/create')->post('/prescriptions', $payload)
            ->assertRedirect('/prescriptions/create')
            ->assertSessionHasErrors($field);

        $this->postJson('/api/v1/case-histories', $payload, ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertSame(0, CaseHistory::count());
    }

    public function test_the_pad_mirrors_the_rules(): void
    {
        [$user] = $this->doctor(['gynae']);

        $this->actingAs($user)->get('/prescriptions/create')->assertOk()
            ->assertSee('x-data="validatedForm(', false)
            ->assertSee('required maxlength="20" data-rule="phone"', false)
            ->assertSee('data-rule="dose-slot"', false)
            ->assertSee('data-rule="money"', false)
            // From Vitals::FIELDS and the gynae schema, through ClientRules.
            ->assertSee('name="vitals[bp_sys]" value="" inputmode="decimal" data-rule="integer" data-min="40" data-max="300"', false)
            ->assertSee('name="specialty_data[gynae][lmp]" value="" placeholder="" data-rule="past-date"', false);
    }

    public function test_a_finalize_problem_is_a_warning_toast_and_a_field_error(): void
    {
        [$user, $chamber] = $this->doctor();

        $response = $this->actingAs($user)->post('/prescriptions', [
            ...$this->payload($chamber, []), 'medicines' => [], 'action' => 'finalize',
        ]);

        $response->assertSessionHas('warning')->assertSessionHasErrors('diagnosis');
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee('window.toast?.warning(', false)
            ->assertDontSee('window.toast?.error(', false);
    }

    /** @return array{0: User, 1: Chamber} */
    private function doctor(array $specialties = []): array
    {
        $user = User::factory()->doctor()->create();
        $doctor = Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. '.$user->id]);
        $chamber = Chamber::create(['name_en' => 'Chamber '.$user->id]);
        $doctor->chambers()->attach($chamber->id);
        $doctor->specialties()->attach(Specialty::whereIn('code', $specialties)->pluck('id'));

        return [$user, $chamber];
    }

    /**
     * A valid new-patient pad, with the overrides merged in (patient fields one level deep).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Chamber $chamber, array $overrides): array
    {
        $patient = ['name' => 'Karim Uddin', 'age_years' => '45', 'gender' => 'male', 'phone' => '01712345678', 'address' => ''];

        return [
            'chamber_id' => $chamber->id,
            'action' => 'draft',
            ...$overrides,
            'patient' => [...$patient, ...($overrides['patient'] ?? [])],
        ];
    }
}
