<?php

namespace App\Domain\Access;

/**
 * Every permission the application checks. This enum is the only source of
 * permission names; AccessSeeder syncs it to the database, and roles are
 * mapped to permissions at runtime by a super admin.
 */
enum Permission: string
{
    case PatientsRead = 'patients.read';
    case PatientsWrite = 'patients.write';

    case CasesRead = 'cases.read';
    case CasesReadPrivate = 'cases.read_private';
    case CasesWrite = 'cases.write';
    case CasesFinalize = 'cases.finalize';
    case CasesAmend = 'cases.amend';
    case PrescriptionsPrint = 'prescriptions.print';

    case PaymentsRecord = 'payments.record';
    case FinanceRead = 'finance.read';
    case FinanceDiscount = 'finance.discount';
    case FinanceVoid = 'finance.void';
    case ExpensesWrite = 'expenses.write';
    case ReportsRead = 'reports.read';
    case ReportsBasic = 'reports.basic';

    case TemplatesManage = 'templates.manage';
    case CatalogManage = 'catalog.manage';

    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case AuditRead = 'audit.read';

    public function label(): string
    {
        return match ($this) {
            self::PatientsRead => 'View patients',
            self::PatientsWrite => 'Register and edit patients',
            self::CasesRead => 'View visits and prescriptions',
            self::CasesReadPrivate => 'View private visit notes',
            self::CasesWrite => 'Write visits and prescriptions',
            self::CasesFinalize => 'Finalize visits',
            self::CasesAmend => 'Amend finalized visits',
            self::PrescriptionsPrint => 'Print prescriptions',
            self::PaymentsRecord => 'Record payments',
            self::FinanceRead => 'View invoices and payments',
            self::FinanceDiscount => 'Give discounts',
            self::FinanceVoid => 'Void invoices and payments',
            self::ExpensesWrite => 'Record expenses',
            self::ReportsRead => 'View all reports',
            self::ReportsBasic => 'View daily collection and dues reports',
            self::TemplatesManage => 'Manage prescription templates',
            self::CatalogManage => 'Manage catalogs',
            self::UsersManage => 'Manage users',
            self::RolesManage => 'Manage roles and permissions',
            self::AuditRead => 'View audit log',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::PatientsRead, self::PatientsWrite => 'Patients',
            self::CasesRead, self::CasesReadPrivate, self::CasesWrite,
            self::CasesFinalize, self::CasesAmend, self::PrescriptionsPrint => 'Clinical',
            self::PaymentsRecord, self::FinanceRead, self::FinanceDiscount, self::FinanceVoid,
            self::ExpensesWrite, self::ReportsRead, self::ReportsBasic => 'Finance',
            self::TemplatesManage, self::CatalogManage => 'Setup',
            self::UsersManage, self::RolesManage, self::AuditRead => 'Administration',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    /** @return array<string, list<self>> */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = $permission;
        }

        return $groups;
    }
}
