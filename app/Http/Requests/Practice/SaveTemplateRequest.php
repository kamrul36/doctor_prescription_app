<?php

namespace App\Http\Requests\Practice;

use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\PrintMode;
use App\Domain\Practice\SectionKey;
use App\Domain\Practice\SectionZone;
use App\Domain\Practice\TemplateLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create or update a template (the route has no {template} when creating). */
class SaveTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', PrescriptionTemplate::class);
    }

    /** The Blade form sends an `order` number per section; sort by it, then drop it. */
    protected function prepareForValidation(): void
    {
        $sections = $this->input('sections');

        if (is_array($sections) && collect($sections)->contains(fn ($s) => is_array($s) && isset($s['order']))) {
            $sorted = collect($sections)->filter(fn ($s) => is_array($s))->sortBy(fn ($s) => (int) ($s['order'] ?? 0))->values();
            $this->merge(['sections' => $sorted->map(fn ($s) => collect($s)->except('order')->all())->all()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'name' => ['required', 'string', 'max:255'],
            'specialty_code' => ['required', Rule::in(array_keys(config('practice.specialties')))],
            'paper_size' => ['required', Rule::in(['A4', 'A5'])],
            'default_print_mode' => ['required', Rule::in(PrintMode::values())],
            'layout' => ['required', Rule::in(TemplateLayout::values())],
            'margins' => ['nullable', 'array:top,right,bottom,left'],
            'margins.*' => ['nullable', 'integer', 'min:0', 'max:100'],
            'show_barcode' => ['boolean'],
            'barcode_source' => ['required', Rule::in(['prescription_no', 'patient_code'])],
            'show_branch_footer' => ['boolean'],
            'show_visiting_hours' => ['boolean'],
            'show_signature' => ['boolean'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'sections' => ['required', 'array', 'min:1', 'max:'.count(SectionKey::cases())],
            'sections.*.section_key' => ['required', 'distinct', Rule::in(SectionKey::values())],
            'sections.*.zone' => ['required', Rule::in(SectionZone::values())],
            'sections.*.label_en' => ['nullable', 'string', 'max:255'],
            'sections.*.label_bn' => ['nullable', 'string', 'max:255'],
            'sections.*.is_visible' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use lowercase letters, digits and underscores, starting with a letter.',
        ];
    }
}
