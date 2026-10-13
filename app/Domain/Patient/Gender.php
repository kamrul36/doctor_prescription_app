<?php

namespace App\Domain\Patient;

use App\Support\Concerns\HasTranslatedLabel;
use App\Support\Contracts\HasLabel;

enum Gender: string implements HasLabel
{
    use HasTranslatedLabel;

    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
