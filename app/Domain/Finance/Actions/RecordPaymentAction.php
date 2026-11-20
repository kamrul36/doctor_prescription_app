<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Finance\InvoiceTotals;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Money;
use App\Domain\Finance\NumberGenerator;
use App\Domain\Finance\PaymentMethod;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records money received (spec §8.1): partial payments allowed, more than
 * the amount due refused, a receipt number from NumberGenerator.
 *
 * Idempotent per user and key (rule A10): sending the same key again returns
 * the first payment instead of making a second one, so a double click or a
 * retried request never charges twice. The same key with a different
 * invoice, amount or method is a 422.
 */
class RecordPaymentAction
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{amount: string, method: string, reference?: string|null, idempotency_key: string}  $data  validated by RecordPaymentRequest
     */
    public function handle(User $actor, Invoice $invoice, array $data): Payment
    {
        $amount = Money::fromDecimal($data['amount']);
        $method = PaymentMethod::from($data['method']);
        $key = $data['idempotency_key'];

        try {
            return DB::transaction(function () use ($actor, $invoice, $data, $amount, $method, $key) {
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

                if (($existing = $this->existing($actor, $key)) !== null) {
                    return $this->replay($existing, $invoice, $amount, $method);
                }

                if ($invoice->isVoided()) {
                    throw ValidationException::withMessages(['amount' => __('This invoice is voided; it cannot take payments.')]);
                }
                if ($amount->isZero() || $amount->isNegative()) {
                    throw ValidationException::withMessages(['amount' => __('Enter an amount greater than zero.')]);
                }
                if ($amount->greaterThan($invoice->due)) {
                    throw ValidationException::withMessages(['amount' => __('The amount is more than the :due due.', ['due' => $invoice->due->format()])]);
                }

                $payment = new Payment;
                $payment->receipt_no = $this->numbers->next(NumberGenerator::RECEIPT);
                $payment->invoice_id = $invoice->id;
                $payment->amount = $amount;
                $payment->method = $method;
                $payment->reference = $data['reference'] ?? null;
                $payment->received_by = $actor->id;
                $payment->received_at = now();
                $payment->idempotency_key = $key;
                $payment->save();

                InvoiceTotals::apply($invoice);

                $this->audit->log('payment.recorded', $payment, [
                    'invoice_no' => $invoice->invoice_no, 'receipt_no' => $payment->receipt_no,
                    'amount' => $amount->toDecimal(), 'method' => $method->value,
                ]);

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            // Two requests with the same key passed the lookup at the same moment; the first one won.
            $existing = $this->existing($actor, $key) ?? throw ValidationException::withMessages(['idempotency_key' => __('This payment was already sent.')]);

            return $this->replay($existing, $invoice, $amount, $method);
        }
    }

    private function existing(User $actor, string $key): ?Payment
    {
        return Payment::query()->where('received_by', $actor->id)->where('idempotency_key', $key)->first();
    }

    private function replay(Payment $existing, Invoice $invoice, Money $amount, PaymentMethod $method): Payment
    {
        if ($existing->invoice_id !== $invoice->id || ! $existing->amount->equals($amount) || $existing->method !== $method) {
            throw ValidationException::withMessages(['idempotency_key' => __('This key was already used for a different payment.')]);
        }

        return $existing;
    }
}
