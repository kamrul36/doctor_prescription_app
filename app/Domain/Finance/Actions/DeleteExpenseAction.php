<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Reports\ReportCache;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Soft-deletes an expense (spec §8.1: no hard deletes). It stops counting in the reports. */
class DeleteExpenseAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, Expense $expense): void
    {
        DB::transaction(function () use ($actor, $expense) {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            $expense->updated_by = $actor->id;
            $expense->save();
            $expense->delete();
            ReportCache::flush();

            $this->audit->log('expense.deleted', $expense, ['amount' => $expense->amount->toDecimal(), 'spent_on' => $expense->spent_on->toDateString()]);
        });
    }
}
