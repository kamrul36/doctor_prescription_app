<?php

namespace App\Domain\Patient;

enum PatientType: string
{
    case General = 'general';
    case Free = 'free';
    case Staff = 'staff';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
