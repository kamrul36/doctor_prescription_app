<?php

namespace Tests\Feature\Document;

use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Patient\Models\Patient;
use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\PracticeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/** The A4 print view and the API export in English, Bangla and bilingual (spec §6). */
class PrescriptionPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessSeeder::class, PracticeSeeder::class]);
    }

    public function test_english_print(): void
    {
        $case = $this->finalizedCase();

        $this->actingAs($this->user)->get(route('prescriptions.print', [$case, 'lang' => 'en']))->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Advice')->assertDontSee('পরামর্শ')
            ->assertSee('Napa 500')->assertSee('2+0+2 tablet · 7 days · after meal')
            ->assertSee('Drink plenty of water')->assertDontSee('প্রচুর পানি পান করবেন')
            ->assertSee('Karim Uddin')->assertSee('Fever — 3 days')
            ->assertSee('After 7 days')
            ->assertSee($case->prescription_no);
    }

    public function test_bangla_print_with_bangla_digits(): void
    {
        $case = $this->finalizedCase();

        $this->actingAs($this->user)->get(route('prescriptions.print', [$case, 'lang' => 'bn']))->assertOk()
            ->assertSee('<html lang="bn">', false)
            ->assertSee('পরামর্শ')->assertDontSee('>Advice<', false)
            ->assertSee('২+০+২ ট্যাবলেট · ৭ দিন · খাবারের পরে')
            ->assertSee('প্রচুর পানি পান করবেন')->assertDontSee('Drink plenty of water')
            ->assertSee('করিম উদ্দিন')
            ->assertSee('Fever — ৩ দিন')
            ->assertSee('৭ দিন পর')
            ->assertSee('৪৫ বছর')
            ->assertSee('পুরুষ');
    }

    public function test_bangla_digits_can_be_turned_off(): void
    {
        $case = $this->finalizedCase();

        $this->actingAs($this->user)->get(route('prescriptions.print', [$case, 'lang' => 'bn', 'bn_digits' => 0]))->assertOk()
            ->assertSee('2+0+2 ট্যাবলেট · 7 দিন · খাবারের পরে')
            ->assertSee('45 বছর');
    }

    public function test_bilingual_print(): void
    {
        $case = $this->finalizedCase();

        $this->actingAs($this->user)->get(route('prescriptions.print', [$case, 'lang' => 'bilingual']))->assertOk()
            ->assertSee('Advice / পরামর্শ')
            ->assertSee('Drink plenty of water')->assertSee('প্রচুর পানি পান করবেন')
            ->assertSee('2+0+2 tablet · 7 days · after meal')->assertSee('২+০+২ ট্যাবলেট · ৭ দিন · খাবারের পরে')
            ->assertSee('Karim Uddin / করিম উদ্দিন')
            ->assertSee('Male / পুরুষ');
    }

    public function test_a_value_missing_in_one_language_prints_the_other(): void
    {
        $case = $this->finalizedCase();

        // The doctor has no Bangla name: the Bangla print shows the English one.
        $this->actingAs($this->user)->get(route('prescriptions.print', [$case, 'lang' => 'bn']))
            ->assertSee('Dr. Rahman')
            // The instruction was typed in English only.
            ->assertSee('Gargle after brushing');

        // Visiting hours were entered in Bangla only: the English print shows them.
        $this->get(route('prescriptions.print', [$case, 'lang' => 'en']))->assertSee('সন্ধ্যা ৬টা - ৯টা');
    }

    public function test_default_language_follows_the_ui_language(): void
    {
        $case = $this->finalizedCase();

        $this->actingAs($this->user)->get(route('prescriptions.print', $case))->assertSee('<html lang="en">', false);

        $this->user->forceFill(['locale' => 'bn'])->save();
        $this->get(route('prescriptions.print', $case))->assertSee('<html lang="bn">', false)->assertSee('২+০+২');
    }

    public function test_pad_only_leaves_out_the_letterhead(): void
    {
        $case = $this->finalizedCase();

        $this->actingAs($this->user)->get(route('prescriptions.print', [$case, 'lang' => 'en', 'mode' => 'with_letterhead']))
            ->assertSee('class="letterhead"', false)->assertSee('City Chamber');

        $this->get(route('prescriptions.print', [$case, 'lang' => 'en', 'mode' => 'pad_only']))
            ->assertDontSee('class="letterhead"', false)->assertDontSee('City Chamber')
            ->assertSee('Napa 500');
    }

    public function test_a_draft_prints_only_as_a_preview_for_its_doctor(): void
    {
        $case = $this->finalizedCase(finalize: false);

        $this->actingAs($this->user)->get(route('prescriptions.print', [$case, 'lang' => 'en']))->assertOk()
            ->assertSee('class="watermark"', false)->assertSee('DRAFT');
        $this->get(route('prescriptions.print', [$case, 'lang' => 'bn']))->assertSee('খসড়া');

        [$other] = $this->doctor();
        $this->actingAs($other)->get(route('prescriptions.print', $case))->assertForbidden();
    }

    public function test_finalized_prescriptions_print_for_staff_with_the_print_permission(): void
    {
        $case = $this->finalizedCase();

        $this->actingAs(User::factory()->assistant()->create())->get(route('prescriptions.print', $case))->assertOk()
            ->assertDontSee('class="watermark"', false);
    }

    public function test_cancelled_prescriptions_do_not_print(): void
    {
        $case = $this->finalizedCase(finalize: false);
        $this->actingAs($this->user)->post(route('prescriptions.cancel', $case), ['reason' => 'Wrong patient']);

        $this->get(route('prescriptions.print', $case))->assertForbidden();
    }

    public function test_invalid_options_are_rejected(): void
    {
        $case = $this->finalizedCase();

        $this->actingAs($this->user)->get(route('prescriptions.print', [$case, 'lang' => 'fr']))->assertSessionHasErrors('lang');
        $this->get(route('prescriptions.print', [$case, 'mode' => 'poster']))->assertSessionHasErrors('mode');
    }

    public function test_the_prescription_page_links_to_each_print_language(): void
    {
        $case = $this->finalizedCase();

        $this->actingAs($this->user)->get(route('prescriptions.show', $case))->assertOk()
            ->assertSee(route('prescriptions.print', [$case, 'lang' => 'en']), false)
            ->assertSee(route('prescriptions.print', [$case, 'lang' => 'bn']), false)
            ->assertSee(route('prescriptions.print', [$case, 'lang' => 'bilingual']), false)
            ->assertDontSee('arrives with P1.6');
    }

    public function test_the_toolbar_switches_language_and_is_hidden_from_api_exports(): void
    {
        $case = $this->finalizedCase();

        $this->actingAs($this->user)->get(route('prescriptions.print', [$case, 'lang' => 'bn']))
            ->assertSee('class="toolbar"', false)
            ->assertSee('name="lang" value="bilingual"', false)
            ->assertSee('Print / Save as PDF');

        $this->get("/api/v1/case-histories/{$case->id}/export?format=html&lang=bn", $this->bearer($this->user))->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('পরামর্শ')
            ->assertDontSee('class="toolbar"', false);
    }

    public function test_api_export_validates_format_and_language(): void
    {
        $case = $this->finalizedCase();
        $h = $this->bearer($this->user);

        $this->getJson("/api/v1/case-histories/{$case->id}/export", $h)->assertUnprocessable()->assertJsonValidationErrors('format');
        $this->getJson("/api/v1/case-histories/{$case->id}/export?format=pdf", $h)->assertUnprocessable()->assertJsonValidationErrors('format');
        $this->getJson("/api/v1/case-histories/{$case->id}/export?format=html&lang=fr", $h)->assertUnprocessable()->assertJsonValidationErrors('lang');
        $this->get("/api/v1/case-histories/{$case->id}/export?format=html&lang=bilingual", $h)->assertOk()->assertSee('Advice / পরামর্শ');
    }

    /** A finalized (or draft) visit by "Dr. Rahman" for Karim Uddin, with Bangla twins for some fields. */
    private function finalizedCase(bool $finalize = true): CaseHistory
    {
        [$this->user, $doctor, $chamber] = $this->doctor('Dr. Rahman');
        $doctor->chambers()->updateExistingPivot($chamber->id, ['visiting_hours_bn' => 'সন্ধ্যা ৬টা - ৯টা']);
        $patient = Patient::factory()->create([
            'name' => 'Karim Uddin', 'name_bn' => 'করিম উদ্দিন', 'gender' => 'male', 'age_years' => 45, 'dob' => null, 'age_recorded_on' => now(),
        ]);

        $this->actingAs($this->user)->post('/prescriptions', [
            'chamber_id' => $chamber->id,
            'patient_id' => $patient->id,
            'diagnosis' => 'Viral fever',
            'complaints' => [['text' => 'Fever', 'duration_value' => '3', 'duration_unit' => 'day']],
            'medicines' => [[
                'name' => 'Napa 500', 'dose_morning' => '2', 'dose_noon' => '0', 'dose_night' => '2',
                'dose_unit' => 'tablet', 'timing' => 'after_meal', 'duration_value' => '7', 'duration_unit' => 'day',
                'instruction' => 'Gargle after brushing',
            ]],
            'advice' => [['text' => 'Drink plenty of water', 'text_bn' => 'প্রচুর পানি পান করবেন']],
            'follow_up_value' => '7', 'follow_up_unit' => 'day',
            'action' => $finalize ? 'finalize' : 'draft',
        ])->assertSessionHasNoErrors();

        return CaseHistory::query()->latest('id')->firstOrFail();
    }

    /** @return array{User, Doctor, Chamber} */
    private function doctor(?string $name = null): array
    {
        $user = User::factory()->doctor()->create();
        $doctor = Doctor::create(['user_id' => $user->id, 'name_en' => $name ?? 'Dr. '.$user->id]);
        $chamber = Chamber::create(['name_en' => 'City Chamber', 'name_bn' => 'সিটি চেম্বার']);
        $doctor->chambers()->attach($chamber->id);

        return [$user, $doctor, $chamber];
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
