<?php

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

/** An expense category; names are unique ignoring case (checked by the Action). */
class SaveExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manageCategories', Expense::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'min:2', 'max:100'],
            'name_bn' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name_en' => __('name'), 'name_bn' => __('Bangla name')];
    }
}
