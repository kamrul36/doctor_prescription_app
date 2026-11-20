<?php

namespace App\Domain\Finance;

use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\InvoiceLine;
use App\Domain\Finance\Models\Payment;

/**
 * The one place that recomputes an invoice's money columns and status
 * (spec §8.1: `due = total − paid`, status derived, never set by hand).
 *
 * Sums are taken in PHP from Money objects, never with SQL SUM(): SQLite
 * keeps decimals as floating point and would return values such as 0.30000000000000004.
 * Call it with the invoice row locked, after any change to lines, discount or payments.
 */
final class InvoiceTotals
{
    public static function apply(Invoice $invoice): Invoice
    {
        $lines = $invoice->lines()->get();
        $payments = $invoice->payments()->get();

        $subtotal = $lines->reduce(fn (Money $sum, InvoiceLine $line) => $sum->add($line->line_total), Money::zero());
        $paid = $payments->filter(fn (Payment $p) => $p->isActive())
            ->reduce(fn (Money $sum, Payment $p) => $sum->add($p->amount), Money::zero());

        // A discount never exceeds the bill (lines can be repriced lower after it was given).
        $discount = $invoice->discount->greaterThan($subtotal) ? $subtotal : $invoice->discount;
        $total = $subtotal->subtract($discount);

        $invoice->subtotal = $subtotal;
        $invoice->discount = $discount;
        $invoice->total = $total;
        $invoice->paid = $paid;
        // A voided bill is owed by nobody.
        $invoice->due = $invoice->isVoided() ? Money::zero() : $total->subtract($paid);
        $invoice->status = self::status($invoice, $total, $paid);
        $invoice->save();

        return $invoice->setRelation('lines', $lines)->setRelation('payments', $payments);
    }

    /** No money yet: issued (also a zero bill); some: partially paid; nothing left: paid. Voided stays voided. */
    private static function status(Invoice $invoice, Money $total, Money $paid): InvoiceStatus
    {
        return match (true) {
            $invoice->isVoided() => InvoiceStatus::Voided,
            $paid->isZero() || $paid->isNegative() => InvoiceStatus::Issued,
            $paid->lessThan($total) => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Paid,
        };
    }
}
