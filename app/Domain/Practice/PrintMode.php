<?php

namespace App\Domain\Practice;

enum PrintMode: string
{
    /** Data only, for pre-printed pads. */
    case PadOnly = 'pad_only';

    /** Full letterhead, for PDF saving and sharing. */
    case WithLetterhead = 'with_letterhead';

    public function label(): string
    {
        return match ($this) {
            self::PadOnly => 'Pad only (pre-printed stationery)',
            self::WithLetterhead => 'With letterhead',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
