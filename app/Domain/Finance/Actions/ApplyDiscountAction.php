<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Finance\InvoiceTotals;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets the invoice discount (spec §8.2): needs a reason, at most the
 * subtotal, and never below what is already paid. `0` removes it.
 * Permission (`finance.discount`) is checked by the policy.
 */
class ApplyDiscountAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array{discount: string, discount_reason?: string|null}  $data  validated by ApplyDiscountRequest */
    public function handle(User $actor, Invoice $invoice, array $data): Invoice
    {
        $discount = Money::fromDecimal($data['discount']);
        $reason = $data['discount_reason'] ?? null;

        return DB::transaction(function () use ($invoice, $discount, $reason) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->isVoided()) {
                throw ValidationException::withMessages(['discount' => __('This invoice is voided.')]);
            }
            if ($discount->isNegative()) {
                throw ValidationException::withMessages(['discount' => __('Enter an amount such as 500 or 500.50 (up to 7 digits and 2 decimals).')]);
            }
            if ($discount->greaterThan($invoice->subtotal)) {
                throw ValidationException::withMessages(['discount' => __('The discount cannot be more than the bill (:amount).', ['amount' => $invoice->subtotal->format()])]);
            }
            if ($invoice->subtotal->subtract($discount)->lessThan($invoice->paid)) {
                throw ValidationException::withMessages(['discount' => __('After this discount the bill would be less than the :paid already paid.', ['paid' => $invoice->paid->format()])]);
            }
            if (! $discount->isZero() && blank($reason)) {
                throw ValidationException::withMessages(['discount_reason' => __('Write why the discount is given.')]);
            }

            $before = $invoice->discount->toDecimal();
            $invoice->discount = $discount;
            $invoice->discount_reason = $discount->isZero() ? null : $reason;
            InvoiceTotals::apply($invoice);

            $this->audit->log('invoice.discounted', $invoice, [
                'invoice_no' => $invoice->invoice_no, 'from' => $before, 'to' => $discount->toDecimal(),
            ]);

            return $invoice;
        });
    }
}
