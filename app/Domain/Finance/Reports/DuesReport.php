<?php

namespace App\Domain\Finance\Reports;

use App\Domain\Finance\InvoiceStatus;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Money;
use Illuminate\Support\Carbon;

/**
 * Dues (spec §8.4): unpaid and partly paid invoices per patient, with the age
 * of each bill in days since it was issued: 0–7, 8–30, over 30. Voided bills
 * owe nothing. Always live.
 */
final class DuesReport
{
    public const BUCKETS = ['0_7', '8_30', '30_plus'];

    /**
     * @return array{
     *     as_of: string,
     *     total: Money,
     *     buckets: array<string, Money>,
     *     patients: list<array{patient_id: int, patient: string, patient_code: string, phone: string|null, due: Money, buckets: array<string, Money>, invoices: list<array{id: int, invoice_no: string, issued_at: string, age_days: int, total: Money, paid: Money, due: Money}>}>
     * }
     */
    public static function run(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy()->endOfDay();

        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->where('due', '>', 0)
            ->where('issued_at', '<=', $asOf)
            ->with('patient')
            ->orderBy('issued_at')
            ->get();

        $zero = fn () => array_fill_keys(self::BUCKETS, Money::zero());
        $totals = $zero();
        $patients = [];

        foreach ($invoices as $invoice) {
            $age = (int) $invoice->issued_at->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay());
            $bucket = $age <= 7 ? '0_7' : ($age <= 30 ? '8_30' : '30_plus');

            $row = $patients[$invoice->patient_id] ?? [
                'patient_id' => $invoice->patient_id,
                'patient' => $invoice->patient->localizedName(),
                'patient_code' => $invoice->patient->code,
                'phone' => $invoice->patient->phone,
                'due' => Money::zero(),
                'buckets' => $zero(),
                'invoices' => [],
            ];
            $row['due'] = $row['due']->add($invoice->due);
            $row['buckets'][$bucket] = $row['buckets'][$bucket]->add($invoice->due);
            $row['invoices'][] = [
                'id' => $invoice->id, 'invoice_no' => $invoice->invoice_no, 'issued_at' => $invoice->issued_at->toDateString(),
                'age_days' => $age, 'total' => $invoice->total, 'paid' => $invoice->paid, 'due' => $invoice->due,
            ];
            $patients[$invoice->patient_id] = $row;
            $totals[$bucket] = $totals[$bucket]->add($invoice->due);
        }

        $patients = array_values($patients);
        usort($patients, fn (array $a, array $b) => $b['due']->minor() <=> $a['due']->minor());

        return [
            'as_of' => $asOf->toDateString(),
            'total' => Sum::of($totals),
            'buckets' => $totals,
            'patients' => $patients,
        ];
    }
}
