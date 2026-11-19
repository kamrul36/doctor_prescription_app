<?php

namespace App\Http\Requests\Practice;

use App\Domain\Practice\Models\Chamber;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class SaveChamberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Chamber::class);
    }

    /** The Blade form always sends spare rows; rows left empty are dropped. */
    protected function prepareForValidation(): void
    {
        // Spare phone fields left empty are dropped too.
        if (is_array($this->input('phones'))) {
            $this->merge(['phones' => array_values(array_filter($this->input('phones'), fn ($p) => is_string($p) ? trim($p) !== '' : $p !== null))]);
        }

        if (is_array($this->input('branches'))) {
            $this->merge(['branches' => array_values(array_filter(
                $this->input('branches'),
                fn ($row) => is_array($row) && (filled($row['name_en'] ?? null) || filled($row['name_bn'] ?? null) || filled($row['phones'] ?? null)),
            ))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'address_en' => ['nullable', 'string', 'max:1000'],
            'address_bn' => ['nullable', 'string', 'max:1000'],
            // A list from the API, or one comma-separated string from the Blade form.
            'phones' => ['nullable', Phone::contactList(4)],
            'phones.*' => ['string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'branches' => ['sometimes', 'nullable', 'array', 'max:20'],
            'branches.*.name_en' => ['required', 'string', 'max:255'],
            'branches.*.name_bn' => ['nullable', 'string', 'max:255'],
            // A list from the API, or one comma-separated string from the Blade form.
            'branches.*.phones' => ['nullable', Phone::contactList()],
            'branches.*.phones.*' => ['string', 'max:32'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['branches.*.name_en.required' => __('Give the branch a name (English).')];
    }
}
