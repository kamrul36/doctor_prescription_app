<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\LabTest;
use App\Domain\Patient\Models\Patient;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/** Patients and catalog items record who created and who last updated them (web and API). */
class AuthorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessSeeder::class);
    }

    public function test_patient_authors_via_api_and_web(): void
    {
        $doctor = User::factory()->doctor()->create(['name' => 'Dr. Karim']);
        $assistant = User::factory()->assistant()->create(['name' => 'Rina']);

        $id = $this->postJson('/api/v1/patients', [
            'name' => 'Abdul Kader', 'age_years' => 40, 'gender' => 'male', 'phone' => '01711000000',
        ], $this->bearer($doctor))
            ->assertCreated()
            ->assertJsonPath('data.created_by', ['id' => $doctor->id, 'name' => 'Dr. Karim'])
            ->assertJsonPath('data.updated_by', ['id' => $doctor->id, 'name' => 'Dr. Karim'])
            ->json('data.id');

        // The request cannot set the authors.
        $this->putJson("/api/v1/patients/{$id}", [
            'name' => 'Abdul Kader Mia', 'age_years' => 40, 'gender' => 'male', 'phone' => '01711000000',
            'created_by' => $assistant->id, 'updated_by' => $doctor->id,
        ], $this->bearer($assistant))
            ->assertOk()
            ->assertJsonPath('data.created_by.id', $doctor->id)
            ->assertJsonPath('data.updated_by', ['id' => $assistant->id, 'name' => 'Rina']);

        $this->assertDatabaseHas('patients', ['id' => $id, 'created_by' => $doctor->id, 'updated_by' => $assistant->id]);
        $this->getJson("/api/v1/patients/{$id}", $this->bearer($assistant))->assertJsonPath('data.created_by.name', 'Dr. Karim');
        $this->getJson('/api/v1/patients', $this->bearer($assistant))->assertJsonPath('data.0.updated_by.name', 'Rina');

        $this->actingAs($doctor)->get("/patients/{$id}")->assertOk()
            ->assertSee('Created by Dr. Karim on', false)
            ->assertSee('Last updated by Rina on', false);
    }

    public function test_catalog_authors_via_web_and_api(): void
    {
        $doctor = User::factory()->doctor()->create(['name' => 'Dr. Karim']);

        $this->actingAs($doctor)->post('/catalog/lab-tests', ['name' => 'Serum TSH', 'is_active' => '1'])->assertRedirect();
        $test = LabTest::query()->where('name', 'Serum TSH')->firstOrFail();
        $this->assertSame($doctor->id, $test->created_by);
        $this->assertSame($doctor->id, $test->updated_by);

        $other = User::factory()->doctor()->create(['name' => 'Dr. Nila']);
        $this->putJson("/api/v1/lab-tests/{$test->id}", ['name' => 'Serum TSH (3rd gen)', 'is_active' => true], $this->bearer($other))
            ->assertOk()
            ->assertJsonPath('data.created_by.name', 'Dr. Karim')
            ->assertJsonPath('data.updated_by.name', 'Dr. Nila');

        $this->actingAs($doctor)->get('/catalog/lab-tests')->assertSee('Dr. Nila');
        $this->get("/catalog/lab-tests/{$test->id}/edit")->assertSee('Created by Dr. Karim on', false);

        // Typeahead (cached) leaves the authors out.
        $this->getJson('/api/v1/lab-tests?search=Serum', $this->bearer($doctor))->assertJsonMissingPath('data.0.created_by');
    }

    public function test_rows_without_a_signed_in_user_keep_null_authors(): void
    {
        $patient = Patient::factory()->create();
        $this->assertNull($patient->created_by);
        $this->assertNull($patient->updated_by);

        $this->actingAs(User::factory()->doctor()->create())->get("/patients/{$patient->id}")->assertOk()->assertSee('Created by — on', false);
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
