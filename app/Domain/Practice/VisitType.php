<?php

namespace App\Domain\Practice;

use App\Support\Concerns\HasTranslatedLabel;
use App\Support\Contracts\HasLabel;

enum VisitType: string implements HasLabel
{
    use HasTranslatedLabel;

    case New = 'new';
    case FollowUp = 'follow_up';
    case ReportShow = 'report_show';
    case Free = 'free';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
