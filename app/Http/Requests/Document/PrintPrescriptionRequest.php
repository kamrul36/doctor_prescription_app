<?php

namespace App\Http\Requests\Document;

use App\Domain\Document\Support\PrintLocale;
use App\Domain\Document\Support\PrintOptions;
use App\Domain\Practice\PrintMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `?lang=en|bn|bilingual&mode=pad_only|with_letterhead&bn_digits=1` (spec §6, §10.2). Authorization: the controller. */
class PrintPrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /** `en`, `bn` or `bilingual`; default: the UI language. */
            'lang' => ['nullable', Rule::in(PrintLocale::values())],
            /** `pad_only` or `with_letterhead`; default: the template's print mode. */
            'mode' => ['nullable', Rule::in(PrintMode::values())],
            /** Bangla digits on Bangla lines; default on whenever Bangla is printed. */
            'bn_digits' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lang.in' => __('Choose English, Bangla or bilingual.'),
            'mode.in' => __('Choose pad only or with letterhead.'),
        ];
    }

    public function options(PrintMode $defaultMode): PrintOptions
    {
        return PrintOptions::fromArray($this->validated(), $defaultMode);
    }
}
