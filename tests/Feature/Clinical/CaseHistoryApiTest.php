<?php

namespace Tests\Feature\Clinical;

use App\Domain\Patient\Models\Patient;
use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\Specialty;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\PracticeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/** `case-histories` API: same rules as the web pad, with a 409 for possible duplicate patients. */
class CaseHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessSeeder::class, PracticeSeeder::class]);
    }

    public function test_create_with_a_new_patient_update_and_finalize(): void
    {
        [$user, $chamber] = $this->doctor(['gynae']);
        $h = $this->bearer($user);

        $id = $this->postJson('/api/v1/case-histories', [
            'chamber_id' => $chamber->id,
            'patient' => ['name' => 'Salma Akter', 'age_years' => 29, 'gender' => 'female', 'phone' => '01811111111'],
            'complaints' => [['text' => 'Irregular cycle', 'duration_value' => 6, 'duration_unit' => 'month']],
            'specialty_data' => ['gynae' => ['parity' => 'P1', 'cycle_regular' => 'irregular']],
            'tests' => [['name' => 'FSH', 'timing_note' => 'D2']],
            'medicines' => [[
                'name' => 'Tab. Biofol 5', 'dose_morning' => '1', 'dose_noon' => '0', 'dose_night' => '0',
                'dose_unit' => 'tablet', 'duration_value' => 6, 'duration_unit' => 'month',
            ], [
                'name' => 'Cap. Traxyl 500', 'dose_morning' => '1', 'dose_noon' => '1', 'dose_night' => '1',
                'dose_unit' => 'capsule', 'duration_unit' => 'as_needed',
            ]],
        ], $h)
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.patient.name', 'Salma Akter')
            ->assertJsonPath('data.medicines.0.dose', '1+0+0')
            ->assertJsonPath('data.medicines.0.summary', '1+0+0 tablet · 6 months')
            ->assertJsonPath('data.medicines.1.summary', '1+1+1 capsule · as needed')
            ->assertJsonPath('data.specialty_data.gynae.parity', 'P1')
            ->json('data.id');

        // Update replaces only the blocks sent; the patient can't change after creation.
        $this->putJson("/api/v1/case-histories/{$id}", ['chamber_id' => $chamber->id, 'diagnosis' => 'PCOS'], $h)
            ->assertOk()->assertJsonPath('data.diagnosis', 'PCOS')->assertJsonCount(2, 'data.medicines');
        $this->putJson("/api/v1/case-histories/{$id}", ['chamber_id' => $chamber->id, 'patient_id' => 1], $h)
            ->assertUnprocessable()->assertJsonValidationErrors('patient_id');

        $this->postJson("/api/v1/case-histories/{$id}/finalize", [], $h)->assertOk()
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.prescription_no', 'RX-'.now()->year.'-000001')
            ->assertJsonPath('data.patient_snapshot.name', 'Salma Akter');
        $this->putJson("/api/v1/case-histories/{$id}", ['chamber_id' => $chamber->id, 'diagnosis' => 'X'], $h)->assertForbidden();

        $patientId = Patient::where('name', 'Salma Akter')->value('id');
        $this->getJson("/api/v1/patients/{$patientId}/case-histories", $h)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tests.0.timing_note', 'D2');
        $this->getJson('/api/v1/case-histories?mine=1&status=finalized', $h)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_possible_duplicate_is_a_409_with_candidates(): void
    {
        [$user, $chamber] = $this->doctor();
        $existing = Patient::factory()->create(['phone' => '01811111111']);
        $payload = [
            'chamber_id' => $chamber->id, 'diagnosis' => 'URTI',
            'patient' => ['name' => 'Somebody Else', 'age_years' => 40, 'gender' => 'male', 'phone' => '01811111111'],
        ];

        $this->postJson('/api/v1/case-histories', $payload, $this->bearer($user))
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('candidates.0.id', $existing->id);

        $this->postJson('/api/v1/case-histories', $payload + ['patient_choice' => (string) $existing->id], $this->bearer($user))
            ->assertCreated()->assertJsonPath('data.patient_id', $existing->id);
        $this->postJson('/api/v1/case-histories', $payload + ['patient_choice' => 'new'], $this->bearer($user))
            ->assertCreated();
        $this->assertSame(2, Patient::count());

        $this->postJson('/api/v1/case-histories', ['chamber_id' => $chamber->id, 'patient' => ['name' => 'X']], $this->bearer($user))
            ->assertUnprocessable()->assertJsonValidationErrors(['patient.age_years', 'patient.gender', 'patient.phone']);
    }

    public function test_assistant_reads_but_cannot_write_and_notes_stay_private(): void
    {
        [$user, $chamber] = $this->doctor();
        $id = $this->postJson('/api/v1/case-histories', [
            'chamber_id' => $chamber->id, 'patient_id' => Patient::factory()->create()->id, 'notes_private' => 'Secret',
        ], $this->bearer($user))->assertCreated()->assertJsonPath('data.notes_private', 'Secret')->json('data.id');

        $assistant = $this->bearer(User::factory()->assistant()->create());
        $this->getJson("/api/v1/case-histories/{$id}", $assistant)->assertOk()->assertJsonMissingPath('data.notes_private');
        $this->postJson('/api/v1/case-histories', ['chamber_id' => $chamber->id], $assistant)->assertForbidden();
        $this->postJson("/api/v1/case-histories/{$id}/finalize", [], $assistant)->assertForbidden();
        $this->postJson("/api/v1/case-histories/{$id}/cancel", ['reason' => 'nope'], $assistant)->assertForbidden();

        $this->postJson("/api/v1/case-histories/{$id}/cancel", ['reason' => 'Duplicate visit'], $this->bearer($user))
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/case-histories/{$id}/finalize", [], $this->bearer($user))->assertForbidden();
    }

    /**
     * @param  list<string>  $specialties
     * @return array{User, Chamber}
     */
    private function doctor(array $specialties = []): array
    {
        $user = User::factory()->doctor()->create();
        $doctor = Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. '.$user->id]);
        $chamber = Chamber::create(['name_en' => 'Chamber '.$user->id]);
        $doctor->chambers()->attach($chamber->id);
        $doctor->specialties()->attach(Specialty::whereIn('code', $specialties)->pluck('id'));

        return [$user, $chamber];
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
