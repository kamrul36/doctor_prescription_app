<?php

namespace App\Domain\Practice;

use App\Support\Concerns\HasTranslatedLabel;
use App\Support\Contracts\HasLabel;

enum PrintMode: string implements HasLabel
{
    use HasTranslatedLabel;

    /** Data only, for pre-printed pads. */
    case PadOnly = 'pad_only';

    /** Full letterhead, for PDF saving and sharing. */
    case WithLetterhead = 'with_letterhead';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
