<?php

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

/** Discount on the whole invoice (`finance.discount`); `0` removes it. */
class ApplyDiscountRequest extends FormRequest
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
            /** Decimal string; at most the subtotal and never below what is paid. */
            'discount' => ['required', 'string', 'regex:'.RecordPaymentRequest::MONEY],
            /** Required when the discount is more than 0. */
            'discount_reason' => ['nullable', 'required_unless:discount,0,0.0,0.00', 'string', 'min:3', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'discount.regex' => __('Enter an amount such as 500 or 500.50 (up to 7 digits and 2 decimals).'),
            'discount_reason.required_unless' => __('Write why the discount is given.'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['discount' => __('discount'), 'discount_reason' => __('reason')];
    }
}
