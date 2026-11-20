<?php

namespace App\Domain\Finance\Reports;

use App\Domain\Finance\InvoiceStatus;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Money;
use App\Domain\Finance\PaymentMethod;
use Illuminate\Support\Carbon;

/**
 * Daily collection (spec §8.4): payments taken that day by method, what was
 * invoiced that day, and how many patients were billed. Voided payments and
 * invoices do not count. Past days are cached; today is live.
 */
final class DailyCollectionReport
{
    /**
     * @return array{
     *     date: string,
     *     collected: Money,
     *     by_method: list<array{method: PaymentMethod, count: int, amount: Money}>,
     *     invoiced: Money,
     *     invoice_count: int,
     *     patient_count: int,
     *     payments: list<array{receipt_no: string, invoice_id: int, invoice_no: string, patient: string, patient_code: string, method: PaymentMethod, amount: Money, received_at: string, received_by: string|null}>
     * }
     */
    public static function run(Carbon $date): array
    {
        $day = $date->copy()->startOfDay();
        $compute = fn () => self::compute($day);

        return $day->isBefore(now()->startOfDay())
            ? ReportCache::remember('daily-collection', ['date' => $day->toDateString(), 'locale' => app()->getLocale()], $compute)
            : $compute();
    }

    /** @return array<string, mixed> */
    private static function compute(Carbon $day): array
    {
        $payments = Payment::query()
            ->whereNull('voided_at')
            ->whereBetween('received_at', [$day, $day->copy()->endOfDay()])
            ->with(['invoice.patient', 'receiver'])
            ->orderBy('received_at')->orderBy('id')
            ->get();

        $byMethod = [];
        foreach (PaymentMethod::cases() as $method) {
            $rows = $payments->where('method', $method);
            if ($rows->isNotEmpty()) {
                $byMethod[] = ['method' => $method, 'count' => $rows->count(), 'amount' => Sum::of($rows->pluck('amount'))];
            }
        }

        $invoices = Invoice::query()
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->whereBetween('issued_at', [$day, $day->copy()->endOfDay()])
            ->get(['id', 'patient_id', 'total']);

        return [
            'date' => $day->toDateString(),
            'collected' => Sum::of($payments->pluck('amount')),
            'by_method' => $byMethod,
            'invoiced' => Sum::of($invoices->pluck('total')),
            'invoice_count' => $invoices->count(),
            'patient_count' => $invoices->pluck('patient_id')->unique()->count(),
            'payments' => $payments->map(fn (Payment $p) => [
                'receipt_no' => $p->receipt_no,
                'invoice_id' => $p->invoice_id,
                'invoice_no' => $p->invoice->invoice_no,
                'patient' => $p->invoice->patient->localizedName(),
                'patient_code' => $p->invoice->patient->code,
                'method' => $p->method,
                'amount' => $p->amount,
                'received_at' => $p->received_at->toIso8601String(),
                'received_by' => $p->receiver?->name,
            ])->values()->all(),
        ];
    }
}
