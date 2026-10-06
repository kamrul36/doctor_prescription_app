<?php

namespace App\Domain\Access;

/**
 * Names and initial permission sets of the roles created by AccessSeeder.
 *
 * Roles are data: a super admin can change their permissions at runtime, so
 * code authorizes by Permission, never by role name. The one exception is
 * SUPER_ADMIN, which bypasses all checks through Gate::before.
 */
final class Role
{
    public const SUPER_ADMIN = 'super_admin';

    public const DOCTOR = 'doctor';

    public const ASSISTANT = 'assistant';

    public const GUARD = 'web';

    /** @return array<string, list<Permission>> */
    public static function defaultPermissions(): array
    {
        $adminOnly = [Permission::UsersManage, Permission::RolesManage, Permission::AuditRead];

        $doctor = [];
        foreach (Permission::cases() as $permission) {
            if (! in_array($permission, $adminOnly, true)) {
                $doctor[] = $permission;
            }
        }

        return [
            self::SUPER_ADMIN => [],
            self::DOCTOR => $doctor,
            self::ASSISTANT => [
                Permission::PatientsRead,
                Permission::PatientsWrite,
                Permission::CasesRead,
                Permission::PrescriptionsPrint,
                Permission::PaymentsRecord,
                Permission::FinanceRead,
                Permission::ExpensesWrite,
                Permission::ReportsBasic,
            ],
        ];
    }
}
