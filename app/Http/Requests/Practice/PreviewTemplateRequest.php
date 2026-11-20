<?php

namespace App\Http\Requests\Practice;

use App\Domain\Document\Support\PrintLocale;
use App\Domain\Document\Support\PrintOptions;
use App\Domain\Practice\PrintMode;
use Illuminate\Validation\Rule;

/**
 * The template editor's unsaved form state, for the live preview. Same rules and
 * section ordering as saving (so the preview matches what gets saved), minus the
 * fields that don't change the page (name, code, default flag), plus the print
 * options picked above the preview.
 */
class PreviewTemplateRequest extends SaveTemplateRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['code'], $rules['name'], $rules['is_default']);

        return [
            ...$rules,
            'lang' => ['nullable', Rule::in(PrintLocale::values())],
            'mode' => ['nullable', Rule::in(PrintMode::values())],
            'bn_digits' => ['nullable', 'boolean'],
        ];
    }

    public function options(): PrintOptions
    {
        return PrintOptions::fromArray($this->only(['lang', 'mode', 'bn_digits']), PrintMode::from($this->validated('default_print_mode')));
    }
}
