<?php

namespace App\Domain\Clinical;

/** How long a medicine is taken: a number of days/weeks/months, or open-ended. */
enum DurationUnit: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Continuous = 'continuous';
    case AsNeeded = 'as_needed';

    /** Day/week/month need a number; continuous and as-needed must not have one. */
    public function needsValue(): bool
    {
        return in_array($this, [self::Day, self::Week, self::Month], true);
    }

    /** Singular for 1, plural otherwise (`lang/{locale}/enums.php`). */
    public function label(int $value = 2, ?string $locale = null): string
    {
        return trans_choice('enums.duration_unit.'.$this->value, $value, [], $locale);
    }

    public function labelBn(): string
    {
        return $this->label(2, 'bn');
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
