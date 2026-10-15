<?php

namespace App\Http\Requests\Patient;

use App\Domain\Patient\BloodGroup;
use App\Domain\Patient\Gender;
use App\Domain\Patient\Models\Patient;
use App\Domain\Patient\PatientType;
use App\Support\Phone;
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

    /**
     * Phones are stored as digits (and a leading +) so a typed fragment always matches.
     * Only separators are removed; letters stay, so the format rule rejects them.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['phone', 'alt_phone'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $merge[$field] = Phone::normalize($value);
            }
        }

        // The Blade form posts blanks for whichever of dob/age was left empty.
        foreach (['dob', 'age_years'] as $field) {
            if ($this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        // Whose phone it is: the relation only applies to a phone that is not the patient's own.
        // The checkbox posts 0/1; an API call that leaves it out keeps the stored value on update.
        if ($this->has('phone_is_personal')) {
            $merge['phone_is_personal'] = $this->boolean('phone_is_personal');
        }

        $personal = (bool) ($merge['phone_is_personal'] ?? $this->route('patient')?->phone_is_personal ?? true);
        $phone = $merge['phone'] ?? $this->input('phone');
        $merge['phone_is_personal'] = $personal || blank($phone);

        if ($merge['phone_is_personal']) {
            $merge['phone_relation'] = null;
        } elseif (is_string($this->input('phone_relation'))) {
            $merge['phone_relation'] = trim($this->input('phone_relation'));
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
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'age_years' => ['nullable', 'integer', 'between:0,130', 'required_without:dob'],
            'gender' => ['required', Rule::in(Gender::values())],
            'phone' => ['required', 'string', Phone::RULE],
            'alt_phone' => ['nullable', 'string', Phone::RULE],
            'phone_is_personal' => ['required', 'boolean'],
            'phone_relation' => [Rule::requiredIf(fn () => ! $this->boolean('phone_is_personal')), 'nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:1000'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'blood_group' => ['nullable', Rule::in(BloodGroup::values())],
            'patient_type' => ['sometimes', Rule::in(PatientType::values())],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'conditions' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => __(Phone::MESSAGE),
            'alt_phone.regex' => __(Phone::MESSAGE),
            'dob.before_or_equal' => __('The date of birth cannot be in the future.'),
            'age_years.required_without' => __('Enter the age.'),
            'phone_relation.required' => __('Enter how this person is related to the patient.'),
        ];
    }
}
