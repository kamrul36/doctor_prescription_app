<?php

namespace Tests\Feature\Access;

use App\Domain\Access\Permission;
use App\Domain\Access\Role;
use App\Domain\Catalog\Models\LabTest;
use App\Domain\Patient\Actions\DeletePatientAction;
use App\Domain\Patient\Models\Patient;
use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\PracticeSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Deleting patients and catalog items is its own permission, granted by the admin.
 * A patient with any visit can be deleted only by a super admin, always softly.
 */
class DeletePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessSeeder::class, PracticeSeeder::class]);
    }

    public function test_assistant_cannot_delete_until_the_admin_grants_it(): void
    {
        $assistant = User::factory()->assistant()->create();
        $patient = Patient::factory()->create();
        $test = LabTest::create(['name' => 'CBC']);

        $this->deleteJson("/api/v1/patients/{$patient->id}", [], $this->bearer($assistant))->assertForbidden();
        $this->actingAs($assistant)->delete("/patients/{$patient->id}")->assertForbidden();
        $this->actingAs($assistant)->get("/patients/{$patient->id}")->assertOk()->assertDontSee('Delete this patient?');
        $this->assertNotSoftDeleted($patient);

        $this->grant(Role::ASSISTANT, Permission::PatientsDelete);

        $this->actingAs($assistant)->get("/patients/{$patient->id}")->assertSee('Delete this patient?');
        $this->deleteJson("/api/v1/patients/{$patient->id}", [], $this->bearer($assistant))->assertNoContent();
        $this->assertSoftDeleted($patient);

        // Catalog delete needs catalog.manage (to reach the item) and catalog.delete.
        $this->grant(Role::ASSISTANT, Permission::CatalogManage);
        $this->deleteJson("/api/v1/lab-tests/{$test->id}", [], $this->bearer($assistant))->assertForbidden();
        $this->grant(Role::ASSISTANT, Permission::CatalogDelete);
        $this->deleteJson("/api/v1/lab-tests/{$test->id}", [], $this->bearer($assistant))->assertNoContent();
        $this->assertSoftDeleted($test);
    }

    public function test_doctor_loses_delete_when_the_admin_removes_it(): void
    {
        $doctor = User::factory()->doctor()->create();
        $test = LabTest::create(['name' => 'ECG']);

        $this->actingAs($doctor)->get('/catalog/lab-tests')->assertSee('Delete this entry?');

        RoleModel::findByName(Role::DOCTOR, Role::GUARD)->revokePermissionTo(Permission::CatalogDelete->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($doctor->fresh())->get('/catalog/lab-tests')->assertDontSee('Delete this entry?');
        $this->delete("/catalog/lab-tests/{$test->id}")->assertForbidden();
        $this->assertNotSoftDeleted($test);
    }

    public function test_doctor_deletes_a_patient_without_visits(): void
    {
        $doctor = User::factory()->doctor()->create();
        $patient = Patient::factory()->create();

        $this->actingAs($doctor)->delete("/patients/{$patient->id}")->assertRedirect('/patients');

        $this->assertSoftDeleted($patient);
    }

    public function test_a_patient_with_visits_can_be_deleted_only_by_a_super_admin(): void
    {
        [$doctor, $chamber] = $this->doctorWithChamber();
        $patient = Patient::factory()->create();
        $h = $this->bearer($doctor);

        $id = $this->postJson('/api/v1/case-histories', ['chamber_id' => $chamber->id, 'patient_id' => $patient->id], $h)
            ->assertCreated()->json('data.id');

        // A draft blocks it; so does a cancelled visit.
        $this->deleteJson("/api/v1/patients/{$patient->id}", [], $h)->assertForbidden();
        $this->postJson("/api/v1/case-histories/{$id}/cancel", ['reason' => 'Wrong patient'], $h)->assertOk();
        $this->deleteJson("/api/v1/patients/{$patient->id}", [], $this->bearer($doctor))->assertForbidden();

        $this->actingAs($doctor)->get("/patients/{$patient->id}")
            ->assertDontSee('Delete this patient?')
            ->assertSee('Only an administrator can delete a patient with prescriptions.');

        $assistant = User::factory()->assistant()->create();
        $this->grant(Role::ASSISTANT, Permission::PatientsDelete);
        $this->actingAs($assistant)->delete("/patients/{$patient->id}")->assertForbidden();
        $this->assertNotSoftDeleted($patient);

        $admin = User::factory()->superAdmin()->create();
        $this->deleteJson("/api/v1/patients/{$patient->id}", [], $this->bearer($admin))->assertNoContent();

        $this->assertSoftDeleted($patient);
        $this->assertDatabaseHas('case_histories', ['id' => $id, 'patient_id' => $patient->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient.deleted', 'user_id' => $admin->id]);
    }

    public function test_the_action_refuses_even_when_called_directly(): void
    {
        [$doctor, $chamber] = $this->doctorWithChamber();
        $patient = Patient::factory()->create();
        $this->postJson('/api/v1/case-histories', ['chamber_id' => $chamber->id, 'patient_id' => $patient->id], $this->bearer($doctor))
            ->assertCreated();

        $this->expectException(AuthorizationException::class);
        app(DeletePatientAction::class)->handle($doctor, $patient);
    }

    public function test_seeded_defaults_grant_delete_to_the_doctor_role_only(): void
    {
        $doctorRole = RoleModel::findByName(Role::DOCTOR, Role::GUARD);
        $assistantRole = RoleModel::findByName(Role::ASSISTANT, Role::GUARD);

        $this->assertTrue($doctorRole->hasPermissionTo(Permission::PatientsDelete->value));
        $this->assertTrue($doctorRole->hasPermissionTo(Permission::CatalogDelete->value));
        $this->assertFalse($assistantRole->hasPermissionTo(Permission::PatientsDelete->value));
        $this->assertFalse($assistantRole->hasPermissionTo(Permission::CatalogDelete->value));
    }

    private function grant(string $role, Permission $permission): void
    {
        RoleModel::findByName($role, Role::GUARD)->givePermissionTo($permission->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return array{0: User, 1: Chamber} */
    private function doctorWithChamber(): array
    {
        $user = User::factory()->doctor()->create();
        $doctor = Doctor::create(['user_id' => $user->id, 'name_en' => 'Dr. '.$user->id]);
        $chamber = Chamber::create(['name_en' => 'Chamber '.$user->id]);
        $doctor->chambers()->attach($chamber->id);

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
