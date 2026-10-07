<?php

namespace Tests\Feature\Practice;

use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\PrescriptionTemplate;
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

    public function test_seeded_templates_define_both_sample_layouts_as_data(): void
    {
        $dental = PrescriptionTemplate::where('code', 'dental_pad')->with('sections')->firstOrFail();
        $gynae = PrescriptionTemplate::where('code', 'gynae_letterhead')->with('sections')->firstOrFail();

        $this->assertSame('A4', $dental->paper_size);
        $this->assertSame('pad_only', $dental->default_print_mode->value);
        $this->assertSame('sidebar_left', $dental->layout->value);
        $this->assertTrue($dental->show_barcode);
        $this->assertSame(['examination', 'treatment_plan'], $this->keysIn($dental, 'left'));
        $this->assertSame(['medicines', 'advice', 'follow_up'], $this->keysIn($dental, 'right'));

        $this->assertSame('A4', $gynae->paper_size);
        $this->assertSame('with_letterhead', $gynae->default_print_mode->value);
        $this->assertSame(['complaints', 'specialty', 'vitals'], $this->keysIn($gynae, 'left'));
        $this->assertSame(
            ['investigations_reviewed', 'investigations_advised', 'medicines', 'advice'],
            $this->keysIn($gynae, 'right'),
        );

        $this->assertSame(['complaints', 'examination', 'vitals'], $this->keysIn(
            PrescriptionTemplate::where('code', 'general')->firstOrFail(), 'left',
        ));
    }

    public function test_reseeding_keeps_edits_and_does_not_duplicate(): void
    {
        PrescriptionTemplate::where('code', 'general')->update(['name' => 'My general']);

        $this->seed(PracticeSeeder::class);

        $this->assertSame(3, PrescriptionTemplate::count());
        $this->assertSame('My general', PrescriptionTemplate::where('code', 'general')->value('name'));
        $this->assertSame(9, PrescriptionTemplate::where('code', 'general')->firstOrFail()->sections()->count());
    }

    public function test_doctor_saves_chamber_with_ordered_branches_via_api(): void
    {
        $doctor = User::factory()->doctor()->create();

        $this->putJson('/api/v1/chamber', [
            'name_en' => 'City Dental', 'name_bn' => 'সিটি ডেন্টাল',
            'branches' => [
                ['name_en' => 'Dhanmondi', 'phones' => ['01711000000', '01811000000']],
                ['name_en' => 'Uttara', 'name_bn' => 'উত্তরা', 'phones' => ['01911000000']],
            ],
        ], $this->bearer($doctor))
            ->assertCreated()
            ->assertJsonPath('data.branches.0.name_en', 'Dhanmondi')
            ->assertJsonPath('data.branches.0.phones', ['01711000000', '01811000000'])
            ->assertJsonPath('data.branches.1.name_bn', 'উত্তরা');

        // Second save replaces the list and updates the same chamber.
        $this->putJson('/api/v1/chamber', [
            'name_en' => 'City Dental', 'branches' => [['name_en' => 'Mirpur']],
        ], $this->bearer($doctor))->assertOk()->assertJsonCount(1, 'data.branches');

        $this->assertSame(1, Chamber::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'chamber.created']);
        $this->getJson('/api/v1/chamber', $this->bearer($doctor))->assertOk()->assertJsonPath('data.name_en', 'City Dental');
    }

    public function test_assistant_can_read_but_not_change_setup(): void
    {
        Chamber::create(['name_en' => 'City Dental']);
        $assistant = User::factory()->assistant()->create();
        $headers = $this->bearer($assistant);

        $this->getJson('/api/v1/chamber', $headers)->assertOk();
        $this->getJson('/api/v1/prescription-templates', $headers)->assertOk()->assertJsonCount(3, 'data');
        $this->putJson('/api/v1/chamber', ['name_en' => 'Hacked'], $headers)->assertForbidden();
        $this->putJson('/api/v1/doctors/me', ['name_en' => 'X', 'reg_label' => 'BMDC', 'specialty_code' => 'general'], $headers)->assertForbidden();
        $this->postJson('/api/v1/prescription-templates', [], $headers)->assertForbidden();

        $this->assertSame('City Dental', Chamber::first()->name_en);
    }

    public function test_doctor_profile_with_credentials_hours_and_fees(): void
    {
        $chamber = Chamber::create(['name_en' => 'City Dental']);
        $user = User::factory()->doctor()->create();

        $payload = [
            'name_en' => 'Dr. A', 'name_bn' => 'ডা. এ', 'reg_label' => 'BMDC', 'reg_no' => 'A-12345',
            'specialty_code' => 'dental',
            'default_template_id' => PrescriptionTemplate::where('code', 'dental_pad')->value('id'),
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
            ->assertCreated()
            ->assertJsonPath('data.credentials.1.text_en', 'MPH')
            ->assertJsonPath('data.chambers.0.visiting_hours_en', 'Sat-Thu 5-9pm')
            ->assertJsonPath('data.chambers.0.fees', ['new' => '500.00', 'follow_up' => '300.50', 'free' => '0.00']);

        // Replaced, not appended: dropping a credential and clearing a fee takes effect.
        $payload['credentials'] = [['text_en' => 'BDS']];
        $payload['chambers'][0]['fees'] = ['new' => '600'];
        $this->putJson('/api/v1/doctors/me', $payload, $this->bearer($user))
            ->assertOk()
            ->assertJsonCount(1, 'data.credentials')
            ->assertJsonPath('data.chambers.0.fees', ['new' => '600.00']);

        $this->assertSame(1, Doctor::count());
        $this->getJson('/api/v1/doctors/me', $this->bearer($user))->assertOk()->assertJsonPath('data.reg_no', 'A-12345');
    }

    public function test_doctor_cannot_use_another_doctors_template_as_default(): void
    {
        $other = Doctor::create(['user_id' => User::factory()->doctor()->create()->id, 'name_en' => 'Dr. B']);
        $private = PrescriptionTemplate::create([
            'doctor_id' => $other->id, 'code' => 'mine', 'name' => 'Private', 'layout' => 'single_column',
        ]);

        $this->putJson('/api/v1/doctors/me', [
            'name_en' => 'Dr. A', 'reg_label' => 'BMDC', 'specialty_code' => 'general',
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
            'code' => 'ortho_pad', 'name' => 'Ortho', 'specialty_code' => 'general', 'paper_size' => 'A4',
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

    public function test_new_default_replaces_previous_default_for_same_owner_and_specialty(): void
    {
        $user = User::factory()->superAdmin()->create();
        $general = PrescriptionTemplate::where('code', 'general')->firstOrFail();
        $dental = PrescriptionTemplate::where('code', 'dental_pad')->firstOrFail();

        $payload = fn (string $code) => [
            'code' => $code, 'name' => $code, 'specialty_code' => 'general', 'paper_size' => 'A4',
            'default_print_mode' => 'with_letterhead', 'layout' => 'two_column', 'barcode_source' => 'prescription_no',
            'is_default' => true, 'sections' => [['section_key' => 'medicines', 'zone' => 'right']],
        ];

        // Super admin has no doctor profile, so the new template is shared like the seeded ones.
        $this->postJson('/api/v1/prescription-templates', $payload('general_two'), $this->bearer($user))->assertCreated();

        $this->assertFalse($general->fresh()->is_default);
        $this->assertTrue($dental->fresh()->is_default);
    }

    public function test_settings_pages_render_and_save_through_the_web_forms(): void
    {
        $user = User::factory()->doctor()->create();
        $this->actingAs($user);

        $this->get('/settings/chamber')->assertOk();
        $this->put('/settings/chamber', [
            'name_en' => 'City Dental',
            'branches' => [
                ['name_en' => 'Dhanmondi', 'phones' => '01711, 01811'],
                ['name_en' => '', 'name_bn' => '', 'phones' => ''],
            ],
        ])->assertRedirect(route('settings.chamber.edit'));
        $chamber = Chamber::with('branches')->firstOrFail();
        $this->assertCount(1, $chamber->branches);
        $this->assertSame(['01711', '01811'], $chamber->branches[0]->phones);
        $this->get('/settings/chamber')->assertOk()->assertSee('Dhanmondi');

        $this->get('/settings/doctor')->assertOk();
        $this->put('/settings/doctor', [
            'name_en' => 'Dr. A', 'reg_label' => 'BMDC', 'specialty_code' => 'general',
            'credentials' => [['text_en' => 'MBBS'], ['text_en' => '', 'text_bn' => '']],
            'chambers' => [['chamber_id' => $chamber->id, 'enabled' => '1', 'fees' => ['new' => '500', 'follow_up' => '']]],
        ])->assertRedirect(route('settings.doctor.edit'));
        $doctor = Doctor::with(['credentials', 'fees'])->firstOrFail();
        $this->assertCount(1, $doctor->credentials);
        $this->assertCount(1, $doctor->fees);
        $this->get('/settings/doctor')->assertOk()->assertSee('MBBS');

        // Unticking the chamber detaches it.
        $this->put('/settings/doctor', [
            'name_en' => 'Dr. A', 'reg_label' => 'BMDC', 'specialty_code' => 'general',
            'chambers' => [['chamber_id' => $chamber->id, 'enabled' => '0']],
        ]);
        $this->assertCount(0, $doctor->fresh()->chambers);

        $this->get('/settings/templates')->assertOk()->assertSee('Dental pad');
        $this->get('/settings/templates/create')->assertOk();
        $template = PrescriptionTemplate::where('code', 'dental_pad')->firstOrFail();
        $this->get("/settings/templates/{$template->id}/edit")->assertOk()->assertSee('treatment_plan');
    }

    public function test_web_template_form_orders_sections_by_the_order_field(): void
    {
        $this->actingAs(User::factory()->doctor()->create());

        $this->post('/settings/templates', [
            'code' => 'ordered', 'name' => 'Ordered', 'specialty_code' => 'general', 'paper_size' => 'A4',
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

        $this->get('/settings/chamber')->assertForbidden();
        $this->get('/settings/doctor')->assertForbidden();
        $this->get('/settings/templates/create')->assertForbidden();
        $this->get('/settings/templates')->assertOk();
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
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
