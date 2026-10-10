<?php

namespace App\Domain\Practice;

/**
 * Built-in prescription blocks a specialty can bring to the pad. The list of
 * specialties itself is data (`specialties`, managed by the admin); a
 * specialty with a block shows that block in the `specialty` section, one
 * without (e.g. typed by a doctor) only affects catalog suggestions.
 *
 * A new block = a new case here plus its schema in App\Domain\Clinical\Specialty.
 */
enum SpecialtyBlock: string
{
    case Gynae = 'gynae';
    case Dental = 'dental';

    public function label(): string
    {
        return match ($this) {
            self::Gynae => 'Menstrual & obstetric history',
            self::Dental => 'Dental findings',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
