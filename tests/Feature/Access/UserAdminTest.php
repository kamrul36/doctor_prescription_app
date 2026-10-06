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

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = AccessSeeder::class;

    public function test_super_admin_creates_a_user_via_api(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->postJson('/api/v1/users', [
            'name' => 'Rina Akter',
            'email' => 'rina@example.com',
            'phone' => '01700000000',
            'password' => 'secret-pass',
            'roles' => [Role::ASSISTANT],
        ], $this->bearer($admin))
            ->assertCreated()
            ->assertJsonPath('data.email', 'rina@example.com')
            ->assertJsonPath('data.roles', [Role::ASSISTANT])
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'rina@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::ASSISTANT));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'auditable_id' => $user->id, 'user_id' => $admin->id]);
    }

    public function test_super_admin_creates_a_user_via_web(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Karim',
            'email' => 'karim@example.com',
            'password' => 'secret-pass',
            'roles' => [Role::DOCTOR],
            'is_active' => '1',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertTrue(User::where('email', 'karim@example.com')->firstOrFail()->hasRole(Role::DOCTOR));
    }

    public function test_user_admin_pages_render(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $other = User::factory()->doctor()->create();

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee($other->email);
        $this->actingAs($admin)->get('/admin/users/create')->assertOk();
        $this->actingAs($admin)->get("/admin/users/{$other->id}/edit")->assertOk()->assertSee($other->name);
    }

    public function test_doctor_and_assistant_cannot_manage_users(): void
    {
        foreach ([User::factory()->doctor()->create(), User::factory()->assistant()->create()] as $user) {
            $this->getJson('/api/v1/users', $this->bearer($user))->assertForbidden();
            $this->actingAs($user)->get('/admin/users')->assertForbidden();
            $this->actingAs($user)->post('/admin/users', [])->assertForbidden();
        }
    }

    public function test_web_edit_updates_roles_and_keeps_password_when_blank(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->assistant()->create();
        $hash = $user->password;

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => 'Renamed',
            'email' => $user->email,
            'password' => '',
            'roles' => [Role::DOCTOR],
            'is_active' => '1',
        ])->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertSame($hash, $user->password);
        $this->assertSame([Role::DOCTOR], $user->getRoleNames()->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'auditable_id' => $user->id]);
    }

    public function test_api_update_without_roles_keeps_roles(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->assistant()->create();

        $this->putJson("/api/v1/users/{$user->id}", ['phone' => '01811111111'], $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('data.roles', [Role::ASSISTANT]);
    }

    public function test_user_manager_cannot_grant_super_admin(): void
    {
        $manager = $this->userManager();
        $user = User::factory()->assistant()->create();

        $this->putJson("/api/v1/users/{$user->id}", ['roles' => [Role::SUPER_ADMIN]], $this->bearer($manager))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roles');

        $this->postJson('/api/v1/users', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-pass', 'roles' => [Role::SUPER_ADMIN],
        ], $this->bearer($manager))->assertUnprocessable();

        $this->assertFalse($user->fresh()->hasRole(Role::SUPER_ADMIN));
    }

    public function test_user_manager_cannot_change_a_super_admin_account(): void
    {
        $manager = $this->userManager();
        User::factory()->superAdmin()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->putJson("/api/v1/users/{$admin->id}", ['password' => 'taken-over'], $this->bearer($manager))
            ->assertUnprocessable();
    }

    public function test_user_manager_cannot_grant_a_role_with_permissions_they_lack(): void
    {
        $manager = $this->userManager();
        $user = User::factory()->create();

        $this->putJson("/api/v1/users/{$manager->id}", ['roles' => ['office_manager', Role::DOCTOR]], $this->bearer($manager))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roles');

        $this->putJson("/api/v1/users/{$user->id}", ['roles' => [Role::DOCTOR]], $this->bearer($manager))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roles');

        $this->postJson('/api/v1/users', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-pass', 'roles' => [Role::DOCTOR],
        ], $this->bearer($manager))->assertUnprocessable();

        $this->assertFalse($manager->fresh()->hasRole(Role::DOCTOR));
        $this->assertFalse($user->fresh()->hasRole(Role::DOCTOR));

        // A role within the manager's own permissions is fine.
        $this->putJson("/api/v1/users/{$user->id}", ['roles' => ['office_manager']], $this->bearer($manager))->assertOk();
    }

    public function test_user_manager_cannot_change_a_user_with_more_permissions(): void
    {
        $manager = $this->userManager();
        $doctor = User::factory()->doctor()->create();

        $this->putJson("/api/v1/users/{$doctor->id}", ['password' => 'taken-over'], $this->bearer($manager))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');
    }

    public function test_user_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->superAdmin()->create();

        $this->putJson("/api/v1/users/{$admin->id}", ['is_active' => false], $this->bearer($admin))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_active');
    }

    public function test_last_active_super_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->putJson("/api/v1/users/{$admin->id}", ['roles' => [Role::DOCTOR]], $this->bearer($admin))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roles');

        $second = User::factory()->superAdmin()->create();

        $this->putJson("/api/v1/users/{$second->id}", ['is_active' => false], $this->bearer($admin))->assertOk();
    }

    public function test_validation_errors_use_problem_details(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->postJson('/api/v1/users', ['email' => 'not-an-email', 'roles' => ['ghost']], $this->bearer($admin))
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonValidationErrors(['name', 'email', 'password', 'roles.0']);
    }

    /** A non-super-admin who has been given users.manage. */
    private function userManager(): User
    {
        RoleModel::create(['name' => 'office_manager', 'guard_name' => Role::GUARD])
            ->givePermissionTo(Permission::UsersManage->value);

        return User::factory()->withRole('office_manager')->create();
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
