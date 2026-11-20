<?php

namespace App\Domain\Finance\Reports;

use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Money;
use App\Domain\Practice\VisitType;
use Illuminate\Support\Carbon;

/**
 * Monthly summary (spec §8.4): money received in the month by visit type,
 * expenses by category, net, and the day-by-day trend. Income is counted when
 * it is received (active payments), expenses on the day they were spent; the
 * same rules as the cash book, so `income − expenses` equals the cash book's
 * change over the month. Closed months are cached.
 */
final class MonthlySummaryReport
{
    /**
     * @return array{
     *     month: string,
     *     income: Money,
     *     income_by_visit_type: list<array{visit_type: VisitType|null, amount: Money}>,
     *     expenses: Money,
     *     expenses_by_category: list<array{category_id: int, category: string, amount: Money}>,
     *     net: Money,
     *     daily: list<array{date: string, income: Money, expenses: Money}>
     * }
     */
    public static function run(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $compute = fn () => self::compute($start);

        return $start->copy()->endOfMonth()->isBefore(now()->startOfDay())
            ? ReportCache::remember('monthly-summary', ['month' => $start->format('Y-m'), 'locale' => app()->getLocale()], $compute)
            : $compute();
    }

    /** @return array<string, mixed> */
    private static function compute(Carbon $start): array
    {
        $end = $start->copy()->endOfMonth();

        $payments = Payment::query()
            ->whereNull('voided_at')
            ->whereBetween('received_at', [$start, $end])
            ->with('invoice.caseHistory:id,visit_type')
            ->get();
        $expenses = Expense::query()
            ->whereDate('spent_on', '>=', $start->toDateString())
            ->whereDate('spent_on', '<=', $end->toDateString())
            ->with('category')
            ->get();

        $byType = [];
        foreach ([...VisitType::cases(), null] as $type) {
            $rows = $payments->filter(fn (Payment $p) => $p->invoice->caseHistory?->visit_type === $type);
            if ($rows->isNotEmpty()) {
                $byType[] = ['visit_type' => $type, 'amount' => Sum::of($rows->pluck('amount'))];
            }
        }

        $byCategory = $expenses->groupBy('category_id')
            ->map(fn ($rows, $id) => [
                'category_id' => (int) $id,
                'category' => (string) $rows->first()->category->localized('name'),
                'amount' => Sum::of($rows->pluck('amount')),
            ])
            ->sortByDesc(fn (array $row) => $row['amount']->minor())->values()->all();

        $daily = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();
            $daily[] = [
                'date' => $date,
                'income' => Sum::of($payments->filter(fn (Payment $p) => $p->received_at->toDateString() === $date)->pluck('amount')),
                'expenses' => Sum::of($expenses->filter(fn (Expense $e) => $e->spent_on->toDateString() === $date)->pluck('amount')),
            ];
        }

        $income = Sum::of($payments->pluck('amount'));
        $spent = Sum::of($expenses->pluck('amount'));

        return [
            'month' => $start->format('Y-m'),
            'income' => $income,
            'income_by_visit_type' => $byType,
            'expenses' => $spent,
            'expenses_by_category' => $byCategory,
            'net' => $income->subtract($spent),
            'daily' => $daily,
        ];
    }
}
