<?php

namespace Tests\Feature;

use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\PracticeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Setup, catalog, admin and login forms (spec §10.1): each format rule rejects a
 * wrong value on the web and the API, and each form mirrors its rules in the browser.
 */
class FormValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessSeeder::class, PracticeSeeder::class]);
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: array<string, mixed>, 5: string}> */
    public static function invalidValues(): array
    {
        return [
            // [who, web url, api url, valid payload, overrides, error key]
            'user phone too short' => ['admin', '/admin/users', '/api/v1/users', 'user', ['phone' => '01712'], 'phone'],
            'user without phone' => ['admin', '/admin/users', '/api/v1/users', 'user', ['phone' => ''], 'phone'],
            'user email' => ['admin', '/admin/users', '/api/v1/users', 'user', ['email' => 'not-an-email'], 'email'],
            'user short password' => ['admin', '/admin/users', '/api/v1/users', 'user', ['password' => 'short'], 'password'],
            'role name with capitals' => ['admin', '/admin/roles', '/api/v1/roles', 'role', ['name' => 'Receptionist'], 'name'],
            'specialty name too short' => ['admin', '/admin/specialties', '/api/v1/specialties', 'specialty', ['name' => 'X'], 'name'],
            'chamber phone with letters' => ['admin', '/admin/chambers', '/api/v1/chambers', 'chamber', ['phone' => '01711 abc'], 'phone'],
            'chamber email' => ['admin', '/admin/chambers', '/api/v1/chambers', 'chamber', ['email' => 'x@'], 'email'],
            'branch phone too short' => ['admin', '/admin/chambers', '/api/v1/chambers', 'chamber', ['branches' => [['name_en' => 'Mirpur', 'phones' => '0171']]], 'branches.0.phones'],
            'branch without a name' => ['admin', '/admin/chambers', '/api/v1/chambers', 'chamber', ['branches' => [['name_en' => '', 'phones' => '01711000000']]], 'branches.0.name_en'],
            'procedure code with spaces' => ['doctor', '/catalog/procedures', '/api/v1/procedures', 'procedure', ['code' => 'RCT 1'], 'code'],
            'procedure fee with three decimals' => ['doctor', '/catalog/procedures', '/api/v1/procedures', 'procedure', ['default_fee' => '10.555'], 'default_fee'],
            'lab test name too long' => ['doctor', '/catalog/lab-tests', '/api/v1/lab-tests', 'labTest', ['name' => str_repeat('a', 256)], 'name'],
            'advice text missing' => ['doctor', '/catalog/advice-templates', '/api/v1/advice-templates', 'advice', ['text_en' => ''], 'text_en'],
            'template code with capitals' => ['doctor', '/settings/templates', '/api/v1/prescription-templates', 'template', ['code' => 'MyPad'], 'code'],
            'template margin over 100' => ['doctor', '/settings/templates', '/api/v1/prescription-templates', 'template', ['margins' => ['top' => 150]], 'margins.top'],
            'template section order not a number' => ['doctor', '/settings/templates', '/api/v1/prescription-templates', 'template', ['sections' => [['order' => 'x', 'section_key' => 'medicines', 'zone' => 'right']]], 'sections.0.order'],
        ];
    }

    /**
     * @dataProvider invalidValues
     *
     * @param  array<string, mixed>  $overrides
     */
    public function test_a_wrong_format_is_rejected_on_web_and_api(string $who, string $web, string $api, string $payload, array $overrides, string $field): void
    {
        $user = $who === 'admin' ? User::factory()->superAdmin()->create() : $this->doctorUser();
        $data = [...$this->{$payload}(), ...$overrides];

        $this->actingAs($user)->from($web.'/create')->post($web, $data)
            ->assertRedirect($web.'/create')
            ->assertSessionHasErrors($field);

        $this->postJson($api, $data, ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_doctor_profile_fees_and_typed_specialties_are_checked(): void
    {
        $user = $this->doctorUser();
        $chamber = Chamber::create(['name_en' => 'City']);
        Doctor::where('user_id', $user->id)->firstOrFail()->chambers()->attach($chamber->id);
        $data = ['name_en' => 'Dr. A', 'reg_label' => 'BMDC', 'chambers' => [['chamber_id' => $chamber->id, 'fees' => ['new' => '5.555']]], 'new_specialties' => 'X'];

        $this->actingAs($user)->from('/settings/doctor')->put('/settings/doctor', $data)
            ->assertRedirect('/settings/doctor')
            ->assertSessionHasErrors(['chambers.0.fees.new', 'new_specialties.0']);
    }

    public function test_valid_values_still_save_and_contact_phones_are_kept_as_typed(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post('/admin/chambers', [...$this->chamber(), 'phone' => '01711-000000, 10666', 'branches' => [['name_en' => 'Mirpur', 'phones' => '01711, 02-9000000']]])
            ->assertRedirect('/admin/chambers')->assertSessionHas('success');
        $chamber = Chamber::with('branches')->where('name_en', 'City Clinic')->firstOrFail();
        $this->assertSame('01711-000000, 10666', $chamber->phone);
        $this->assertSame(['01711', '02-9000000'], $chamber->branches[0]->phones);

        $this->post('/admin/users', [...$this->user(), 'phone' => '0171 234-5678'])->assertRedirect('/admin/users');
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'phone' => '01712345678']);
    }

    public function test_template_errors_point_at_the_row_typed_in_and_valid_orders_still_sort(): void
    {
        $this->actingAs($this->doctorUser());
        $sections = [
            ['order' => '2', 'section_key' => 'advice', 'zone' => 'right'],
            ['order' => '-1', 'section_key' => 'medicines', 'zone' => 'right'],
        ];

        $this->from('/settings/templates/create')->post('/settings/templates', [...$this->template(), 'sections' => $sections])
            ->assertSessionHasErrors('sections.1.order')
            ->assertSessionDoesntHaveErrors('sections.0.order');

        $sections[1]['order'] = '1';
        $this->post('/settings/templates', [...$this->template(), 'sections' => $sections])->assertRedirect(route('settings.templates.index'));
        $template = PrescriptionTemplate::where('code', 'my_pad')->with('sections')->firstOrFail();
        $this->assertSame(['medicines', 'advice'], $template->sections->sortBy('sort_order')->pluck('section_key.value')->all());
    }

    /** @return array<string, array{0: string, 1: string, 2: list<string>}> */
    public static function forms(): array
    {
        return [
            'users' => ['admin', '/admin/users/create', ['data-rule="local-phone"', 'minlength="8"', 'x-text="err(\'email\')"']],
            'roles' => ['admin', '/admin/roles/create', ['data-pattern="[a-z][a-z0-9_]*"', 'id="permissions-error"']],
            'specialties' => ['admin', '/admin/specialties/create', ['minlength="2" maxlength="100"']],
            'chambers' => ['admin', '/admin/chambers/create', ['data-rule="phone-list" data-max-items="4"', 'rowErr(`branches.${i}`)']],
            'procedures' => ['doctor', '/catalog/procedures/create', ['data-pattern-message="Use 2 to 32 letters', 'data-rule="money"', 'x-text="err(\'specialty_id\')"']],
            'lab tests' => ['doctor', '/catalog/lab-tests/create', ['x-text="err(\'default_timing_note\')"']],
            'advice' => ['doctor', '/catalog/advice-templates/create', ['x-text="err(\'text_en\')"']],
            'templates' => ['doctor', '/settings/templates/create', ['data-rule="integer" data-min="0" data-max="100"', 'data-max="999"']],
            'my profile' => ['doctor', '/settings/doctor', ['data-rule="list" data-max-items="5"', 'maxlength="32"']],
            'login' => ['guest', '/login', ['x-text="err(\'login\')"']],
        ];
    }

    /**
     * @dataProvider forms
     *
     * @param  list<string>  $markup
     */
    public function test_each_form_mirrors_its_rules(string $who, string $url, array $markup): void
    {
        if ($who !== 'guest') {
            $this->actingAs($who === 'admin' ? User::factory()->superAdmin()->create() : $this->doctorUser());
        }

        $response = $this->get($url)->assertOk()
            ->assertSee('x-data="validatedForm(', false)
            ->assertSee('x-show="otherErrors.length"', false);

        foreach ($markup as $html) {
            $response->assertSee($html, false);
        }
    }

    private function doctorUser(): User
    {
        $user = User::factory()->doctor()->create();
        Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. '.$user->id]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function user(): array
    {
        return ['name' => 'New', 'email' => 'new@example.com', 'phone' => '01712345000', 'password' => 'secret-pass', 'roles' => ['assistant']];
    }

    /** @return array<string, mixed> */
    private function role(): array
    {
        return ['name' => 'receptionist', 'permissions' => []];
    }

    /** @return array<string, mixed> */
    private function specialty(): array
    {
        return ['name' => 'Cardiology'];
    }

    /** @return array<string, mixed> */
    private function chamber(): array
    {
        return ['name_en' => 'City Clinic'];
    }

    /** @return array<string, mixed> */
    private function procedure(): array
    {
        return ['code' => 'RCT', 'name_en' => 'Root canal'];
    }

    /** @return array<string, mixed> */
    private function labTest(): array
    {
        return ['name' => 'Serum ferritin'];
    }

    /** @return array<string, mixed> */
    private function advice(): array
    {
        return ['title' => 'Rest', 'text_en' => 'Take rest.'];
    }

    /** @return array<string, mixed> */
    private function template(): array
    {
        return [
            'code' => 'my_pad', 'name' => 'My pad', 'paper_size' => 'A4', 'default_print_mode' => 'pad_only',
            'layout' => 'single_column', 'barcode_source' => 'prescription_no',
            'sections' => [['section_key' => 'medicines', 'zone' => 'right']],
        ];
    }
}
