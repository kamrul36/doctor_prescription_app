<?php

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Models\Invoice;
use App\Support\Rules\Owned;
use Illuminate\Foundation\Http\FormRequest;

/** "Ref Dr." (free text), "Under Dr." and a note, printed on the receipt. Fields left out are kept. */
class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Invoice $invoice */
        $invoice = $this->route('invoice');

        return (bool) $this->user()?->can('update', $invoice);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /** Referring doctor, as typed. */
            'referred_by' => ['sometimes', 'nullable', 'string', 'max:255'],
            /** A doctor id; defaults to the visit's doctor. */
            'under_doctor_id' => ['sometimes', 'nullable', 'integer', Owned::exists('doctors')],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['referred_by' => __('referring doctor'), 'under_doctor_id' => __('doctor'), 'note' => __('note')];
    }
}
