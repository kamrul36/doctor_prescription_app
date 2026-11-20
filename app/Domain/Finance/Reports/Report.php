<?php

namespace App\Domain\Finance\Reports;

use App\Domain\Access\Permission;
use App\Models\User;

/**
 * The four finance reports (spec §8.4) and who may see them (§8.5):
 * `reports.read` sees all; `reports.basic` (assistant default) sees the daily
 * collection and the dues. By permission only (rule A1).
 */
enum Report: string
{
    case DailyCollection = 'daily-collection';
    case MonthlySummary = 'monthly-summary';
    case Dues = 'dues';
    case CashBook = 'cash-book';

    public function allows(User $user): bool
    {
        if ($user->can(Permission::ReportsRead->value)) {
            return true;
        }

        return $user->can(Permission::ReportsBasic->value) && in_array($this, [self::DailyCollection, self::Dues], true);
    }

    /** English text is the key; Bangla in lang/bn.json. */
    public function title(): string
    {
        return match ($this) {
            self::DailyCollection => __('Daily collection'),
            self::MonthlySummary => __('Monthly summary'),
            self::Dues => __('Dues'),
            self::CashBook => __('Cash book'),
        };
    }

    public function summary(): string
    {
        return match ($this) {
            self::DailyCollection => __('Payments by method, invoiced vs collected, patients billed.'),
            self::MonthlySummary => __('Income by visit type, expenses by category, net, daily trend.'),
            self::Dues => __('Unpaid bills per patient, by age.'),
            self::CashBook => __('Payments and expenses in date order with a running balance.'),
        };
    }

    /** @return list<self> */
    public static function visibleTo(User $user): array
    {
        return array_values(array_filter(self::cases(), fn (self $r) => $r->allows($user)));
    }

    public static function anyVisibleTo(User $user): bool
    {
        return self::visibleTo($user) !== [];
    }
}
