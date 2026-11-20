<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

/** Voiding an invoice or a payment always needs a reason (spec §8.1). Authorization: the controller. */
class VoidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:500']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason' => __('reason')];
    }
}
