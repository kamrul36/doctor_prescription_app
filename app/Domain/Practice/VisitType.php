<?php

namespace App\Domain\Practice;

enum VisitType: string
{
    case New = 'new';
    case FollowUp = 'follow_up';
    case ReportShow = 'report_show';
    case Free = 'free';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New patient',
            self::FollowUp => 'Follow-up',
            self::ReportShow => 'Report show',
            self::Free => 'Free',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
