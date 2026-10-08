<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Models\Procedure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create or update a procedure (the route has no {procedure} when creating). */
class SaveProcedureRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Procedure|null $item */
        $item = $this->route('procedure');

        return (bool) ($item === null
            ? $this->user()?->can('create', Procedure::class)
            : $this->user()?->can('update', $item));
    }

    /** Codes are stored uppercase so `scal` and `SCAL` are the same code. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Procedure|null $item */
        $item = $this->route('procedure');

        return [
            // Unique over deleted rows too, exactly like the database constraint.
            'code' => ['required', 'string', 'regex:/^[A-Z0-9_-]{2,32}$/',
                Rule::unique('procedures', 'code')->ignore($item?->id)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'default_fee' => ['nullable', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'is_billable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use 2 to 32 letters, digits, dashes or underscores.',
            'code.unique' => 'This procedure code is already in use (it may belong to a deleted procedure).',
            'default_fee.regex' => 'Enter an amount such as 500 or 500.50 (up to 7 digits and 2 decimals).',
        ];
    }
}
