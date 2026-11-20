<?php

namespace App\Domain\Finance;

use App\Support\Concerns\HasTranslatedLabel;
use App\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    use HasTranslatedLabel;

    case Cash = 'cash';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Card = 'card';
    case Bank = 'bank';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
