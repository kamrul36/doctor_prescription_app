<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Models\AdviceTemplate;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\PrescriptionTemplate;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create or update an advice template (the route has no {advice_template} when creating). */
class SaveAdviceTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AdviceTemplate|null $item */
        $item = $this->route('advice_template');

        return (bool) ($item === null
            ? $this->user()?->can('create', AdviceTemplate::class)
            : $this->user()?->can('update', $item));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'specialty_code' => ['required', Rule::in(array_keys(config('practice.specialties')))],
            'title' => ['required', 'string', 'max:255'],
            'text_en' => ['required', 'string', 'max:5000'],
            'text_bn' => ['nullable', 'string', 'max:5000'],
            'is_default_for_template_id' => ['nullable', 'integer', $this->defaultTemplateRule()],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The prescription template must exist, be active, match the specialty and be
     * shared or belong to the same doctor who owns this advice; and an inactive
     * advice cannot be a default.
     */
    private function defaultTemplateRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $template = PrescriptionTemplate::query()->find($value);

            if ($template === null || ! $template->is_active) {
                $fail('Choose an active prescription template.');

                return;
            }

            if ($template->specialty_code !== $this->input('specialty_code')) {
                $fail('The prescription template belongs to a different specialty.');
            }

            if ($template->doctor_id !== null && $template->doctor_id !== $this->ownerDoctorId()) {
                $fail('That prescription template belongs to another doctor.');
            }

            if ($this->has('is_active') && ! $this->boolean('is_active')) {
                $fail('An inactive advice template cannot be a default.');
            }
        };
    }

    private function ownerDoctorId(): ?int
    {
        /** @var AdviceTemplate|null $item */
        $item = $this->route('advice_template');

        if ($item !== null) {
            return $item->doctor_id;
        }

        $id = Doctor::query()->where('user_id', $this->user()?->id)->value('id');

        return $id === null ? null : (int) $id;
    }
}
