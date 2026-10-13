<?php

namespace App\Domain\Clinical;

use App\Support\Concerns\HasTranslatedLabel;
use App\Support\Contracts\HasLabel;

/** When to take a medicine relative to meals (optional). */
enum DoseTiming: string implements HasLabel
{
    use HasTranslatedLabel;

    case BeforeMeal = 'before_meal';
    case AfterMeal = 'after_meal';
    case Any = 'any';

    public function labelBn(): string
    {
        return $this->label('bn');
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
