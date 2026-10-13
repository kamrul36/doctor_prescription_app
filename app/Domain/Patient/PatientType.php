<?php

namespace App\Domain\Patient;

use App\Support\Concerns\HasTranslatedLabel;
use App\Support\Contracts\HasLabel;

enum PatientType: string implements HasLabel
{
    use HasTranslatedLabel;

    case General = 'general';
    case Free = 'free';
    case Staff = 'staff';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
