<?php

namespace App\Http\Requests\Patient;

use App\Domain\Patient\BloodGroup;
use App\Domain\Patient\Gender;
use App\Domain\Patient\Models\Patient;
use App\Domain\Patient\PatientType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Patient|null $patient */
        $patient = $this->route('patient');

        return (bool) ($patient === null
            ? $this->user()?->can('create', Patient::class)
            : $this->user()?->can('update', $patient));
    }

    /** Phones are stored as digits (and a leading +) so a typed fragment always matches. */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['phone', 'alt_phone'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $merge[$field] = preg_replace('/(?!^\+)[^\d]/', '', trim($value)) ?: null;
            }
        }

        // The Blade form posts blanks for whichever of dob/age was left empty.
        foreach (['dob', 'age_years'] as $field) {
            if ($this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before_or_equal:today', 'required_without:age_years'],
            'age_years' => ['nullable', 'integer', 'between:0,130', 'required_without:dob'],
            'gender' => ['required', Rule::in(Gender::values())],
            'phone' => ['required', 'string', 'regex:/^\+?\d{6,15}$/'],
            'alt_phone' => ['nullable', 'string', 'regex:/^\+?\d{6,15}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'blood_group' => ['nullable', Rule::in(BloodGroup::values())],
            'patient_type' => ['sometimes', Rule::in(PatientType::values())],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'conditions' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
