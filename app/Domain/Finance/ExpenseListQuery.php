<?php

namespace App\Domain\Finance;

use App\Domain\Finance\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** The expense list for the web page and the API, built in one place (rule A9). Filters: `month` (YYYY-MM, default this month), `category_id`. */
final class ExpenseListQuery
{
    /**
     * @param  array{month?: string|null, category_id?: int|string|null}  $filters
     * @return Builder<Expense>
     */
    public static function build(array $filters): Builder
    {
        $month = self::month($filters['month'] ?? null);

        return Expense::query()
            ->with(['category', 'recorder'])
            ->whereDate('spent_on', '>=', $month->toDateString())
            ->whereDate('spent_on', '<=', $month->copy()->endOfMonth()->toDateString())
            ->when(filled($filters['category_id'] ?? null), fn (Builder $q) => $q->where('category_id', (int) $filters['category_id']))
            ->orderByDesc('spent_on')->orderByDesc('id');
    }

    public static function month(?string $value): Carbon
    {
        if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            // `!` resets the missing day; without it the 31st of today's month rolls February into March.
            return Carbon::createFromFormat('!Y-m', $value)->startOfMonth()->startOfDay();
        }

        return now()->startOfMonth();
    }
}
