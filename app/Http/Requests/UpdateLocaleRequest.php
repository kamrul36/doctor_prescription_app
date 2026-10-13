<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The UI language switch (web and API). Anyone may switch; guests only for the session. */
class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /** `bn` (Bangla, the default) or `en`. */
            'locale' => ['required', 'string', Rule::in(config('app.supported_locales'))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['locale.in' => __('Choose Bangla or English.')];
    }
}
