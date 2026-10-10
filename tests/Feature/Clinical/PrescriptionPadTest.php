<?php

namespace Tests\Feature\Clinical;

use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Clinical\VisitStatus;
use App\Domain\Patient\Models\Patient;
use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\Specialty;
use App\Domain\Practice\VisitType;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\PracticeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The prescription pad on the web: pad-first flow, auto-registration, drafts and finalizing. */
class PrescriptionPadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessSeeder::class, PracticeSeeder::class, CatalogSeeder::class]);
    }

    public function test_prescribe_button_on_the_patients_list_opens_a_prefilled_pad(): void
    {
        [$user] = $this->doctor();
        $patient = Patient::factory()->create(['name' => 'Rahima Begum', 'code' => '26000042']);

        $this->actingAs($user)->get('/patients')->assertOk()
            ->assertSee('Prescribe')
            ->assertSee(route('prescriptions.create', ['patient' => $patient->id]), false);

        $this->get(route('prescriptions.create', ['patient' => $patient->id]))->assertOk()
            ->assertSee('Rahima Begum')->assertSee('26000042')
            ->assertSee('Medicines');
    }

    public function test_pad_needs_a_doctor_profile_and_an_assigned_chamber(): void
    {
        $user = User::factory()->doctor()->create();
        $this->actingAs($user)->get('/prescriptions/create')->assertRedirect(route('settings.doctor.edit'));

        Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. A']);
        $this->get('/prescriptions/create')->assertRedirect(route('settings.doctor.edit'));
        $this->get('/settings/doctor')->assertSee('No chamber is assigned to you yet');
    }

    public function test_a_new_patient_is_registered_when_the_prescription_is_saved(): void
    {
        [$user, , $chamber] = $this->doctor();

        $response = $this->actingAs($user)->post('/prescriptions', $this->pad($chamber) + [
            'patient' => ['name' => 'Karim Uddin', 'age_years' => '45', 'gender' => 'male', 'phone' => '0171-234 5678', 'address' => ''],
            'action' => 'draft',
        ]);

        $patient = Patient::where('name', 'Karim Uddin')->firstOrFail();
        $case = CaseHistory::firstOrFail();
        $response->assertRedirect(route('prescriptions.edit', $case))->assertSessionHas('status', fn ($s) => str_contains($s, $patient->code));
        $this->assertSame('01712345678', $patient->phone);
        $this->assertSame(45, $patient->ageYears());
        $this->assertMatchesRegularExpression('/^\d{8}$/', $patient->code);
        $this->assertSame($patient->id, $case->patient_id);
        $this->assertSame(VisitStatus::Draft, $case->status);
        $this->assertSame(1, $case->visit_no_today);
        $this->assertSame(['Napa 500'], $case->medicines->pluck('display_name')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'case.created']);
    }

    public function test_a_possible_duplicate_patient_asks_before_registering(): void
    {
        [$user, , $chamber] = $this->doctor();
        $existing = Patient::factory()->create(['name' => 'Karim Uddin', 'phone' => '01712345678']);
        $details = ['patient' => ['name' => 'Karim Uddin', 'age_years' => '45', 'gender' => 'male', 'phone' => '01712345678']];

        // Same phone: nothing is saved, the pad comes back with a chooser.
        $this->actingAs($user)->from('/prescriptions/create')->post('/prescriptions', $this->pad($chamber) + $details)
            ->assertRedirect('/prescriptions/create')->assertSessionHas('patient_candidates', [$existing->id]);
        $this->assertSame(0, CaseHistory::count());
        $this->get('/prescriptions/create')->assertSee('may already be registered')->assertSee($existing->code);

        // "Use this patient": the prescription goes to the existing record.
        $this->post('/prescriptions', $this->pad($chamber) + $details + ['patient_choice' => (string) $existing->id]);
        $this->assertSame([$existing->id], CaseHistory::pluck('patient_id')->all());
        $this->assertSame(1, Patient::count());

        // "Register new" creates a second record on purpose.
        $this->post('/prescriptions', $this->pad($chamber) + $details + ['patient_choice' => 'new']);
        $this->assertSame(2, Patient::count());
    }

    public function test_draft_is_edited_then_finalized_and_becomes_read_only(): void
    {
        [$user, $doctor, $chamber] = $this->doctor();
        $patient = Patient::factory()->create();

        $this->actingAs($user)->post('/prescriptions', $this->pad($chamber) + ['patient_id' => $patient->id, 'action' => 'draft']);
        $case = CaseHistory::firstOrFail();
        $this->get(route('prescriptions.edit', $case))->assertOk()->assertSee('Napa 500');

        $this->put(route('prescriptions.update', $case), $this->pad($chamber, [
            'diagnosis' => 'Viral fever',
            'complaints' => [['text' => 'Fever', 'duration_value' => '3', 'duration_unit' => 'day'], ['text' => '']],
            'vitals' => ['bp_sys' => '120', 'bp_dia' => '80', 'pulse' => '', 'temp' => '101.5'],
            'tests' => [['name' => 'CBC', 'lab_test_id' => '', 'kind' => 'advised']],
            'advice' => [['text' => 'Drink plenty of water']],
            'follow_up_value' => '7', 'follow_up_unit' => 'day',
            'notes_private' => 'Check again',
            'action' => 'finalize',
        ]))->assertRedirect(route('prescriptions.show', $case));

        $case->refresh();
        $this->assertSame(VisitStatus::Finalized, $case->status);
        $this->assertSame('RX-'.now()->year.'-000001', $case->prescription_no);
        $this->assertSame($patient->code, $case->patient_snapshot['code']);
        $this->assertSame($doctor->name_en, $case->doctor_snapshot['name_en']);
        $this->assertSame($chamber->name_en, $case->doctor_snapshot['chamber']['name_en']);
        $this->assertSame(['bp_sys' => 120, 'bp_dia' => 80, 'temp' => 101.5], $case->vitals);
        $this->assertSame(['Fever'], $case->complaints->pluck('text')->all());

        $this->get(route('prescriptions.show', $case))->assertOk()
            ->assertSee($case->prescription_no)->assertSee('Viral fever')->assertSee('2+0+2 tablet · 7 days')->assertSee('Check again');
        // Read-only now.
        $this->get(route('prescriptions.edit', $case))->assertRedirect(route('prescriptions.show', $case));
        $this->put(route('prescriptions.update', $case), $this->pad($chamber, ['diagnosis' => 'Changed']))->assertForbidden();
        $this->assertSame('Viral fever', $case->fresh()->diagnosis);
        // Finalizing again changes nothing.
        $this->post(route('prescriptions.finalize', $case))->assertRedirect(route('prescriptions.show', $case));
        $this->assertSame('RX-'.now()->year.'-000001', $case->fresh()->prescription_no);
    }

    public function test_finalize_needs_valid_doses_but_the_draft_is_kept(): void
    {
        [$user, , $chamber] = $this->doctor();
        $patient = Patient::factory()->create();

        $this->actingAs($user)->post('/prescriptions', [
            'patient_id' => $patient->id, 'chamber_id' => $chamber->id,
            'medicines' => [['name' => 'Napa 500', 'dose_morning' => '', 'dose_noon' => '', 'dose_night' => '', 'duration_value' => '7', 'duration_unit' => 'day']],
            'action' => 'finalize',
        ]);

        $case = CaseHistory::firstOrFail();
        $this->assertSame(VisitStatus::Draft, $case->status, 'the work is saved as a draft');
        $this->assertNull($case->prescription_no);
        $this->assertSame(0, \DB::table('sequence_counters')->where('key', 'prescription')->count(), 'no RX number is used');

        // A filled-in dose must be valid even in a draft.
        $this->from(route('prescriptions.edit', $case))->put(route('prescriptions.update', $case), $this->pad($chamber, [
            'medicines' => [['name' => 'Napa 500', 'dose_morning' => '0', 'dose_noon' => '0', 'dose_night' => '0', 'duration_unit' => 'continuous']],
        ]))->assertSessionHasErrors('medicines.0.dose');

        // And finalize with nothing to print is refused.
        $this->put(route('prescriptions.update', $case), ['chamber_id' => $chamber->id, 'medicines' => [], 'action' => 'finalize'])
            ->assertSessionHasErrors('diagnosis');
    }

    public function test_assistant_reads_prescriptions_but_cannot_write_or_see_private_notes(): void
    {
        [$user, , $chamber] = $this->doctor();
        $this->actingAs($user)->post('/prescriptions', $this->pad($chamber, ['notes_private' => 'Secret note']) + ['patient_id' => Patient::factory()->create()->id]);
        $case = CaseHistory::firstOrFail();

        $this->actingAs(User::factory()->assistant()->create());
        $this->get('/prescriptions')->assertOk()->assertSee($case->patient->name)->assertDontSee('+ New prescription');
        $this->get(route('prescriptions.show', $case))->assertOk()->assertSee('Napa 500')->assertDontSee('Secret note');
        $this->get('/prescriptions/create')->assertForbidden();
        $this->post('/prescriptions', $this->pad($chamber) + ['patient_id' => $case->patient_id])->assertForbidden();
        $this->put(route('prescriptions.update', $case), $this->pad($chamber))->assertForbidden();
        $this->post(route('prescriptions.finalize', $case))->assertForbidden();
        $this->get('/patients')->assertDontSee('Prescribe');
    }

    public function test_another_doctor_cannot_change_or_finalize_a_draft(): void
    {
        [$user, , $chamber] = $this->doctor();
        $this->actingAs($user)->post('/prescriptions', $this->pad($chamber) + ['patient_id' => Patient::factory()->create()->id]);
        $case = CaseHistory::firstOrFail();

        [$other, , $otherChamber] = $this->doctor();
        $this->actingAs($other);
        $this->get(route('prescriptions.edit', $case))->assertForbidden();
        $this->put(route('prescriptions.update', $case), $this->pad($otherChamber))->assertForbidden();
        $this->post(route('prescriptions.finalize', $case))->assertForbidden();
        $this->post(route('prescriptions.cancel', $case), ['reason' => 'mine now'])->assertForbidden();
        $this->assertSame(VisitStatus::Draft, $case->fresh()->status);
    }

    public function test_only_the_doctors_own_specialty_blocks_and_chambers_are_accepted(): void
    {
        [$gynaeUser, , $chamber] = $this->doctor(['gynae']);
        $patient = Patient::factory()->create();

        $this->actingAs($gynaeUser)->get('/prescriptions/create')->assertSee('Menstrual &amp; obstetric history', false)->assertDontSee('Dental findings');
        $this->post('/prescriptions', $this->pad($chamber, [
            'specialty_data' => ['gynae' => ['parity' => 'P2+1', 'lmp' => now()->subDays(20)->toDateString(), 'cycle_days' => '']],
        ]) + ['patient_id' => $patient->id])->assertSessionHasNoErrors();
        $this->assertSame(['gynae' => ['parity' => 'P2+1', 'lmp' => now()->subDays(20)->toDateString()]], CaseHistory::firstOrFail()->specialty_data);

        $this->from('/prescriptions/create')->post('/prescriptions', $this->pad($chamber, ['specialty_data' => ['dental' => ['findings' => 'Calculus']]]) + ['patient_id' => $patient->id])
            ->assertSessionHasErrors('specialty_data');

        // A general physician has no blocks at all.
        [$generalUser, , $generalChamber] = $this->doctor();
        $this->actingAs($generalUser)->from('/prescriptions/create')
            ->post('/prescriptions', $this->pad($generalChamber, ['specialty_data' => ['gynae' => ['parity' => 'P1']]]) + ['patient_id' => $patient->id])
            ->assertSessionHasErrors('specialty_data');

        // Someone else's chamber is refused.
        $this->from('/prescriptions/create')->post('/prescriptions', $this->pad($chamber) + ['patient_id' => $patient->id])
            ->assertSessionHasErrors('chamber_id');
    }

    public function test_daily_serial_follow_up_suggestion_and_cancel(): void
    {
        [$user, , $chamber] = $this->doctor();
        $patient = Patient::factory()->create();
        $this->actingAs($user);

        $this->post('/prescriptions', $this->pad($chamber, ['diagnosis' => 'URTI', 'action' => 'finalize']) + ['patient_id' => $patient->id]);
        $this->post('/prescriptions', $this->pad($chamber) + ['patient_id' => Patient::factory()->create()->id]);
        $this->assertSame([1, 2], CaseHistory::orderBy('id')->pluck('visit_no_today')->all());

        // Back within 14 days: the pad suggests a follow-up, and a saved visit without a type gets it.
        $this->get(route('prescriptions.create', ['patient' => $patient->id]))->assertSee('value="follow_up" selected', false);
        $this->post('/prescriptions', $this->pad($chamber) + ['patient_id' => $patient->id]);
        $this->assertSame(VisitType::FollowUp, CaseHistory::latest('id')->first()->visit_type);

        $draft = CaseHistory::where('status', 'draft')->firstOrFail();
        $this->post(route('prescriptions.cancel', $draft), ['reason' => 'Patient left'])->assertRedirect(route('prescriptions.index'));
        $this->assertSame(VisitStatus::Cancelled, $draft->fresh()->status);
        $this->assertSame(3, CaseHistory::count(), 'cancelled, not deleted');

        $this->get('/prescriptions')->assertOk()->assertSee($patient->name);
        $this->get(route('patients.show', $patient))->assertOk()->assertSee('Visit history')->assertSee('URTI');
    }

    public function test_lookup_endpoints_serve_the_pad_on_the_session(): void
    {
        [$user] = $this->doctor(['gynae']);

        $this->getJson('/lookup/lab-tests?search=f')->assertUnauthorized();

        $names = $this->actingAs($user)->getJson('/lookup/lab-tests?search=f&take=10')->assertOk()->json('data.*.name');
        // Gynae tests (FSH) rank with the general ones, before other specialties' items.
        $this->assertContains('FSH', $names);
        $this->getJson('/lookup/procedures?search=sca')->assertOk()->assertJsonPath('data.0.code', 'SCALING');
        $this->getJson('/lookup/advice-templates?search=ante')->assertOk()->assertJsonPath('data.0.title', 'Antenatal advice');
        $this->getJson('/lookup/patients?q=zzzz')->assertOk()->assertJsonPath('data', []);
    }

    /**
     * A doctor with a profile, an assigned chamber and optional specialties.
     *
     * @param  list<string>  $specialties
     * @return array{User, Doctor, Chamber}
     */
    private function doctor(array $specialties = []): array
    {
        $user = User::factory()->doctor()->create();
        $doctor = Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. '.$user->id]);
        $chamber = Chamber::create(['name_en' => 'Chamber '.$user->id]);
        $doctor->chambers()->attach($chamber->id);
        $doctor->specialties()->attach(Specialty::whereIn('code', $specialties)->pluck('id'));

        return [$user, $doctor, $chamber];
    }

    /**
     * A minimal pad submission with one medicine.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pad(Chamber $chamber, array $overrides = []): array
    {
        return $overrides + [
            'chamber_id' => $chamber->id,
            'medicines' => [[
                'name' => 'Napa 500', 'dose_morning' => '2', 'dose_noon' => '0', 'dose_night' => '2',
                'dose_unit' => 'tablet', 'timing' => 'after_meal', 'duration_value' => '7', 'duration_unit' => 'day',
            ]],
        ];
    }
}
