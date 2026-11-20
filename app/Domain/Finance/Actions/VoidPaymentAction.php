<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Finance\InvoiceTotals;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Voids a payment with a reason (spec §8.1: no deletion). The row stays,
 * stops counting, and the invoice's paid / due / status are recomputed.
 * Voiding again changes nothing.
 */
class VoidPaymentAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($actor, $payment, $reason) {
            // Lock the root, then load the payment through it (rule A7).
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            /** @var Payment $payment */
            $payment = $invoice->payments()->whereKey($payment->id)->firstOrFail();

            if (! $payment->isActive()) {
                return $payment;
            }

            $payment->voided_at = now();
            $payment->voided_by = $actor->id;
            $payment->void_reason = $reason;
            $payment->save();

            InvoiceTotals::apply($invoice);

            $this->audit->log('payment.voided', $payment, [
                'invoice_no' => $invoice->invoice_no, 'receipt_no' => $payment->receipt_no, 'amount' => $payment->amount->toDecimal(),
            ]);

            return $payment;
        });
    }
}
