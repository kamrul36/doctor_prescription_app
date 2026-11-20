<?php

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\PaymentMethod;
use App\Support\Rules\Owned;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** An expense: category, amount, the day it was spent (not in the future), how it was paid, and a note. */
class SaveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            ? (bool) $this->user()?->can('update', $expense)
            : (bool) $this->user()?->can('create', Expense::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('amount'))) {
            $this->merge(['amount' => trim($this->input('amount'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /** An active expense category id. */
            'category_id' => ['required', 'integer', Owned::exists('expense_categories')],
            /** Decimal string, e.g. `1500` or `1500.50`. */
            'amount' => ['required', 'string', 'regex:'.RecordPaymentRequest::MONEY],
            /** `YYYY-MM-DD`, today or earlier. */
            // `before:` tomorrow: with date_format the value carries the current time, so `before_or_equal:` today would refuse today.
            'spent_on' => ['required', 'date_format:Y-m-d', 'before:'.now()->addDay()->toDateString()],
            /** `cash`, `bkash`, `nagad`, `card` or `bank`. */
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.regex' => __('Enter an amount such as 500 or 500.50 (up to 7 digits and 2 decimals).'),
            'spent_on.before' => __('The date cannot be in the future.'),
            'spent_on.date_format' => __('Enter a date such as 2026-11-20.'),
            'method.enum' => __('Choose how the money was paid.'),
            'category_id.exists' => __('Choose an active category.'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['category_id' => __('category'), 'amount' => __('amount'), 'spent_on' => __('date'), 'method' => __('payment method'), 'note' => __('note')];
    }
}
