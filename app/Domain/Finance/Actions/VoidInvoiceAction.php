<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Finance\InvoiceStatus;
use App\Domain\Finance\InvoiceTotals;
use App\Domain\Finance\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Voids an invoice with a reason, only while it has no active payment (void
 * those first). The invoice and its lines stay for the record; it takes no
 * more payments or lines. Voiding again changes nothing. Also used when a
 * draft visit is cancelled (spec §7.5).
 */
class VoidInvoiceAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, Invoice $invoice, string $reason): Invoice
    {
        return DB::transaction(function () use ($actor, $invoice, $reason) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->isVoided()) {
                return $invoice;
            }
            if ($invoice->payments()->whereNull('voided_at')->exists()) {
                throw ValidationException::withMessages(['status' => __('This invoice has payments. Void them first.')]);
            }

            $invoice->status = InvoiceStatus::Voided;
            $invoice->voided_at = now();
            $invoice->voided_by = $actor->id;
            $invoice->void_reason = $reason;
            InvoiceTotals::apply($invoice);

            $this->audit->log('invoice.voided', $invoice, ['invoice_no' => $invoice->invoice_no]);

            return $invoice;
        });
    }
}
