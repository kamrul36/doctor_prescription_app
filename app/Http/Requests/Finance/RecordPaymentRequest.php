<?php

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Money received against an invoice. The idempotency key comes from the
 * `Idempotency-Key` header (API) or the hidden form field (web); the same
 * key sent again by the same user returns the first payment.
 */
class RecordPaymentRequest extends FormRequest
{
    public const MONEY = '/^\d{1,7}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        /** @var Invoice $invoice */
        $invoice = $this->route('invoice');

        return (bool) $this->user()?->can('recordPayment', $invoice);
    }

    protected function prepareForValidation(): void
    {
        if ($this->headers->has('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
        if (is_string($this->input('amount'))) {
            $this->merge(['amount' => trim($this->input('amount'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /** Decimal string, e.g. `500` or `500.50`; at most the amount due. */
            'amount' => ['required', 'string', 'regex:'.self::MONEY],
            /** `cash`, `bkash`, `nagad`, `card` or `bank`. */
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            /** Transaction id for bKash / Nagad / card / bank. */
            'reference' => ['nullable', 'string', 'max:100'],
            /** Unique per request; send it as the `Idempotency-Key` header. */
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100', 'regex:/^[A-Za-z0-9_\-:.]+$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.regex' => __('Enter an amount such as 500 or 500.50 (up to 7 digits and 2 decimals).'),
            'method.enum' => __('Choose how the money was paid.'),
            'idempotency_key.required' => __('Send an Idempotency-Key header with each payment.'),
            'idempotency_key.*' => __('The Idempotency-Key must be 8 to 100 letters, digits, dashes or underscores.'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['amount' => __('amount'), 'method' => __('payment method'), 'reference' => __('reference')];
    }
}
