<?php

namespace App\Http\Requests\Practice;

use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\VisitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDoctorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Doctor::class);
    }

    /** The Blade form always sends spare rows; rows left empty are dropped. */
    protected function prepareForValidation(): void
    {
        // The Blade form lists every chamber with an `enabled` checkbox; unticked ones are dropped.
        if (is_array($this->input('chambers'))) {
            $this->merge(['chambers' => array_values(array_filter(
                $this->input('chambers'),
                fn ($row) => ! is_array($row) || ! array_key_exists('enabled', $row) || filter_var($row['enabled'], FILTER_VALIDATE_BOOLEAN),
            ))]);
        }

        if (is_array($this->input('credentials'))) {
            $this->merge(['credentials' => array_values(array_filter(
                $this->input('credentials'),
                fn ($row) => is_array($row) && (filled($row['text_en'] ?? null) || filled($row['text_bn'] ?? null)),
            ))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name_en' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'designation_en' => ['nullable', 'string', 'max:255'],
            'designation_bn' => ['nullable', 'string', 'max:255'],
            'reg_label' => ['required', 'string', 'max:32'],
            'reg_no' => ['nullable', 'string', 'max:64'],
            'specialty_code' => ['required', Rule::in(array_keys(config('practice.specialties')))],
            'default_template_id' => ['nullable', 'integer', Rule::exists('prescription_templates', 'id')],
            'credentials' => ['sometimes', 'array', 'max:20'],
            'credentials.*.text_en' => ['required', 'string', 'max:255'],
            'credentials.*.text_bn' => ['nullable', 'string', 'max:255'],
            'chambers' => ['sometimes', 'array', 'max:10'],
            'chambers.*.chamber_id' => ['required', 'integer', 'distinct', Rule::exists('chambers', 'id')],
            'chambers.*.visiting_hours_en' => ['nullable', 'string', 'max:1000'],
            'chambers.*.visiting_hours_bn' => ['nullable', 'string', 'max:1000'],
            'chambers.*.fees' => ['nullable', 'array'],
        ];

        foreach (VisitType::values() as $type) {
            $rules["chambers.*.fees.{$type}"] = ['nullable', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'];
        }

        return $rules;
    }
}
