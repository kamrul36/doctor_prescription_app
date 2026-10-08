<?php

namespace Tests\Feature\Catalog;

use App\Domain\Access\Role;
use App\Domain\Catalog\Models\AdviceTemplate;
use App\Domain\Catalog\Models\LabTest;
use App\Domain\Catalog\Models\Procedure;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessSeeder::class);
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        // Otherwise the guard and the JWT singletons keep the previous request's user and token,
        // which matters here because several tests switch users within one test.
        $this->app['auth']->forgetGuards();
        foreach (['tymon.jwt', 'tymon.jwt.auth', 'tymon.jwt.parser'] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
        JWTAuth::clearResolvedInstances();
        JWTAuth::unsetToken();

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    private function doctorWithProfile(): User
    {
        $user = User::factory()->doctor()->create();
        Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. '.$user->name, 'specialty_code' => 'general']);

        return $user;
    }

    /** @return list<string> */
    private function names(array $response): array
    {
        return array_column($response['data'], 'name');
    }

    // ---- lab tests -----------------------------------------------------

    public function test_doctor_manages_lab_tests_and_audit_has_no_text(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());

        $id = $this->postJson('/api/v1/lab-tests', ['name' => 'CBC', 'category' => 'Hematology'], $h)
            ->assertCreated()->assertJsonPath('data.name', 'CBC')->assertJsonPath('data.is_active', true)
            ->json('data.id');
        $this->putJson("/api/v1/lab-tests/{$id}", ['name' => 'CBC with ESR', 'default_timing_note' => 'Fasting'], $h)
            ->assertOk()->assertJsonPath('data.name', 'CBC with ESR')->assertJsonPath('data.default_timing_note', 'Fasting');
        $this->getJson("/api/v1/lab-tests/{$id}", $h)->assertOk();
        $this->deleteJson("/api/v1/lab-tests/{$id}", [], $h)->assertNoContent();

        $this->assertSoftDeleted('lab_tests', ['id' => $id]);
        $this->getJson("/api/v1/lab-tests/{$id}", $h)->assertNotFound();
        $this->putJson("/api/v1/lab-tests/{$id}", ['name' => 'X'], $h)->assertNotFound();
        foreach (['created', 'updated', 'deleted'] as $event) {
            $this->assertDatabaseHas('audit_logs', ['action' => "catalog.lab_tests.{$event}"]);
        }
        $this->assertStringNotContainsString('Fasting', (string) DB::table('audit_logs')->pluck('properties')->join(' '));
    }

    public function test_lab_test_name_must_be_unique_among_live_rows(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());
        $first = LabTest::create(['name' => 'ECG']);

        $this->postJson('/api/v1/lab-tests', ['name' => 'ECG'], $h)->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson("/api/v1/lab-tests/{$first->id}", ['name' => 'ECG', 'category' => 'Heart'], $h)->assertOk();

        $first->delete();
        $this->postJson('/api/v1/lab-tests', ['name' => 'ECG'], $h)->assertCreated();
    }

    public function test_assistant_can_search_but_not_change_catalogs(): void
    {
        $assistant = $this->bearer(User::factory()->assistant()->create());
        $test = LabTest::create(['name' => 'CBC']);
        $procedure = Procedure::create(['code' => 'SCALING', 'name_en' => 'Scaling']);
        $advice = AdviceTemplate::create(['specialty_code' => 'general', 'title' => 'General', 'text_en' => 'Rest']);

        foreach (['lab-tests' => $test, 'procedures' => $procedure, 'advice-templates' => $advice] as $path => $item) {
            $this->getJson("/api/v1/{$path}", $assistant)->assertOk()->assertJsonCount(1, 'data');
            $this->getJson("/api/v1/{$path}/{$item->id}", $assistant)->assertOk();
            $this->postJson("/api/v1/{$path}", ['name' => 'X'], $assistant)->assertForbidden();
            $this->putJson("/api/v1/{$path}/{$item->id}", ['name' => 'X'], $assistant)->assertForbidden();
            $this->deleteJson("/api/v1/{$path}/{$item->id}", [], $assistant)->assertForbidden();
        }
    }

    public function test_users_without_permission_are_forbidden(): void
    {
        $nobody = $this->bearer(User::factory()->create());

        foreach (['lab-tests', 'procedures', 'advice-templates'] as $path) {
            $this->getJson("/api/v1/{$path}", $nobody)->assertForbidden();
        }
    }

    public function test_guests_are_unauthorized(): void
    {
        foreach (['lab-tests', 'procedures', 'advice-templates'] as $path) {
            $this->getJson("/api/v1/{$path}")->assertUnauthorized();
        }
    }

    // ---- typeahead -----------------------------------------------------

    public function test_typeahead_puts_prefix_matches_before_contains_matches(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());
        foreach (['Serum calcium', 'Calcium (ionised)', 'Urine calcium', 'Hemoglobin', 'CBC'] as $name) {
            LabTest::create(['name' => $name]);
        }

        $this->assertSame(
            ['Calcium (ionised)', 'Serum calcium', 'Urine calcium'],
            $this->names($this->getJson('/api/v1/lab-tests?search=calc', $h)->assertOk()->json()),
        );
        // A short prefix list is topped up with contains matches only up to `take`.
        $this->assertSame(
            ['Calcium (ionised)', 'Serum calcium'],
            $this->names($this->getJson('/api/v1/lab-tests?search=calc&take=2', $h)->json()),
        );
        $this->assertSame([], $this->names($this->getJson('/api/v1/lab-tests?search=zzz', $h)->json()));
    }

    public function test_typeahead_matches_wildcards_literally(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());
        foreach (['Hb%', 'Hb A1c', 'a_b test', 'axb test', 'Hb!'] as $name) {
            LabTest::create(['name' => $name]);
        }

        $this->assertSame(['Hb%'], $this->names($this->getJson('/api/v1/lab-tests?search='.urlencode('%'), $h)->json()));
        $this->assertSame(['a_b test'], $this->names($this->getJson('/api/v1/lab-tests?search=a_b', $h)->json()));
        $this->assertSame(['Hb!'], $this->names($this->getJson('/api/v1/lab-tests?search='.urlencode('Hb!'), $h)->json()));
        $this->assertSame(['Hb!'], $this->names($this->getJson('/api/v1/lab-tests?search='.urlencode('!'), $h)->json()));
    }

    public function test_typeahead_finds_bangla_and_procedure_codes(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());
        Procedure::create(['code' => 'SCALING', 'name_en' => 'Scaling', 'name_bn' => 'স্কেলিং']);
        Procedure::create(['code' => 'RCT', 'name_en' => 'Root canal treatment']);

        $this->assertSame(['SCALING'], array_column($this->getJson('/api/v1/procedures?search='.urlencode('স্কে'), $h)->json('data'), 'code'));
        $this->assertSame(['RCT'], array_column($this->getJson('/api/v1/procedures?search=rct', $h)->json('data'), 'code'));
    }

    public function test_take_is_capped_and_inactive_rows_hidden_unless_a_manager_asks(): void
    {
        $doctorUser = User::factory()->doctor()->create();
        $assistantUser = User::factory()->assistant()->create();
        for ($i = 1; $i <= 60; $i++) {
            LabTest::create(['name' => sprintf('Test %02d', $i)]);
        }
        LabTest::create(['name' => 'Test hidden', 'is_active' => false]);
        // A fresh token per request: the JWT guard caches the user it saw last.
        $doctor = fn () => $this->bearer($doctorUser);
        $assistant = fn () => $this->bearer($assistantUser);

        $this->assertCount(50, $this->getJson('/api/v1/lab-tests?take=500', $doctor())->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/lab-tests?take=0', $doctor())->json('data'));
        $this->assertNotContains('Test hidden', $this->names($this->getJson('/api/v1/lab-tests?search=hidden', $doctor())->json()));
        $this->assertSame(['Test hidden'], $this->names($this->getJson('/api/v1/lab-tests?search=hidden&include_inactive=1', $doctor())->json()));
        // Not allowed for an assistant: the flag is ignored, not an error.
        $this->assertSame([], $this->names($this->getJson('/api/v1/lab-tests?search=hidden&include_inactive=1', $assistant())->json()));
    }

    public function test_paged_listing_when_page_is_given(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());
        for ($i = 1; $i <= 30; $i++) {
            LabTest::create(['name' => sprintf('Test %02d', $i)]);
        }

        $this->getJson('/api/v1/lab-tests?page=2&per_page=10', $h)->assertOk()
            ->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 30)->assertJsonPath('data.0.name', 'Test 11');
        $this->getJson('/api/v1/lab-tests?page=1&per_page=1000', $h)->assertJsonCount(30, 'data');
    }

    public function test_typeahead_uses_at_most_two_queries(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());
        foreach (range(1, 40) as $i) {
            LabTest::create(['name' => "Alpha {$i}"]);
            LabTest::create(['name' => "Beta alpha {$i}"]);
        }
        $this->getJson('/api/v1/lab-tests', $h); // warm up auth lookups

        DB::enableQueryLog();
        $this->getJson('/api/v1/lab-tests?search=alpha&take=50', $h)->assertOk();
        $searchQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'lab_tests'))->count();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, $searchQueries);
    }

    public function test_typeahead_cache_is_flushed_by_every_write(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());

        $this->assertSame([], $this->names($this->getJson('/api/v1/lab-tests?search=cbc', $h)->json()));

        $id = $this->postJson('/api/v1/lab-tests', ['name' => 'CBC'], $h)->json('data.id');
        $this->assertSame(['CBC'], $this->names($this->getJson('/api/v1/lab-tests?search=cbc', $h)->json()));

        $this->putJson("/api/v1/lab-tests/{$id}", ['name' => 'CBC', 'is_active' => false], $h)->assertOk();
        $this->assertSame([], $this->names($this->getJson('/api/v1/lab-tests?search=cbc', $h)->json()));

        $this->putJson("/api/v1/lab-tests/{$id}", ['name' => 'CBC', 'is_active' => true], $h)->assertOk();
        $this->assertSame(['CBC'], $this->names($this->getJson('/api/v1/lab-tests?search=cbc', $h)->json()));

        $this->deleteJson("/api/v1/lab-tests/{$id}", [], $h)->assertNoContent();
        $this->assertSame([], $this->names($this->getJson('/api/v1/lab-tests?search=cbc', $h)->json()));
    }

    // ---- procedures ----------------------------------------------------

    public function test_procedure_code_is_uppercased_and_unique_even_when_deleted(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());

        $id = $this->postJson('/api/v1/procedures', ['code' => ' scaling ', 'name_en' => 'Scaling'], $h)
            ->assertCreated()->assertJsonPath('data.code', 'SCALING')->assertJsonPath('data.is_billable', true)
            ->json('data.id');
        $this->postJson('/api/v1/procedures', ['code' => 'Scaling', 'name_en' => 'Again'], $h)
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->putJson("/api/v1/procedures/{$id}", ['code' => 'SCALING', 'name_en' => 'Scaling and polishing'], $h)->assertOk();

        $this->deleteJson("/api/v1/procedures/{$id}", [], $h)->assertNoContent();
        $this->postJson('/api/v1/procedures', ['code' => 'SCALING', 'name_en' => 'New'], $h)
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/procedures', ['code' => 'a b', 'name_en' => 'Bad'], $h)->assertUnprocessable();
    }

    public function test_procedure_fee_is_validated_and_stored_exactly(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());
        $post = fn (array $extra) => $this->postJson('/api/v1/procedures', ['code' => 'P'.random_int(10, 99999), 'name_en' => 'X', ...$extra], $h);

        $post(['default_fee' => '500'])->assertCreated()->assertJsonPath('data.default_fee', '500.00');
        $post(['default_fee' => '1250.5'])->assertCreated()->assertJsonPath('data.default_fee', '1250.50');
        $post(['default_fee' => '0'])->assertCreated()->assertJsonPath('data.default_fee', '0.00');
        $post(['default_fee' => null])->assertCreated()->assertJsonPath('data.default_fee', null);
        $post([])->assertCreated()->assertJsonPath('data.default_fee', null);
        $post(['default_fee' => '9999999.99'])->assertCreated();

        foreach (['12.345', '-5', 'abc', '10000000', '1e3', '5,000'] as $bad) {
            $post(['default_fee' => $bad])->assertUnprocessable()->assertJsonValidationErrors('default_fee');
        }
        $this->assertSame('1250.50', DB::table('procedures')->where('default_fee', 1250.5)->value('default_fee'));
    }

    // ---- advice templates ------------------------------------------------

    public function test_advice_owner_is_set_by_the_server_and_private_texts_stay_private(): void
    {
        $alice = $this->doctorWithProfile();
        $bob = $this->doctorWithProfile();
        $bobProfile = Doctor::where('user_id', $bob->id)->firstOrFail();
        $ha = fn () => $this->bearer($alice);
        $hb = fn () => $this->bearer($bob);

        $id = $this->postJson('/api/v1/advice-templates', [
            'specialty_code' => 'general', 'title' => 'Alice private', 'text_en' => 'Secret', 'doctor_id' => $bobProfile->id,
        ], $ha())->assertCreated()->json('data.id');

        $aliceProfile = Doctor::where('user_id', $alice->id)->firstOrFail();
        $this->assertSame($aliceProfile->id, AdviceTemplate::find($id)->doctor_id);

        $shared = AdviceTemplate::create(['specialty_code' => 'general', 'title' => 'Shared', 'text_en' => 'Rest']);
        $titles = fn (array $r) => array_column($r['data'], 'title');
        $this->assertEqualsCanonicalizing(['Alice private', 'Shared'], $titles($this->getJson('/api/v1/advice-templates', $ha())->json()));
        $this->assertSame(['Shared'], $titles($this->getJson('/api/v1/advice-templates', $hb())->json()));
        $this->assertSame(['Shared'], $titles($this->getJson('/api/v1/advice-templates?page=1', $hb())->json()));
        $this->getJson("/api/v1/advice-templates/{$id}", $hb())->assertForbidden();
        $this->putJson("/api/v1/advice-templates/{$id}", ['specialty_code' => 'general', 'title' => 'Hijack', 'text_en' => 'x'], $hb())->assertForbidden();
        $this->deleteJson("/api/v1/advice-templates/{$id}", [], $hb())->assertForbidden();

        // Shared ones can be maintained by any catalog manager.
        $this->putJson("/api/v1/advice-templates/{$shared->id}", ['specialty_code' => 'general', 'title' => 'Shared 2', 'text_en' => 'Rest'], $hb())->assertOk();
        // And a super admin can reach everything.
        $admin = User::factory()->create()->assignRole(Role::SUPER_ADMIN);
        $this->getJson("/api/v1/advice-templates/{$id}", $this->bearer($admin))->assertOk();
    }

    public function test_advice_cache_is_not_shared_between_doctors(): void
    {
        $alice = $this->doctorWithProfile();
        $bob = $this->doctorWithProfile();
        $ha = fn () => $this->bearer($alice);
        $this->postJson('/api/v1/advice-templates', ['specialty_code' => 'dental', 'title' => 'Mine', 'text_en' => 'x'], $ha())->assertCreated();

        $this->assertCount(1, $this->getJson('/api/v1/advice-templates?search=mine', $ha())->json('data'));
        $this->assertCount(0, $this->getJson('/api/v1/advice-templates?search=mine', $this->bearer($bob))->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/advice-templates?search=mine', $ha())->json('data'));
    }

    public function test_advice_specialty_filter_and_validation(): void
    {
        $h = $this->bearer($this->doctorWithProfile());
        $this->postJson('/api/v1/advice-templates', ['specialty_code' => 'dental', 'title' => 'D', 'text_en' => 'x'], $h)->assertCreated();
        $this->postJson('/api/v1/advice-templates', ['specialty_code' => 'gynae', 'title' => 'G', 'text_en' => 'x'], $h)->assertCreated();

        $this->assertSame(['D'], array_column($this->getJson('/api/v1/advice-templates?specialty=dental', $h)->json('data'), 'title'));
        $this->postJson('/api/v1/advice-templates', ['specialty_code' => 'astro', 'title' => 'x', 'text_en' => 'x'], $h)
            ->assertUnprocessable()->assertJsonValidationErrors('specialty_code');
        $this->postJson('/api/v1/advice-templates', ['specialty_code' => 'dental', 'title' => 'x'], $h)
            ->assertUnprocessable()->assertJsonValidationErrors('text_en');
    }

    public function test_default_advice_rules(): void
    {
        $user = $this->doctorWithProfile();
        $doctor = Doctor::where('user_id', $user->id)->firstOrFail();
        $h = $this->bearer($user);
        $pad = PrescriptionTemplate::create($this->template(['code' => 'pad', 'specialty_code' => 'dental']));
        $other = PrescriptionTemplate::create($this->template(['code' => 'other', 'specialty_code' => 'dental', 'doctor_id' => Doctor::where('user_id', $this->doctorWithProfile()->id)->value('id')]));
        $inactive = PrescriptionTemplate::create($this->template(['code' => 'off', 'specialty_code' => 'dental', 'is_active' => false]));
        $payload = fn (array $extra = []) => ['specialty_code' => 'dental', 'title' => 'T'.random_int(1, 99999), 'text_en' => 'x', ...$extra];

        $first = $this->postJson('/api/v1/advice-templates', $payload(['is_default_for_template_id' => $pad->id]), $h)
            ->assertCreated()->json('data.id');
        $second = $this->postJson('/api/v1/advice-templates', $payload(['is_default_for_template_id' => $pad->id]), $h)
            ->assertCreated()->json('data.id');

        $this->assertNull(AdviceTemplate::find($first)->is_default_for_template_id, 'a template has one default per owner');
        $this->assertSame($pad->id, AdviceTemplate::find($second)->is_default_for_template_id);

        $this->postJson('/api/v1/advice-templates', $payload(['specialty_code' => 'gynae', 'is_default_for_template_id' => $pad->id]), $h)
            ->assertUnprocessable()->assertJsonValidationErrors('is_default_for_template_id');
        $this->postJson('/api/v1/advice-templates', $payload(['is_default_for_template_id' => $other->id]), $h)->assertUnprocessable();
        $this->postJson('/api/v1/advice-templates', $payload(['is_default_for_template_id' => $inactive->id]), $h)->assertUnprocessable();
        $this->postJson('/api/v1/advice-templates', $payload(['is_default_for_template_id' => 99999]), $h)->assertUnprocessable();
        $this->postJson('/api/v1/advice-templates', $payload(['is_default_for_template_id' => $pad->id, 'is_active' => false]), $h)->assertUnprocessable();

        // Deactivating the default clears it.
        $this->putJson("/api/v1/advice-templates/{$second}", $payload(['is_active' => false]), $h)->assertOk();
        $this->assertNull(AdviceTemplate::find($second)->is_default_for_template_id);
    }

    public function test_validation_errors_use_problem_details(): void
    {
        $h = $this->bearer(User::factory()->doctor()->create());

        $this->postJson('/api/v1/lab-tests', [], $h)->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 422)->assertJsonStructure(['errors' => ['name']]);
    }

    /** @return array<string, mixed> */
    private function template(array $overrides): array
    {
        return [
            'name' => 'T', 'paper_size' => 'A4', 'default_print_mode' => 'pad_only', 'layout' => 'single_column',
            'barcode_source' => 'prescription_no', 'is_active' => true, ...$overrides,
        ];
    }
}
