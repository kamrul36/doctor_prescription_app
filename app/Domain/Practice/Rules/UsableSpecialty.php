<?php

namespace App\Domain\Practice\Rules;

use App\Domain\Practice\Models\Specialty;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A specialty id that exists and is active. The id an item already has is
 * accepted even after the admin deactivated it, so editing the item's other
 * fields keeps working.
 */
class UsableSpecialty implements ValidationRule
{
    public function __construct(private readonly ?int $currentId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail('Choose a specialty from the list.');

            return;
        }

        $specialty = Specialty::query()->find((int) $value);

        if ($specialty === null || (! $specialty->is_active && (int) $value !== $this->currentId)) {
            $fail('Choose an active specialty from the list.');
        }
    }
}
