<?php

namespace App\Domain\Clinical;

/** When to take a medicine relative to meals (optional). */
enum DoseTiming: string
{
    case BeforeMeal = 'before_meal';
    case AfterMeal = 'after_meal';
    case Any = 'any';

    public function label(): string
    {
        return match ($this) {
            self::BeforeMeal => 'Before meal',
            self::AfterMeal => 'After meal',
            self::Any => 'Any time',
        };
    }

    public function labelBn(): string
    {
        return match ($this) {
            self::BeforeMeal => 'খাবারের আগে',
            self::AfterMeal => 'খাবারের পরে',
            self::Any => 'যেকোনো সময়',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
