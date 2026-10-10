<?php

namespace Tests\Feature\Access;

use App\Domain\Access\Permission;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * P1.1 exit check: the assistant is blocked from finalize.
 *
 * The real finalize route arrives in P1.5; until then a probe route carries
 * the same permission middleware on both the session and the JWT guard.
 */
class PermissionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = AccessSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'active', 'permission:'.Permission::CasesFinalize->value])
            ->post('/_probe/finalize', fn () => response('finalized'));

        Route::middleware(['api', 'auth:api', 'active', 'permission:'.Permission::CasesFinalize->value])
            ->post('/api/v1/_probe/finalize', fn () => response()->json(['ok' => true]));
    }

    public function test_assistant_is_blocked_from_finalize_on_web(): void
    {
        $this->actingAs(User::factory()->assistant()->create(), 'web')
            ->post('/_probe/finalize')
            ->assertForbidden();
    }

    public function test_assistant_is_blocked_from_finalize_on_api(): void
    {
        $this->postJson('/api/v1/_probe/finalize', [], $this->bearer(User::factory()->assistant()->create()))
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_doctor_can_finalize_on_web_and_api(): void
    {
        $doctor = User::factory()->doctor()->create();

        $this->postJson('/api/v1/_probe/finalize', [], $this->bearer($doctor))->assertOk();
        $this->actingAs($doctor, 'web')->post('/_probe/finalize')->assertOk();
    }

    public function test_super_admin_passes_every_check(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->postJson('/api/v1/_probe/finalize', [], $this->bearer($admin))->assertOk();

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($admin->can($permission->value), $permission->value);
        }
    }

    public function test_user_without_role_has_no_permissions(): void
    {
        $user = User::factory()->create();

        foreach (Permission::cases() as $permission) {
            $this->assertFalse($user->can($permission->value), $permission->value);
        }
    }

    /** Spec §8.5 matrix (plus admin permissions, which only super admin holds). */
    #[DataProvider('matrix')]
    public function test_default_role_permissions(Permission $permission, bool $doctor, bool $assistant): void
    {
        $this->assertSame($doctor, User::factory()->doctor()->create()->can($permission->value), 'doctor');
        $this->assertSame($assistant, User::factory()->assistant()->create()->can($permission->value), 'assistant');
    }

    /** @return array<string, array{Permission, bool, bool}> */
    public static function matrix(): array
    {
        $rows = [
            [Permission::PatientsRead, true, true],
            [Permission::PatientsWrite, true, true],
            [Permission::CasesRead, true, true],
            [Permission::CasesReadPrivate, true, false],
            [Permission::CasesWrite, true, false],
            [Permission::CasesFinalize, true, false],
            [Permission::CasesAmend, true, false],
            [Permission::PrescriptionsPrint, true, true],
            [Permission::PaymentsRecord, true, true],
            [Permission::FinanceRead, true, true],
            [Permission::FinanceDiscount, true, false],
            [Permission::FinanceVoid, true, false],
            [Permission::ExpensesWrite, true, true],
            [Permission::ReportsRead, true, false],
            [Permission::ReportsBasic, true, true],
            [Permission::TemplatesManage, true, false],
            [Permission::CatalogManage, true, false],
            // Chambers, the specialty list and chamber assignment: admin only by default.
            [Permission::PracticeManage, false, false],
            [Permission::UsersManage, false, false],
            [Permission::RolesManage, false, false],
            [Permission::AuditRead, false, false],
        ];

        $named = [];
        foreach ($rows as $row) {
            $named[$row[0]->value] = $row;
        }

        return $named;
    }

    public function test_matrix_covers_every_permission(): void
    {
        $this->assertEqualsCanonicalizing(Permission::values(), array_keys(self::matrix()));
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
