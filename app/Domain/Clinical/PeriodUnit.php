<?php

namespace App\Domain\Clinical;

/** Unit for how long a complaint has lasted and for the follow-up interval. */
enum PeriodUnit: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /** Singular for 1, plural otherwise (`lang/{locale}/enums.php`). */
    public function label(int $value = 2, ?string $locale = null): string
    {
        return trans_choice('enums.period_unit.'.$this->value, $value, [], $locale);
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
