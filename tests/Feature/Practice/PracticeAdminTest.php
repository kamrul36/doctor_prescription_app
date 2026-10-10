<?php

namespace Tests\Feature\Practice;

use App\Domain\Finance\Money;
use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\Specialty;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\PracticeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/** Admin-managed setup: the specialty list, chambers, and which chambers each doctor works at. */
class PracticeAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessSeeder::class, PracticeSeeder::class]);
    }

    // ---- specialties ------------------------------------------------------

    public function test_admin_manages_the_specialty_list_on_web(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get('/admin/specialties')->assertOk()->assertSee('Gynaecology &amp; Obstetrics', false)->assertSee('Dental');
        $this->get('/admin/specialties/create')->assertOk();
        $this->post('/admin/specialties', ['name' => 'Cardiology', 'block' => '', 'is_active' => '1'])->assertRedirect('/admin/specialties');

        $cardiology = Specialty::where('name', 'Cardiology')->firstOrFail();
        $this->assertSame('cardiology', $cardiology->code);
        $this->assertNull($cardiology->block);

        // Renaming keeps the code; deactivating hides it from profiles but keeps the row.
        $this->put("/admin/specialties/{$cardiology->id}", ['name' => 'Cardiology & Medicine', 'is_active' => '0'])->assertRedirect('/admin/specialties');
        $this->assertSame('cardiology', $cardiology->fresh()->code);
        $this->assertFalse($cardiology->fresh()->is_active);

        $this->from('/admin/specialties/create')->post('/admin/specialties', ['name' => 'dental'])
            ->assertRedirect('/admin/specialties/create')->assertSessionHasErrors('name');
        $this->assertDatabaseHas('audit_logs', ['action' => 'specialty.updated']);
    }

    public function test_specialty_api_lists_active_for_staff_and_writes_need_practice_manage(): void
    {
        $admin = $this->bearer(User::factory()->superAdmin()->create());
        $id = $this->postJson('/api/v1/specialties', ['name' => 'Paediatrics', 'block' => null], $admin)
            ->assertCreated()->assertJsonPath('data.code', 'paediatrics')->json('data.id');
        $this->putJson("/api/v1/specialties/{$id}", ['name' => 'Paediatrics', 'is_active' => false], $admin)->assertOk();
        $this->getJson('/api/v1/specialties?include_inactive=1', $admin)->assertJsonCount(3, 'data');

        $doctor = $this->bearer(User::factory()->doctor()->create());
        $this->getJson('/api/v1/specialties', $doctor)->assertOk()->assertJsonCount(2, 'data');
        // A doctor can't see inactive entries or write to the list.
        $this->getJson('/api/v1/specialties?include_inactive=1', $doctor)->assertJsonCount(2, 'data');
        $this->postJson('/api/v1/specialties', ['name' => 'X'], $doctor)->assertForbidden();
        $this->putJson("/api/v1/specialties/{$id}", ['name' => 'X'], $doctor)->assertForbidden();

        $admin = $this->bearer(User::factory()->superAdmin()->create());
        $this->postJson('/api/v1/specialties', ['name' => 'Y', 'block' => 'astro'], $admin)->assertUnprocessable()->assertJsonValidationErrors('block');
        $this->postJson('/api/v1/specialties', ['name' => 'DENTAL'], $admin)->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    // ---- chambers ---------------------------------------------------------

    public function test_admin_creates_several_chambers_with_branches_on_web(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get('/admin/chambers/create')->assertOk();
        $this->post('/admin/chambers', [
            'name_en' => 'City Dental', 'is_active' => '1',
            'branches' => [['name_en' => 'Dhanmondi', 'phones' => '01711, 01811'], ['name_en' => '', 'name_bn' => '', 'phones' => '']],
        ])->assertRedirect('/admin/chambers');
        $this->post('/admin/chambers', ['name_en' => 'Uttara Clinic', 'is_active' => '1', 'branches' => ''])->assertRedirect('/admin/chambers');

        $this->assertSame(2, Chamber::count());
        $city = Chamber::where('name_en', 'City Dental')->with('branches')->firstOrFail();
        $this->assertSame(['01711', '01811'], $city->branches[0]->phones);
        $this->get('/admin/chambers')->assertOk()->assertSee('City Dental')->assertSee('Uttara Clinic');
        $this->get("/admin/chambers/{$city->id}/edit")->assertOk()->assertSee('Dhanmondi');

        // Removing every branch clears them; deactivating keeps the chamber.
        $this->put("/admin/chambers/{$city->id}", ['name_en' => 'City Dental', 'is_active' => '0', 'branches' => ''])->assertRedirect('/admin/chambers');
        $this->assertCount(0, $city->fresh()->branches);
        $this->assertFalse($city->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'chamber.created']);
    }

    public function test_chamber_api_for_admin_and_read_only_for_doctor(): void
    {
        $admin = $this->bearer(User::factory()->superAdmin()->create());
        $id = $this->postJson('/api/v1/chambers', [
            'name_en' => 'City Dental',
            'branches' => [['name_en' => 'Dhanmondi', 'phones' => ['01711000000']], ['name_en' => 'Uttara']],
        ], $admin)->assertCreated()->assertJsonPath('data.branches.0.phones', ['01711000000'])->json('data.id');
        $this->putJson("/api/v1/chambers/{$id}", ['name_en' => 'City Dental', 'branches' => [['name_en' => 'Mirpur']]], $admin)
            ->assertOk()->assertJsonCount(1, 'data.branches');
        $this->postJson('/api/v1/chambers', ['name_en' => 'Closed', 'is_active' => false], $admin)->assertCreated();

        $doctor = $this->bearer(User::factory()->doctor()->create());
        $this->getJson('/api/v1/chambers', $doctor)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/chambers/{$id}", $doctor)->assertOk()->assertJsonPath('data.name_en', 'City Dental');
        $this->postJson('/api/v1/chambers', ['name_en' => 'Mine'], $doctor)->assertForbidden();
        $this->putJson("/api/v1/chambers/{$id}", ['name_en' => 'Hacked'], $doctor)->assertForbidden();
        $this->getJson('/api/v1/chamber', $doctor)->assertNotFound();
    }

    public function test_doctor_cannot_reach_admin_screens(): void
    {
        $this->actingAs(User::factory()->doctor()->create());

        foreach (['/admin/chambers', '/admin/chambers/create', '/admin/specialties', '/admin/doctors'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/chambers', ['name_en' => 'X'])->assertForbidden();
        $this->assertSame(0, Chamber::count());
    }

    // ---- chamber assignment -----------------------------------------------

    public function test_admin_assigns_and_unassigns_chambers_for_a_doctor_without_a_profile(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $a = Chamber::create(['name_en' => 'Chamber A']);
        $b = Chamber::create(['name_en' => 'Chamber B']);
        $closed = Chamber::create(['name_en' => 'Closed', 'is_active' => false]);
        $doctorUser = User::factory()->doctor()->create(['name' => 'New Doc']);

        $this->get('/admin/doctors')->assertOk()->assertSee('New Doc')->assertSee('None assigned');
        $this->get("/admin/doctors/{$doctorUser->id}/chambers")->assertOk()->assertSee('Chamber A')->assertDontSee('Closed');

        $this->put("/admin/doctors/{$doctorUser->id}/chambers", ['chamber_ids' => [$a->id => '1', $b->id => '1']])->assertRedirect('/admin/doctors');
        $doctor = Doctor::where('user_id', $doctorUser->id)->firstOrFail();
        $this->assertSame('New Doc', $doctor->name_en, 'a minimal profile is created');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $doctor->chambers()->pluck('chambers.id')->all());

        // The doctor sets fees; unassigning B removes B and its fees only.
        $doctor->fees()->create(['chamber_id' => $a->id, 'visit_type' => 'new', 'amount' => Money::fromDecimal('500')]);
        $doctor->fees()->create(['chamber_id' => $b->id, 'visit_type' => 'new', 'amount' => Money::fromDecimal('700')]);
        $this->put("/admin/doctors/{$doctorUser->id}/chambers", ['chamber_ids' => [$a->id => '1', $b->id => '0']])->assertRedirect('/admin/doctors');
        $this->assertSame([$a->id], $doctor->chambers()->pluck('chambers.id')->all());
        $this->assertSame([$a->id], $doctor->fees()->pluck('chamber_id')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.chambers_assigned']);

        // A closed chamber can't be newly assigned.
        $this->from("/admin/doctors/{$doctorUser->id}/chambers")
            ->put("/admin/doctors/{$doctorUser->id}/chambers", ['chamber_ids' => [$a->id => '1', $closed->id => '1']])
            ->assertSessionHasErrors('chamber_ids.1');

        // Only users with the doctor role have chambers.
        $assistant = User::factory()->assistant()->create();
        $this->get("/admin/doctors/{$assistant->id}/chambers")->assertNotFound();
    }

    public function test_doctor_chamber_assignment_api(): void
    {
        $admin = $this->bearer(User::factory()->superAdmin()->create());
        $chamber = Chamber::create(['name_en' => 'Chamber A']);
        $doctorUser = User::factory()->doctor()->create();

        // 201: the doctor had no profile yet, so a minimal one was created with the assignment.
        $this->putJson("/api/v1/doctors/{$doctorUser->id}/chambers", ['chamber_ids' => [$chamber->id]], $admin)
            ->assertCreated()->assertJsonPath('data.chambers.0.name_en', 'Chamber A');
        $this->getJson('/api/v1/doctors', $admin)->assertOk()->assertJsonPath('data.0.chambers.0.id', $chamber->id);
        $this->putJson("/api/v1/doctors/{$doctorUser->id}/chambers", ['chamber_ids' => [999]], $admin)->assertJsonValidationErrors('chamber_ids.0');
        $this->putJson("/api/v1/doctors/{$doctorUser->id}/chambers", ['chamber_ids' => []], $admin)->assertOk()->assertJsonPath('data.chambers', []);

        $doctor = $this->bearer($doctorUser);
        $this->getJson('/api/v1/doctors', $doctor)->assertForbidden();
        $this->putJson("/api/v1/doctors/{$doctorUser->id}/chambers", ['chamber_ids' => [$chamber->id]], $doctor)->assertForbidden();
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
