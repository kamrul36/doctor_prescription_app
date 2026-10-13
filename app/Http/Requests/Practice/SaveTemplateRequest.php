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

    /**
     * The Blade form sends an `order` number per section; after validation the
     * sections are sorted by it and it is dropped. Sorting afterwards keeps the
     * error keys (`sections.3.order`) on the row the user typed in.
     *
     * @param  array-key|null  $key
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated();

        if (is_array($data['sections'] ?? null) && collect($data['sections'])->contains(fn ($s) => isset($s['order']))) {
            $data['sections'] = collect($data['sections'])
                ->sortBy(fn ($s) => (int) ($s['order'] ?? 0))
                ->map(fn ($s) => collect($s)->except('order')->all())
                ->values()->all();
        }

        return data_get($data, $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'name' => ['required', 'string', 'max:255'],
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
            'sections.*.order' => ['sometimes', 'nullable', 'integer', 'between:0,999'],
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
            'code.regex' => __('Use lowercase letters, digits and underscores, starting with a letter.'),
            'sections.*.order.*' => __('The order is a whole number from 0 to 999.'),
            'margins.*.*' => __('A margin is a whole number of mm from 0 to 100.'),
        ];
    }
}
