<?php

namespace App\Domain\Finance\Reports;

use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Money;
use App\Domain\Finance\PaymentMethod;
use Illuminate\Support\Carbon;

/**
 * Cash book (spec §8.4): money in (active payments, by the time received) and
 * money out (expenses, by the day spent) in date order, with a running
 * balance that starts from everything before the period. Voided payments and
 * deleted expenses do not count. Closed periods are cached.
 */
final class CashBookReport
{
    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     opening: Money,
     *     money_in: Money,
     *     money_out: Money,
     *     closing: Money,
     *     entries: list<array{date: string, type: string, reference: string, description: string, method: PaymentMethod, in: Money|null, out: Money|null, balance: Money, link_id: int}>
     * }
     */
    public static function run(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $compute = fn () => self::compute($from, $to);

        return $to->isBefore(now()->startOfDay())
            ? ReportCache::remember('cash-book', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'locale' => app()->getLocale()], $compute)
            : $compute();
    }

    /** @return array<string, mixed> */
    private static function compute(Carbon $from, Carbon $to): array
    {
        $opening = Sum::of(Payment::query()->whereNull('voided_at')->where('received_at', '<', $from)->get(['amount'])->pluck('amount'))
            ->subtract(Sum::of(Expense::query()->whereDate('spent_on', '<', $from->toDateString())->get(['amount'])->pluck('amount')));

        $rows = [];
        foreach (Payment::query()->whereNull('voided_at')->whereBetween('received_at', [$from, $to])->with('invoice.patient')->get() as $p) {
            $rows[] = [
                'sort' => $p->received_at->format('Y-m-d').' 0 '.$p->received_at->format('H:i:s').' '.str_pad((string) $p->id, 10, '0', STR_PAD_LEFT),
                'date' => $p->received_at->toDateString(), 'type' => 'payment', 'reference' => $p->receipt_no,
                'description' => $p->invoice->invoice_no.' · '.$p->invoice->patient->localizedName(),
                'method' => $p->method, 'in' => $p->amount, 'out' => null, 'link_id' => $p->invoice_id,
            ];
        }
        $expenses = Expense::query()->whereDate('spent_on', '>=', $from->toDateString())->whereDate('spent_on', '<=', $to->toDateString());
        foreach ($expenses->with('category')->get() as $e) {
            $rows[] = [
                'sort' => $e->spent_on->format('Y-m-d').' 1 '.str_pad((string) $e->id, 10, '0', STR_PAD_LEFT),
                'date' => $e->spent_on->toDateString(), 'type' => 'expense', 'reference' => 'EXP-'.$e->id,
                'description' => trim($e->category->localized('name').($e->note ? ' · '.$e->note : '')),
                'method' => $e->method, 'in' => null, 'out' => $e->amount, 'link_id' => $e->id,
            ];
        }
        usort($rows, fn (array $a, array $b) => strcmp($a['sort'], $b['sort']));

        $balance = $opening;
        $in = Money::zero();
        $out = Money::zero();
        $entries = [];
        foreach ($rows as $row) {
            $in = $in->add($row['in'] ?? Money::zero());
            $out = $out->add($row['out'] ?? Money::zero());
            $balance = $balance->add($row['in'] ?? Money::zero())->subtract($row['out'] ?? Money::zero());
            unset($row['sort']);
            $entries[] = $row + ['balance' => $balance];
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'opening' => $opening,
            'money_in' => $in,
            'money_out' => $out,
            'closing' => $balance,
            'entries' => $entries,
        ];
    }
}
