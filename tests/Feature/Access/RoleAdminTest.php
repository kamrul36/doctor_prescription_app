<?php

namespace Tests\Feature\Access;

use App\Domain\Access\Permission;
use App\Domain\Access\Role;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class RoleAdminTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = AccessSeeder::class;

    public function test_super_admin_creates_a_role_and_it_takes_effect(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->postJson('/api/v1/roles', [
            'name' => 'receptionist',
            'permissions' => [Permission::PatientsRead->value, Permission::PaymentsRecord->value],
        ], $this->bearer($admin))
            ->assertCreated()
            ->assertJsonPath('data.permissions', ['patients.read', 'payments.record']);

        $user = User::factory()->withRole('receptionist')->create();
        $this->assertTrue($user->can('payments.record'));
        $this->assertFalse($user->can('cases.finalize'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.created']);
    }

    public function test_changing_a_role_changes_its_users_immediately(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $assistant = User::factory()->assistant()->create();
        $role = RoleModel::findByName(Role::ASSISTANT, Role::GUARD);

        $this->assertFalse($assistant->can('cases.finalize'));

        $this->actingAs($admin)->put("/admin/roles/{$role->id}", [
            'name' => Role::ASSISTANT,
            'permissions' => [Permission::PatientsRead->value, Permission::CasesFinalize->value],
        ])->assertRedirect(route('admin.roles.index'));

        $this->assertTrue($assistant->fresh()->can('cases.finalize'));
        $this->assertFalse($assistant->fresh()->can('payments.record'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.updated', 'auditable_id' => $role->id]);
    }

    public function test_role_pages_render(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = RoleModel::findByName(Role::DOCTOR, Role::GUARD);

        $this->actingAs($admin)->get('/admin/roles')->assertOk()->assertSee(Role::DOCTOR);
        $this->actingAs($admin)->get('/admin/roles/create')->assertOk()->assertSee('cases.finalize');
        $this->actingAs($admin)->get("/admin/roles/{$role->id}/edit")->assertOk();
    }

    public function test_super_admin_role_cannot_be_changed_or_deleted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = RoleModel::findByName(Role::SUPER_ADMIN, Role::GUARD);

        $this->putJson("/api/v1/roles/{$role->id}", ['name' => 'boss', 'permissions' => []], $this->bearer($admin))
            ->assertUnprocessable();
        $this->deleteJson("/api/v1/roles/{$role->id}", [], $this->bearer($admin))->assertUnprocessable();

        $this->assertDatabaseHas('roles', ['name' => Role::SUPER_ADMIN]);
    }

    public function test_role_with_users_cannot_be_deleted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->assistant()->create();
        $role = RoleModel::findByName(Role::ASSISTANT, Role::GUARD);

        $this->deleteJson("/api/v1/roles/{$role->id}", [], $this->bearer($admin))->assertUnprocessable();
    }

    public function test_unused_role_can_be_deleted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = RoleModel::create(['name' => 'temp', 'guard_name' => Role::GUARD]);

        $this->actingAs($admin)->delete("/admin/roles/{$role->id}")->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', ['name' => 'temp']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.deleted']);
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->postJson('/api/v1/roles', ['name' => 'x', 'permissions' => ['cases.delete_everything']], $this->bearer($admin))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permissions.0');
    }

    public function test_permissions_endpoint_lists_the_enum(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->getJson('/api/v1/permissions', $this->bearer($admin))
            ->assertOk()
            ->assertJsonCount(count(Permission::cases()), 'data')
            ->assertJsonFragment(['name' => 'cases.finalize', 'group' => 'Clinical']);
    }

    public function test_doctor_cannot_manage_roles(): void
    {
        $doctor = User::factory()->doctor()->create();

        $this->getJson('/api/v1/roles', $this->bearer($doctor))->assertForbidden();
        $this->postJson('/api/v1/roles', ['name' => 'x', 'permissions' => []], $this->bearer($doctor))->assertForbidden();
        $this->actingAs($doctor)->get('/admin/roles')->assertForbidden();
    }

    public function test_role_manager_can_only_grant_permissions_they_hold(): void
    {
        RoleModel::create(['name' => 'role_manager', 'guard_name' => Role::GUARD])
            ->givePermissionTo([Permission::RolesManage->value, Permission::PatientsRead->value]);
        $manager = User::factory()->withRole('role_manager')->create();
        $own = RoleModel::findByName('role_manager', Role::GUARD);

        // Adding users.manage to their own role would be an escalation.
        $this->putJson("/api/v1/roles/{$own->id}", [
            'name' => 'role_manager',
            'permissions' => [Permission::RolesManage->value, Permission::PatientsRead->value, Permission::UsersManage->value],
        ], $this->bearer($manager))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permissions');

        $this->assertFalse($manager->fresh()->can(Permission::UsersManage->value));

        $this->postJson('/api/v1/roles', [
            'name' => 'viewer',
            'permissions' => [Permission::PatientsRead->value],
        ], $this->bearer($manager))->assertCreated();
    }

    public function test_reseeding_keeps_permission_changes_made_in_the_admin(): void
    {
        $role = RoleModel::findByName(Role::ASSISTANT, Role::GUARD);
        $role->syncPermissions([Permission::PatientsRead->value]);

        $this->seed(AccessSeeder::class);

        $this->assertSame(['patients.read'], $role->fresh()->permissions->pluck('name')->all());
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
