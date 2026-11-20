<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Finance\Money;
use App\Domain\Finance\Reports\ReportCache;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records or corrects an expense (spec §4.6). A new expense needs an active
 * category; an existing one may keep the category it already has even if it
 * was deactivated since. The audit entry holds ids, amount and date only.
 */
class SaveExpenseAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array{category_id: int|string, amount: string, spent_on: string, method: string, note?: string|null}  $data  validated by SaveExpenseRequest */
    public function handle(User $actor, ?Expense $expense, array $data): Expense
    {
        $amount = Money::fromDecimal($data['amount']);
        if ($amount->isZero() || $amount->isNegative()) {
            throw ValidationException::withMessages(['amount' => __('Enter an amount greater than zero.')]);
        }

        return DB::transaction(function () use ($actor, $expense, $data, $amount) {
            $creating = $expense === null;
            $expense = $creating ? new Expense : Expense::query()->lockForUpdate()->findOrFail($expense->id);

            $categoryId = (int) $data['category_id'];
            $category = ExpenseCategory::query()->find($categoryId);
            if ($category === null || (! $category->is_active && $categoryId !== $expense->category_id)) {
                throw ValidationException::withMessages(['category_id' => __('Choose an active category.')]);
            }

            $expense->fill([
                'category_id' => $categoryId,
                'amount' => $amount,
                'spent_on' => $data['spent_on'],
                'method' => $data['method'],
                'note' => $data['note'] ?? null,
            ]);
            if ($creating) {
                $expense->recorded_by = $actor->id;
            }
            $expense->updated_by = $actor->id;
            $changed = array_keys($expense->getDirty());
            $expense->save();
            ReportCache::flush();

            $this->audit->log($creating ? 'expense.created' : 'expense.updated', $expense, [
                'amount' => $amount->toDecimal(), 'spent_on' => $expense->spent_on->toDateString(), 'fields' => $changed,
            ]);

            return $expense;
        });
    }
}
