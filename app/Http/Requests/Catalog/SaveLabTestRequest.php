<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Models\LabTest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create or update a lab test (the route has no {lab_test} when creating). */
class SaveLabTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var LabTest|null $item */
        $item = $this->route('lab_test');

        return (bool) ($item === null
            ? $this->user()?->can('create', LabTest::class)
            : $this->user()?->can('update', $item));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var LabTest|null $item */
        $item = $this->route('lab_test');

        return [
            'name' => ['required', 'string', 'max:255',
                Rule::unique('lab_tests', 'name')->whereNull('deleted_at')->ignore($item?->id)],
            'category' => ['nullable', 'string', 'max:100'],
            'default_timing_note' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.unique' => 'A lab test with this name already exists.'];
    }
}
