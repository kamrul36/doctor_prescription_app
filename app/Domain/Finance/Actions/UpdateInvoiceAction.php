<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Finance\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** The desk details printed on the receipt: "Ref Dr." (free text), "Under Dr." and a note. */
class UpdateInvoiceAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array{referred_by?: string|null, under_doctor_id?: int|string|null, note?: string|null}  $data  validated by UpdateInvoiceRequest */
    public function handle(User $actor, Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            foreach (['referred_by', 'under_doctor_id', 'note'] as $field) {
                if (array_key_exists($field, $data)) {
                    $invoice->{$field} = $field === 'under_doctor_id' && $data[$field] !== null ? (int) $data[$field] : $data[$field];
                }
            }

            $changed = array_keys($invoice->getDirty());
            $invoice->save();

            if ($changed !== []) {
                $this->audit->log('invoice.updated', $invoice, ['invoice_no' => $invoice->invoice_no, 'fields' => $changed]);
            }

            return $invoice;
        });
    }
}
