<?php

namespace Tests\Feature\Practice;

use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\SectionKey;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\PracticeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** The template editor's live preview: the real print view, laid out with the unsaved form. */
class TemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessSeeder::class, PracticeSeeder::class]);
    }

    public function test_preview_renders_every_visible_section_with_sample_data(): void
    {
        $html = $this->preview($this->form())->assertOk()->getContent();

        $this->assertStringContainsString('class="sheet"', (string) $html);
        foreach (SectionKey::cases() as $key) {
            if (! in_array($key, [SectionKey::PatientBlock, SectionKey::Specialty], true)) {
                $this->assertStringContainsString('data-section="'.$key->value.'"', (string) $html, "{$key->value} should print");
            }
        }
        $this->assertStringContainsString('Rahim Uddin', (string) $html);
        $this->assertStringNotContainsString('class="toolbar"', (string) $html);
    }

    public function test_letterhead_uses_the_signed_in_doctor_and_their_chamber(): void
    {
        $user = User::factory()->doctor()->create();
        $doctor = Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. Real Name']);
        $chamber = Chamber::create(['name_en' => 'Real Chamber']);
        $doctor->chambers()->attach($chamber->id, ['visiting_hours_en' => 'Sun 6–9 pm']);

        $this->actingAs($user);
        $this->preview($this->form())->assertOk()
            ->assertSee('Dr. Real Name')->assertSee('Real Chamber')->assertSee('Sun 6–9 pm')
            ->assertDontSee('Dr. Sample Doctor');

        $this->preview([...$this->form(), 'show_visiting_hours' => 0])->assertDontSee('Sun 6–9 pm');
        $this->preview([...$this->form(), 'mode' => 'pad_only'])->assertDontSee('Dr. Real Name');
    }

    public function test_hiding_a_section_removes_it(): void
    {
        $this->preview($this->form(['advice' => ['is_visible' => 0], 'patient_block' => ['is_visible' => 0]]))
            ->assertOk()
            ->assertDontSee('data-section="advice"', false)
            ->assertDontSee('Rahim Uddin')
            ->assertSee('data-section="medicines"', false);
    }

    public function test_label_zone_and_order_follow_the_form(): void
    {
        $html = (string) $this->preview($this->form([
            'diagnosis' => ['label_en' => 'Impression', 'order' => 99],
            'vitals' => ['zone' => 'right', 'order' => 0],
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('Impression', $html);
        $right = substr($html, (int) strpos($html, '<div class="right">'));
        $this->assertStringContainsString('data-section="vitals"', $right);
        // Vitals now prints first on the right, diagnosis last.
        $this->assertLessThan(strpos($right, 'data-section="medicines"'), strpos($right, 'data-section="vitals"'));
        $this->assertGreaterThan(strpos($right, 'data-section="follow_up"'), strpos($right, 'data-section="diagnosis"'));
    }

    public function test_single_column_layout_and_bangla_labels(): void
    {
        $this->preview([...$this->form(), 'layout' => 'single_column', 'lang' => 'bn'])
            ->assertOk()
            ->assertDontSee('class="body two"', false)
            ->assertSee('পরামর্শ')
            ->assertSee('রহিম উদ্দিন');
    }

    public function test_preview_writes_nothing(): void
    {
        $tables = ['prescription_templates', 'prescription_template_sections', 'case_histories', 'patients', 'audit_logs'];
        $before = array_map(fn ($t) => DB::table($t)->count(), $tables);

        $this->preview($this->form())->assertOk();

        $this->assertSame($before, array_map(fn ($t) => DB::table($t)->count(), $tables));
    }

    public function test_invalid_form_gives_422_and_code_or_name_are_not_needed(): void
    {
        $form = $this->form();
        unset($form['code'], $form['name']);
        $this->preview($form)->assertOk();

        $this->preview([...$form, 'margins' => ['top' => 500]])->assertStatus(422)->assertJsonValidationErrors('margins.top');
    }

    public function test_editor_pages_show_the_preview_panel(): void
    {
        $this->actingAs(User::factory()->doctor()->create());
        $id = DB::table('prescription_templates')->value('id');

        foreach ([route('settings.templates.create'), route('settings.templates.edit', $id)] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('templatePreview(', false)
                ->assertSee('settings\\/templates\\/preview', false)
                ->assertSee('Live preview');
        }
    }

    public function test_only_template_managers_can_preview(): void
    {
        $this->actingAs(User::factory()->assistant()->create())
            ->postJson(route('settings.templates.preview'), $this->form())->assertForbidden();

        auth()->logout();
        $this->post(route('settings.templates.preview'), $this->form())->assertRedirect(route('login'));
    }

    /** @param  array<string, mixed>  $data */
    private function preview(array $data): TestResponse
    {
        if (! auth()->check()) {
            $this->actingAs(User::factory()->doctor()->create());
        }

        return $this->postJson(route('settings.templates.preview'), $data);
    }

    /**
     * The editor's fields for the seeded general template, with per-section overrides.
     *
     * @param  array<string, array<string, mixed>>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        $sections = DB::table('prescription_template_sections')->orderBy('sort_order')->get()
            ->map(fn ($s, $i) => [
                'order' => $i + 1, 'section_key' => $s->section_key, 'zone' => $s->zone,
                'label_en' => $s->label_en, 'label_bn' => $s->label_bn, 'is_visible' => (int) $s->is_visible,
                ...($overrides[$s->section_key] ?? []),
            ])->all();

        return [
            'name' => 'General', 'code' => 'general', 'paper_size' => 'A4', 'layout' => 'two_column',
            'default_print_mode' => 'with_letterhead', 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            'barcode_source' => 'prescription_no', 'show_signature' => 1, 'show_visiting_hours' => 1, 'show_branch_footer' => 1,
            'sections' => $sections,
        ];
    }
}
