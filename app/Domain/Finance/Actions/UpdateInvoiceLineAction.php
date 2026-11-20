<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Finance\InvoiceTotals;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\InvoiceLine;
use App\Domain\Finance\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes a line's price, e.g. a consultation billed at 0 because no fee was
 * set yet. Only while nothing has been paid and the invoice is not voided,
 * so a receipt already given never disagrees with the bill. Permission
 * (`finance.discount`) is checked by the policy.
 */
class UpdateInvoiceLineAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array{unit_price: string}  $data  validated by UpdateInvoiceLineRequest */
    public function handle(User $actor, Invoice $invoice, int $lineId, array $data): Invoice
    {
        $price = Money::fromDecimal($data['unit_price']);

        return DB::transaction(function () use ($invoice, $lineId, $price) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            /** @var InvoiceLine $line */
            $line = $invoice->lines()->whereKey($lineId)->firstOrFail();

            if ($invoice->isVoided()) {
                throw ValidationException::withMessages(['unit_price' => __('This invoice is voided.')]);
            }
            if ($invoice->payments()->whereNull('voided_at')->exists()) {
                throw ValidationException::withMessages(['unit_price' => __('Prices cannot change after a payment. Void the payment first, or give a discount.')]);
            }

            $before = $line->unit_price->toDecimal();
            $line->unit_price = $price;
            $line->line_total = $price->multiply($line->quantity);
            $line->save();
            InvoiceTotals::apply($invoice);

            $this->audit->log('invoice.line_updated', $invoice, [
                'invoice_no' => $invoice->invoice_no, 'line_id' => $line->id, 'from' => $before, 'to' => $price->toDecimal(),
            ]);

            return $invoice;
        });
    }
}
