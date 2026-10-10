<?php

namespace Tests\Feature\Practice;

use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\Models\Specialty;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\PracticeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PracticeSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessSeeder::class, PracticeSeeder::class]);
    }

    public function test_seeder_creates_one_general_template_with_a_specialty_section(): void
    {
        $general = PrescriptionTemplate::where('code', 'general')->with('sections')->firstOrFail();

        // Specialties are not templates any more: one general template for every doctor.
        $this->assertSame(1, PrescriptionTemplate::count());
        $this->assertNull($general->doctor_id);
        $this->assertTrue($general->is_default);
        $this->assertSame('A4', $general->paper_size);
        $this->assertSame('with_letterhead', $general->default_print_mode->value);
        $this->assertSame('two_column', $general->layout->value);
        $this->assertSame(['patient_block'], $this->keysIn($general, 'header'));
        $this->assertSame(['complaints', 'specialty', 'examination', 'vitals'], $this->keysIn($general, 'left'));
        $this->assertSame(
            ['diagnosis', 'treatment_plan', 'investigations_reviewed', 'investigations_advised', 'medicines', 'advice', 'follow_up'],
            $this->keysIn($general, 'right'),
        );
    }

    public function test_reseeding_keeps_edits_and_does_not_duplicate(): void
    {
        PrescriptionTemplate::where('code', 'general')->update(['name' => 'My general']);

        $this->seed(PracticeSeeder::class);

        $this->assertSame(1, PrescriptionTemplate::count());
        $this->assertSame('My general', PrescriptionTemplate::where('code', 'general')->value('name'));
        $this->assertSame(12, PrescriptionTemplate::where('code', 'general')->firstOrFail()->sections()->count());
    }

    public function test_reseeding_never_recreates_or_removes_retired_specialty_templates(): void
    {
        // A database migrated from the old seeds still holds the retired, inactive pads.
        $retired = PrescriptionTemplate::create([
            'code' => 'dental_pad', 'name' => 'Dental pad', 'layout' => 'sidebar_left', 'is_active' => false,
        ]);

        $this->seed(PracticeSeeder::class);

        $this->assertFalse($retired->fresh()->is_active);
        $this->assertSame(0, PrescriptionTemplate::where('code', 'gynae_letterhead')->count());
        $this->assertSame(2, PrescriptionTemplate::count());
    }

    public function test_assistant_can_read_but_not_change_setup(): void
    {
        $chamber = Chamber::create(['name_en' => 'City Dental']);
        $assistant = User::factory()->assistant()->create();
        $headers = $this->bearer($assistant);

        $this->getJson('/api/v1/chambers', $headers)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/specialties', $headers)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/prescription-templates', $headers)->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/v1/chambers/{$chamber->id}", ['name_en' => 'Hacked'], $headers)->assertForbidden();
        $this->putJson('/api/v1/doctors/me', ['name_en' => 'X', 'reg_label' => 'BMDC', 'specialty_ids' => [$this->specialtyId('gynae')]], $headers)->assertForbidden();
        $this->postJson('/api/v1/prescription-templates', [], $headers)->assertForbidden();

        $this->assertSame('City Dental', Chamber::first()->name_en);
    }

    public function test_doctor_profile_with_credentials_hours_and_fees(): void
    {
        $chamber = Chamber::create(['name_en' => 'City Dental']);
        $user = User::factory()->doctor()->create();
        // The admin assigns the chamber; the doctor only sets hours and fees.
        Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. A'])->chambers()->attach($chamber->id);
        [$gynae, $dental] = [$this->specialtyId('gynae'), $this->specialtyId('dental')];

        $payload = [
            'name_en' => 'Dr. A', 'name_bn' => 'ডা. এ', 'reg_label' => 'BMDC', 'reg_no' => 'A-12345',
            'specialty_ids' => [$gynae, $dental],
            'default_template_id' => PrescriptionTemplate::where('code', 'general')->value('id'),
            'credentials' => [
                ['text_en' => 'BDS', 'text_bn' => 'বিডিএস'],
                ['text_en' => 'MPH'],
            ],
            'chambers' => [[
                'chamber_id' => $chamber->id,
                'visiting_hours_en' => 'Sat-Thu 5-9pm',
                'fees' => ['new' => '500', 'follow_up' => '300.50', 'free' => '0', 'bogus' => '9'],
            ]],
        ];

        $this->putJson('/api/v1/doctors/me', $payload, $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('data.specialties.*.code', ['dental', 'gynae'])
            ->assertJsonPath('data.chambers.0.name_en', 'City Dental')
            ->assertJsonPath('data.credentials.1.text_en', 'MPH')
            ->assertJsonPath('data.chambers.0.visiting_hours_en', 'Sat-Thu 5-9pm')
            ->assertJsonPath('data.chambers.0.fees', ['new' => '500.00', 'follow_up' => '300.50', 'free' => '0.00']);

        // Replaced, not appended: dropping a credential, a specialty and a fee takes effect.
        $payload['specialty_ids'] = [$gynae];
        $payload['credentials'] = [['text_en' => 'BDS']];
        $payload['chambers'][0]['fees'] = ['new' => '600'];
        $this->putJson('/api/v1/doctors/me', $payload, $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('data.specialties.*.code', ['gynae'])
            ->assertJsonCount(1, 'data.credentials')
            ->assertJsonPath('data.chambers.0.fees', ['new' => '600.00']);

        $this->assertSame(1, Doctor::count());
        $this->getJson('/api/v1/doctors/me', $this->bearer($user))->assertOk()
            ->assertJsonPath('data.reg_no', 'A-12345')
            ->assertJsonPath('data.specialties.*.code', ['gynae']);
    }

    public function test_doctor_cannot_assign_or_change_a_chamber_the_admin_did_not_assign(): void
    {
        $mine = Chamber::create(['name_en' => 'Mine']);
        $other = Chamber::create(['name_en' => 'Other']);
        $user = User::factory()->doctor()->create();
        $doctor = Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. A']);
        $doctor->chambers()->attach($mine->id);

        $this->putJson('/api/v1/doctors/me', [
            'name_en' => 'Dr. A', 'reg_label' => 'BMDC',
            'chambers' => [['chamber_id' => $mine->id, 'visiting_hours_en' => 'Sun'], ['chamber_id' => $other->id, 'visiting_hours_en' => 'Mon']],
        ], $this->bearer($user))->assertUnprocessable()->assertJsonValidationErrors('chambers.1.chamber_id');

        // Nothing changed: still only the assigned chamber, without the rejected hours.
        $this->assertSame([$mine->id], $doctor->chambers()->pluck('chambers.id')->all());
        $this->assertNull($doctor->chambers()->first()->pivot->visiting_hours_en);

        // Leaving a chamber out of the payload never detaches it.
        $this->putJson('/api/v1/doctors/me', ['name_en' => 'Dr. A', 'reg_label' => 'BMDC', 'chambers' => []], $this->bearer($user))->assertOk();
        $this->assertSame(1, $doctor->chambers()->count());
    }

    public function test_doctor_specialties_from_the_list_or_typed_and_cleared_via_api(): void
    {
        $user = User::factory()->doctor()->create();
        $headers = $this->bearer($user);
        $base = ['name_en' => 'Dr. A', 'reg_label' => 'BMDC'];
        $dental = $this->specialtyId('dental');

        // A general physician needs no specialty at all.
        $this->putJson('/api/v1/doctors/me', $base, $headers)->assertCreated()->assertJsonPath('data.specialties', []);

        $inactive = Specialty::create(['code' => 'old', 'name' => 'Old', 'is_active' => false]);
        foreach ([[999], [$dental, $dental], [$inactive->id]] as $bad) {
            $this->putJson('/api/v1/doctors/me', $base + ['specialty_ids' => $bad], $headers)
                ->assertUnprocessable()->assertJsonValidationErrors(count($bad) === 2 ? 'specialty_ids.1' : 'specialty_ids.0');
        }
        $this->putJson('/api/v1/doctors/me', $base + ['specialty_ids' => 'x'], $headers)->assertJsonValidationErrors('specialty_ids');

        // A typed specialty joins the shared list once; a second doctor typing it in another case reuses it.
        $this->putJson('/api/v1/doctors/me', $base + ['specialty_ids' => [$dental], 'new_specialties' => ['Cardiology']], $headers)
            ->assertOk()->assertJsonPath('data.specialties.*.name', ['Cardiology', 'Dental']);
        $other = User::factory()->doctor()->create();
        $this->putJson('/api/v1/doctors/me', $base + ['new_specialties' => ['  cardiology ']], $this->bearer($other))
            ->assertCreated()->assertJsonPath('data.specialties.*.name', ['Cardiology']);
        $this->assertSame(1, Specialty::where('name', 'Cardiology')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'specialty.created']);

        // Leaving the keys out keeps them; an empty list clears them.
        $this->putJson('/api/v1/doctors/me', $base, $this->bearer($user))->assertJsonCount(2, 'data.specialties');
        $this->putJson('/api/v1/doctors/me', $base + ['specialty_ids' => []], $this->bearer($user))->assertJsonPath('data.specialties', []);
    }

    public function test_a_specialty_the_admin_deactivates_stays_with_its_doctors(): void
    {
        $user = User::factory()->doctor()->create();
        $doctor = Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. A']);
        $old = Specialty::create(['code' => 'old', 'name' => 'Old']);
        $doctor->specialties()->attach([$old->id, $this->specialtyId('dental')]);
        $old->update(['is_active' => false]);

        // The profile form no longer shows it, so saving the visible list keeps it.
        $this->actingAs($user)->get('/settings/doctor')->assertOk()->assertDontSee('name="specialty_ids['.$old->id.']"', false);
        $this->putJson('/api/v1/doctors/me', ['name_en' => 'Dr. A', 'reg_label' => 'BMDC', 'specialty_ids' => []], $this->bearer($user))->assertOk();

        $this->assertSame([$old->id], $doctor->fresh()->specialtyIds());
    }

    public function test_doctor_cannot_use_another_doctors_template_as_default(): void
    {
        $other = Doctor::create(['user_id' => User::factory()->doctor()->create()->id, 'name_en' => 'Dr. B']);
        $private = PrescriptionTemplate::create([
            'doctor_id' => $other->id, 'code' => 'mine', 'name' => 'Private', 'layout' => 'single_column',
        ]);

        $this->putJson('/api/v1/doctors/me', [
            'name_en' => 'Dr. A', 'reg_label' => 'BMDC',
            'default_template_id' => $private->id,
        ], $this->bearer(User::factory()->doctor()->create()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('default_template_id');
    }

    public function test_template_create_update_default_and_validation(): void
    {
        $user = User::factory()->doctor()->create();
        Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. A']);
        $headers = $this->bearer($user);
        $payload = [
            'code' => 'ortho_pad', 'name' => 'Ortho', 'paper_size' => 'A4',
            'default_print_mode' => 'pad_only', 'layout' => 'single_column',
            'margins' => ['top' => 40, 'right' => 10, 'bottom' => 20, 'left' => 10],
            'barcode_source' => 'patient_code', 'is_default' => true,
            'sections' => [
                ['section_key' => 'medicines', 'zone' => 'left', 'label_en' => 'Rx', 'is_visible' => true],
                ['section_key' => 'advice', 'zone' => 'left', 'is_visible' => false],
            ],
        ];

        $id = $this->postJson('/api/v1/prescription-templates', $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('data.sections.0.section_key', 'medicines')
            ->assertJsonPath('data.sections.1.is_visible', false)
            ->json('data.id');

        // Owned by the doctor, so the shared general default is untouched.
        $this->assertTrue(PrescriptionTemplate::where('code', 'general')->value('is_default'));

        $payload['sections'] = [['section_key' => 'advice', 'zone' => 'right']];
        $this->putJson("/api/v1/prescription-templates/{$id}", $payload, $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data.sections');

        $this->postJson('/api/v1/prescription-templates', $payload, $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $bad = ['code' => 'Bad Code', 'layout' => 'diagonal', 'sections' => [
            ['section_key' => 'advice', 'zone' => 'right'], ['section_key' => 'advice', 'zone' => 'left'],
        ]] + $payload;
        $this->postJson('/api/v1/prescription-templates', $bad, $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'layout', 'sections.0.section_key']);
    }

    public function test_new_default_replaces_previous_default_for_same_owner(): void
    {
        $user = User::factory()->superAdmin()->create();
        $general = PrescriptionTemplate::where('code', 'general')->firstOrFail();
        $doctor = Doctor::create(['user_id' => User::factory()->doctor()->create()->id, 'name_en' => 'Dr. B']);
        $doctorsDefault = PrescriptionTemplate::create([
            'doctor_id' => $doctor->id, 'code' => 'mine', 'name' => 'Mine', 'layout' => 'single_column', 'is_default' => true,
        ]);

        $payload = fn (string $code) => [
            'code' => $code, 'name' => $code, 'paper_size' => 'A4',
            'default_print_mode' => 'with_letterhead', 'layout' => 'two_column', 'barcode_source' => 'prescription_no',
            'is_default' => true, 'sections' => [['section_key' => 'medicines', 'zone' => 'right']],
        ];

        // Super admin has no doctor profile, so the new template is shared like the seeded ones.
        $this->postJson('/api/v1/prescription-templates', $payload('general_two'), $this->bearer($user))->assertCreated();

        $this->assertFalse($general->fresh()->is_default);
        $this->assertTrue(PrescriptionTemplate::where('code', 'general_two')->value('is_default'));
        // Another owner's default is untouched.
        $this->assertTrue($doctorsDefault->fresh()->is_default);
    }

    public function test_settings_pages_render_and_save_through_the_web_forms(): void
    {
        $user = User::factory()->doctor()->create();
        $this->actingAs($user);
        [$gynae, $dental] = [$this->specialtyId('gynae'), $this->specialtyId('dental')];

        // Before the admin assigns a chamber, the profile says so.
        $this->get('/settings/doctor')->assertOk()
            ->assertSee('General practice is always included')
            ->assertSee('Gynaecology &amp; Obstetrics', false)
            ->assertSee('No chamber assigned yet');

        $chamber = Chamber::create(['name_en' => 'City Dental']);
        Chamber::create(['name_en' => 'Not mine']);
        Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. A'])->chambers()->attach($chamber->id);
        $this->get('/settings/doctor')->assertOk()->assertSee('City Dental')->assertDontSee('Not mine');

        $this->put('/settings/doctor', [
            'name_en' => 'Dr. A', 'reg_label' => 'BMDC',
            // The form sends a hidden 0 and a checkbox 1 per specialty, plus a box for typed ones.
            'specialty_ids' => [$gynae => '1', $dental => '0'],
            'new_specialties' => 'Diabetology, ',
            'credentials' => [['text_en' => 'MBBS'], ['text_en' => '', 'text_bn' => '']],
            'chambers' => [['chamber_id' => $chamber->id, 'visiting_hours_en' => 'Sat 5-9pm', 'fees' => ['new' => '500', 'follow_up' => '']]],
        ])->assertRedirect(route('settings.doctor.edit'));
        $doctor = Doctor::with(['credentials', 'fees', 'specialties'])->firstOrFail();
        $this->assertCount(1, $doctor->credentials);
        $this->assertCount(1, $doctor->fees);
        $this->assertEqualsCanonicalizing(['Diabetology', 'Gynaecology & Obstetrics'], $doctor->specialties->pluck('name')->all());
        $diabetology = Specialty::where('name', 'Diabetology')->value('id');
        $this->get('/settings/doctor')->assertOk()->assertSee('MBBS')->assertSee('Sat 5-9pm')
            ->assertSee('name="specialty_ids['.$gynae.']" value="1" checked', false)
            ->assertSee('name="specialty_ids['.$diabetology.']" value="1" checked', false)
            ->assertDontSee('name="specialty_ids['.$dental.']" value="1" checked', false);

        // Unticking every specialty clears them; the chamber stays (only the admin unassigns).
        $this->put('/settings/doctor', [
            'name_en' => 'Dr. A', 'reg_label' => 'BMDC',
            'specialty_ids' => [$gynae => '0', $dental => '0', $diabetology => '0'],
        ]);
        $this->assertCount(1, $doctor->fresh()->chambers);
        $this->assertSame([], $doctor->fresh()->specialtyIds());

        // An unknown specialty goes back with an error and keeps the ticks.
        $this->from('/settings/doctor')->put('/settings/doctor', [
            'name_en' => 'Dr. A', 'reg_label' => 'BMDC', 'specialty_ids' => [$gynae => '1', 9999 => '1'],
        ])->assertRedirect('/settings/doctor')->assertSessionHasErrors('specialty_ids.1');
        $this->get('/settings/doctor')->assertSee('name="specialty_ids['.$gynae.']" value="1" checked', false);

        $this->get('/settings/templates')->assertOk()->assertSee('General')->assertDontSee('Specialty');
        $this->get('/settings/templates/create')->assertOk()->assertDontSee('name="specialty_code"', false);
        $template = PrescriptionTemplate::where('code', 'general')->firstOrFail();
        $this->get("/settings/templates/{$template->id}/edit")->assertOk()->assertSee('treatment_plan')->assertSee('specialty');
    }

    public function test_web_template_form_orders_sections_by_the_order_field(): void
    {
        $this->actingAs(User::factory()->doctor()->create());

        $this->post('/settings/templates', [
            'code' => 'ordered', 'name' => 'Ordered', 'paper_size' => 'A4',
            'default_print_mode' => 'pad_only', 'layout' => 'single_column', 'barcode_source' => 'prescription_no',
            'sections' => [
                ['order' => 2, 'section_key' => 'advice', 'zone' => 'right', 'is_visible' => '1'],
                ['order' => 1, 'section_key' => 'medicines', 'zone' => 'right', 'is_visible' => '1'],
            ],
        ])->assertRedirect(route('settings.templates.index'));

        $template = PrescriptionTemplate::where('code', 'ordered')->firstOrFail();
        $this->assertSame(['medicines', 'advice'], $this->keysIn($template, 'right'));
    }

    public function test_assistant_is_blocked_from_settings_pages_that_edit(): void
    {
        $this->actingAs(User::factory()->assistant()->create());

        $this->get('/settings/chamber')->assertNotFound();
        $this->get('/admin/chambers')->assertForbidden();
        $this->get('/settings/doctor')->assertForbidden();
        $this->get('/settings/templates/create')->assertForbidden();
        $this->get('/settings/templates')->assertOk();
    }

    private function specialtyId(string $code): int
    {
        return (int) Specialty::where('code', $code)->value('id');
    }

    /** @return list<string> */
    private function keysIn(PrescriptionTemplate $template, string $zone): array
    {
        return $template->sections()->get()
            ->filter(fn ($s) => $s->zone->value === $zone)
            ->map(fn ($s) => $s->section_key->value)
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        // Otherwise the guard and JWT singletons keep the previous request's user when a test switches users.
        $this->app['auth']->forgetGuards();
        foreach (['tymon.jwt', 'tymon.jwt.auth', 'tymon.jwt.parser'] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
        JWTAuth::clearResolvedInstances();
        JWTAuth::unsetToken();

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
