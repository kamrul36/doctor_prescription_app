<?php

namespace App\Domain\Clinical;

/** Unit for how long a complaint has lasted and for the follow-up interval. */
enum PeriodUnit: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function label(int $value = 2): string
    {
        return $value === 1 ? $this->value : $this->value.'s';
    }

    public function labelBn(): string
    {
        return match ($this) {
            self::Day => 'দিন',
            self::Week => 'সপ্তাহ',
            self::Month => 'মাস',
            self::Year => 'বছর',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
