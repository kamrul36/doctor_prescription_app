<?php

namespace App\Http\Requests\Practice;

use App\Domain\Practice\Models\Specialty;
use App\Domain\Practice\SpecialtyBlock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create or update a specialty (the route has no {specialty} when creating). */
class SaveSpecialtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Specialty::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim((string) preg_replace('/\s+/u', ' ', $this->input('name')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Specialty|null $specialty */
        $specialty = $this->route('specialty');

        return [
            // Unique ignoring case on every database (MySQL's collation would, SQLite's would not).
            'name' => ['required', 'string', 'min:2', 'max:100', function (string $attribute, mixed $value, \Closure $fail) use ($specialty) {
                $taken = Specialty::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)])
                    ->when($specialty, fn ($q) => $q->whereKeyNot($specialty->id))
                    ->exists();
                if ($taken) {
                    $fail('This specialty is already in the list.');
                }
            }],
            // Optional built-in prescription block (menstrual history, dental findings).
            'block' => ['nullable', Rule::enum(SpecialtyBlock::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
