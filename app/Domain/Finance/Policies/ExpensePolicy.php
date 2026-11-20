<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Access\Permission;
use App\Domain\Finance\Models\Expense;
use App\Models\User;

/**
 * Expenses and their categories (spec §8.5): `expenses.write` records,
 * corrects and deletes them (doctor and assistant by default); `reports.read`
 * may also look at the list. By permission only (rule A1).
 */
class ExpensePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::ExpensesWrite->value) || $actor->can(Permission::ReportsRead->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::ExpensesWrite->value);
    }

    public function update(User $actor, Expense $expense): bool
    {
        return $actor->can(Permission::ExpensesWrite->value);
    }

    public function delete(User $actor, Expense $expense): bool
    {
        return $actor->can(Permission::ExpensesWrite->value);
    }

    /** Categories are managed by whoever records expenses. */
    public function manageCategories(User $actor): bool
    {
        return $actor->can(Permission::ExpensesWrite->value);
    }
}
