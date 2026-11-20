<?php

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

/** A line's price (`finance.discount`), only while the invoice has no payment. */
class UpdateInvoiceLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Invoice $invoice */
        $invoice = $this->route('invoice');

        return (bool) $this->user()?->can('discount', $invoice);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /** Decimal string, e.g. `500` or `500.50`. */
            'unit_price' => ['required', 'string', 'regex:'.RecordPaymentRequest::MONEY],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['unit_price.regex' => __('Enter an amount such as 500 or 500.50 (up to 7 digits and 2 decimals).')];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['unit_price' => __('price')];
    }
}
