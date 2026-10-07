<?php

namespace App\Domain\Practice;

enum SectionZone: string
{
    case Header = 'header';
    case Left = 'left';
    case Right = 'right';
    case Footer = 'footer';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
