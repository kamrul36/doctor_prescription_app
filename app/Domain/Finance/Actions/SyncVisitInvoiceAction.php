<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Finance\InvoiceLineKind;
use App\Domain\Finance\InvoiceTotals;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\InvoiceLine;
use App\Domain\Finance\Money;
use App\Domain\Finance\NumberGenerator;
use App\Domain\Practice\Models\DoctorChamberFee;
use App\Domain\Practice\VisitType;
use Illuminate\Support\Facades\DB;

/**
 * Makes sure a visit has its invoice (spec §3 item 8, §8.2), and keeps the
 * consultation line in step with the visit type and chamber while nothing
 * has been paid.
 *
 * Idempotent: visits saved before P1.7 get their invoice the next time they
 * are saved or finalized. The consultation price is a snapshot of the
 * doctor's fee for that chamber and visit type; with no fee set it is 0 (the
 * pad warns) and is filled in on the next save or finalize once the fee
 * exists. A `free` visit is always 0. A deliberate 0 for a paying visit type
 * is a discount, not a 0 price.
 *
 * Called inside the visit's transaction with the visit row already locked
 * (lock order: visit, then invoice).
 */
class SyncVisitInvoiceAction
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /** @param  bool  $reprice  the visit type or chamber changed: price the consultation line again (only while unpaid) */
    public function handle(CaseHistory $case, bool $reprice = false): Invoice
    {
        return DB::transaction(function () use ($case, $reprice) {
            $invoice = Invoice::query()->where('case_history_id', $case->id)->lockForUpdate()->first();

            if ($invoice === null) {
                return $this->open($case);
            }

            if ($invoice->isVoided() || $invoice->payments()->whereNull('voided_at')->exists()) {
                return $invoice;
            }

            $line = $invoice->lines()->where('kind', InvoiceLineKind::Consultation)->first();
            // A consultation billed at 0 because no fee was set picks the fee up once the doctor sets it.
            $feeArrived = $line !== null && $line->unit_price->isZero() && $case->visit_type !== VisitType::Free && self::feeFor($case) !== null;

            if ($reprice || $feeArrived) {
                $before = $line?->unit_price->toDecimal();
                $line = $this->consultationLine($invoice, $case, $line);
                InvoiceTotals::apply($invoice);

                if ($before !== $line->unit_price->toDecimal()) {
                    $this->audit->log('invoice.repriced', $invoice, [
                        'invoice_no' => $invoice->invoice_no, 'from' => $before, 'to' => $line->unit_price->toDecimal(),
                    ]);
                }
            }

            return $invoice;
        });
    }

    /** The doctor's fee for this visit, or null when none is set. A free visit costs nothing. */
    public static function feeFor(CaseHistory $case): ?Money
    {
        if ($case->visit_type === VisitType::Free) {
            return Money::zero();
        }

        return DoctorChamberFee::query()
            ->where('doctor_id', $case->doctor_id)
            ->where('chamber_id', $case->chamber_id)
            ->where('visit_type', $case->visit_type->value)
            ->first()?->amount;
    }

    private function open(CaseHistory $case): Invoice
    {
        $invoice = new Invoice;
        $invoice->invoice_no = $this->numbers->next(NumberGenerator::INVOICE);
        $invoice->patient_id = $case->patient_id;
        $invoice->case_history_id = $case->id;
        $invoice->under_doctor_id = $case->doctor_id;
        $invoice->issued_at = now();
        $invoice->issued_by = auth()->id();
        if ($case->visit_type === VisitType::Free) {
            $invoice->note = __('Free visit', [], 'en');
        }
        $invoice->save();

        $line = $this->consultationLine($invoice, $case, null);
        InvoiceTotals::apply($invoice);

        $this->audit->log('invoice.opened', $invoice, [
            'invoice_no' => $invoice->invoice_no, 'case_history_id' => $case->id,
            'consultation' => $line->unit_price->toDecimal(), 'fee_missing' => self::feeFor($case) === null,
        ]);

        return $invoice;
    }

    private function consultationLine(Invoice $invoice, CaseHistory $case, ?InvoiceLine $line): InvoiceLine
    {
        $price = self::feeFor($case) ?? Money::zero();
        $line ??= new InvoiceLine(['kind' => InvoiceLineKind::Consultation, 'sort_order' => 0]);

        $line->fill([
            'item_code' => 'CONS',
            'description_en' => __('Consultation', [], 'en').' · '.$case->visit_type->label('en'),
            'description_bn' => __('Consultation', [], 'bn').' · '.$case->visit_type->label('bn'),
            'quantity' => 1,
            'unit_price' => $price,
            'line_total' => $price,
        ]);
        $invoice->lines()->save($line);

        return $line;
    }
}
