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

    public function label(int $value = 2): string
    {
        return match ($this) {
            self::Day => $value === 1 ? 'day' : 'days',
            self::Week => $value === 1 ? 'week' : 'weeks',
            self::Month => $value === 1 ? 'month' : 'months',
            self::Continuous => 'continue',
            self::AsNeeded => 'as needed',
        };
    }

    public function labelBn(): string
    {
        return match ($this) {
            self::Day => 'দিন',
            self::Week => 'সপ্তাহ',
            self::Month => 'মাস',
            self::Continuous => 'চলবে',
            self::AsNeeded => 'প্রয়োজনে',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
