<?php

namespace Tests\Feature\Patient;

use App\Domain\Finance\NumberGenerator;
use App\Domain\Patient\Actions\SavePatientAction;
use App\Domain\Patient\Models\Patient;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PatientTest extends TestCase
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

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Abdur Rahim', 'gender' => 'male', 'dob' => '1990-05-10',
            'phone' => '01711-000 111', 'address' => 'Mirpur, Dhaka', ...$overrides,
        ];
    }

    public function test_first_patient_gets_code_26000001_and_codes_never_change(): void
    {
        $doctor = User::factory()->doctor()->create();
        $h = $this->bearer($doctor);

        $first = $this->postJson('/api/v1/patients', $this->payload(), $h)
            ->assertCreated()
            ->assertJsonPath('data.code', '26000001')
            ->assertJsonPath('data.phone', '01711000111')
            ->json('data');
        $this->postJson('/api/v1/patients', $this->payload(['name' => 'Second']), $h)
            ->assertCreated()->assertJsonPath('data.code', '26000002');

        $this->putJson("/api/v1/patients/{$first['id']}", $this->payload(['name' => 'Renamed', 'code' => '99']), $h)
            ->assertOk()->assertJsonPath('data.code', '26000001')->assertJsonPath('data.name', 'Renamed');

        $this->assertDatabaseHas('audit_logs', ['action' => 'patient.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient.updated']);
    }

    public function test_failed_registration_does_not_burn_a_code(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());

        $this->postJson('/api/v1/patients', $this->payload(['gender' => 'robot']), $h)->assertUnprocessable();
        $this->postJson('/api/v1/patients', $this->payload(), $h)->assertCreated()->assertJsonPath('data.code', '26000001');
    }

    public function test_many_registrations_get_unique_gap_free_codes(): void
    {
        $generator = app(NumberGenerator::class);
        $codes = [];

        for ($i = 0; $i < 25; $i++) {
            $codes[] = $generator->next(NumberGenerator::PATIENT);
        }

        $this->assertCount(25, array_unique($codes));
        $this->assertSame('26000001', $codes[0]);
        $this->assertSame('26000025', $codes[24]);
    }

    public function test_code_restarts_in_a_new_year(): void
    {
        $generator = app(NumberGenerator::class);
        $generator->next(NumberGenerator::PATIENT);

        Carbon::setTestNow('2027-01-02');

        $this->assertSame('27000001', $generator->next(NumberGenerator::PATIENT));
    }

    public function test_dob_or_age_is_required_and_age_advances(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());

        $this->postJson('/api/v1/patients', $this->payload(['dob' => null]), $h)
            ->assertUnprocessable()->assertJsonValidationErrors(['dob', 'age_years']);
        $this->postJson('/api/v1/patients', $this->payload(['dob' => '2999-01-01']), $h)
            ->assertUnprocessable()->assertJsonValidationErrors('dob');

        $id = $this->postJson('/api/v1/patients', $this->payload(['dob' => null, 'age_years' => 30]), $h)
            ->assertCreated()->assertJsonPath('data.age_years', 30)->assertJsonPath('data.age_text', '30 y')
            ->json('data.id');
        $this->assertSame('2026-10-07', Patient::find($id)->age_recorded_on->toDateString());

        Carbon::setTestNow('2028-11-01');
        $this->assertSame(32, Patient::find($id)->ageYears());

        // A date of birth wins and clears the told age. (Called directly: JWTs
        // issued before the clock jump would be expired in test time.)
        $saved = app(SavePatientAction::class)->handle(Patient::findOrFail($id), $this->payload(['dob' => '2000-01-01']));

        $this->assertSame(28, $saved->ageYears());
        $this->assertNull(Patient::find($id)->age_years);
        $this->assertNull(Patient::find($id)->age_recorded_on);
    }

    public function test_resaving_the_same_told_age_keeps_its_original_date(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());
        $id = $this->postJson('/api/v1/patients', $this->payload(['dob' => null, 'age_years' => 30]), $h)->json('data.id');

        Carbon::setTestNow('2027-12-01');
        app(SavePatientAction::class)->handle(Patient::findOrFail($id), $this->payload(['dob' => null, 'age_years' => 31]));

        $this->assertSame('2026-10-07', Patient::find($id)->age_recorded_on->toDateString());
    }

    public function test_babies_show_months_or_days(): void
    {
        $this->assertSame('7 m', Patient::factory()->make(['dob' => now()->subMonths(7)])->age_text);
        $this->assertSame('12 d', Patient::factory()->make(['dob' => now()->subDays(12)])->age_text);
    }

    public function test_allergies_and_conditions_are_stored_but_not_audited(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());

        $this->postJson('/api/v1/patients', $this->payload([
            'allergies' => 'Penicillin - rash', 'conditions' => 'Diabetes, Hypertension',
        ]), $h)->assertCreated()->assertJsonPath('data.allergies', 'Penicillin - rash');

        $this->assertStringNotContainsString('Penicillin', (string) \DB::table('audit_logs')->pluck('properties')->join(' '));
    }

    public function test_assistant_can_register_and_read(): void
    {
        $assistant = $this->bearer(User::factory()->assistant()->create());
        $patient = Patient::factory()->create();

        $this->postJson('/api/v1/patients', $this->payload(), $assistant)->assertCreated();
        $this->getJson("/api/v1/patients/{$patient->id}", $assistant)->assertOk();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $patient = Patient::factory()->create();
        $nobody = $this->bearer(User::factory()->create());

        $this->getJson('/api/v1/patients', $nobody)->assertForbidden();
        $this->postJson('/api/v1/patients', $this->payload(), $nobody)->assertForbidden();
        $this->getJson("/api/v1/patients/{$patient->id}", $nobody)->assertForbidden();
        $this->deleteJson("/api/v1/patients/{$patient->id}", [], $nobody)->assertForbidden();
    }

    public function test_guests_are_unauthorized(): void
    {
        $this->getJson('/api/v1/patients')->assertUnauthorized();
        $this->get('/patients')->assertRedirect('/login');
    }

    public function test_delete_is_soft_and_hides_the_patient(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());
        $patient = Patient::factory()->create();

        $this->deleteJson("/api/v1/patients/{$patient->id}", [], $h)->assertNoContent();

        $this->assertSoftDeleted($patient);
        $this->getJson("/api/v1/patients/{$patient->id}", $h)->assertNotFound();
        $this->getJson('/api/v1/patients', $h)->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient.deleted']);
    }

    public function test_summary_and_view_audit(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());
        $patient = Patient::factory()->create(['allergies' => 'Sulfa']);

        $this->getJson("/api/v1/patients/{$patient->id}/summary", $h)
            ->assertOk()->assertJsonPath('data.allergies', 'Sulfa')->assertJsonPath('data.code', $patient->code);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient.viewed', 'auditable_id' => $patient->id]);
    }

    public function test_search_by_code_phone_fragment_and_name_typo(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());
        $rahim = Patient::factory()->create(['code' => '26000001', 'name' => 'Abdur Rahim', 'phone' => '01711223344']);
        Patient::factory()->create(['code' => '26000002', 'name' => 'Rahman Ali', 'phone' => '01899887766']);
        Patient::factory()->create(['code' => '26000003', 'name' => 'Karim Hossain', 'phone' => '01555000999', 'name_bn' => 'করিম হোসেন']);

        $ids = fn (string $q) => collect($this->getJson('/api/v1/patients?q='.urlencode($q), $h)->assertOk()->json('data'))->pluck('code')->all();

        $this->assertSame(['26000001'], $ids('26000001'));
        $this->assertSame(['26000001'], $ids('2233'));           // phone fragment
        $this->assertSame(['26000001'], $ids('01711 22'));        // phone with spaces
        $this->assertSame('26000001', $ids('Rahiem')[0]);        // typo
        $this->assertSame('26000001', $ids('rahim')[0]);         // case
        $this->assertSame('26000003', $ids('করিম')[0]);          // Bangla
        $this->assertSame('26000003', $ids('Karym')[0]);
        $this->assertNotContains('26000002', $ids('Rahim'));     // Rahman is not Rahim
        $this->assertSame([], $ids('Zzzzzz'));

        $rahim->delete();
        $this->assertSame([], $ids('Rahim'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient.searched']);
    }

    public function test_search_audit_never_stores_the_term(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());
        $this->getJson('/api/v1/patients?q=Secret+Name', $h)->assertOk();

        $this->assertStringNotContainsString('Secret', (string) \DB::table('audit_logs')->pluck('properties')->join(' '));
    }

    public function test_search_paginates_and_blank_lists_newest_first(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());
        Patient::factory()->count(3)->create();

        $this->getJson('/api/v1/patients?per_page=2', $h)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
        $this->getJson('/api/v1/patients?per_page=2&page=2', $h)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_web_crud_flow(): void
    {
        $this->actingAs(User::factory()->doctor()->create());

        $this->get('/patients')->assertOk();
        $this->get('/patients/create')->assertOk();

        $response = $this->post('/patients', $this->payload(['dob' => '', 'age_years' => '40']));
        $patient = Patient::firstOrFail();
        $response->assertRedirect("/patients/{$patient->id}");
        $this->assertSame('26000001', $patient->code);

        $this->get("/patients/{$patient->id}")->assertOk()->assertSee('26000001')->assertSee('Abdur Rahim');
        $this->get("/patients/{$patient->id}/edit")->assertOk();
        $this->put("/patients/{$patient->id}", $this->payload(['name' => 'Edited', 'dob' => '', 'age_years' => '40']))
            ->assertRedirect("/patients/{$patient->id}");
        $this->get('/patients?q=Edited')->assertOk()->assertSee('26000001');
        $this->post('/patients', [])->assertSessionHasErrors(['name', 'gender', 'phone', 'address']);
        $this->delete("/patients/{$patient->id}")->assertRedirect('/patients');
        $this->assertSoftDeleted($patient);
    }

    public function test_web_requires_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/patients')->assertForbidden();
        $this->post('/patients', $this->payload())->assertForbidden();
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        // Otherwise the guard and the JWT singleton keep the previous request's user and token.
        $this->app['auth']->forgetGuards();
        JWTAuth::unsetToken();

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
