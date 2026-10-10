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
        // The Blade form sends `specialty_ids[<id>]=0|1` (hidden 0 + checkbox 1) so un-ticking all
        // still clears them; the API sends a plain list `[1, 2]`. Ids start at 1, so a map is never a list.
        $ids = $this->input('specialty_ids');
        if (is_array($ids) && ! array_is_list($ids)) {
            $this->merge(['specialty_ids' => array_map('intval', array_keys(array_filter(
                $ids,
                fn ($ticked) => filter_var($ticked, FILTER_VALIDATE_BOOLEAN),
            )))]);
        }

        // Typed specialties: one comma-separated box on the form, a list from the API.
        $new = $this->input('new_specialties');
        if ($this->has('new_specialties') && (is_string($new) || $new === null)) {
            $this->merge(['new_specialties' => array_values(array_filter(
                array_map(fn ($name) => trim($name), explode(',', (string) $new)),
                fn ($name) => $name !== '',
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
            'specialty_ids' => ['sometimes', 'array', 'max:30'],
            'specialty_ids.*' => ['integer', 'distinct', Rule::exists('specialties', 'id')->where('is_active', true)],
            // Names not in the list yet; each joins the shared list (or matches an existing entry).
            'new_specialties' => ['sometimes', 'array', 'max:5'],
            'new_specialties.*' => ['string', 'min:2', 'max:100'],
            'default_template_id' => ['nullable', 'integer', Rule::exists('prescription_templates', 'id')],
            'credentials' => ['sometimes', 'array', 'max:20'],
            'credentials.*.text_en' => ['required', 'string', 'max:255'],
            'credentials.*.text_bn' => ['nullable', 'string', 'max:255'],
            // Hours and fees for chambers the admin assigned; assigning itself is admin-only.
            'chambers' => ['sometimes', 'array', 'max:20'],
            'chambers.*.chamber_id' => ['required', 'integer', 'distinct'],
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
