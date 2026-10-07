<?php

namespace App\Domain\Practice;

enum TemplateLayout: string
{
    case SidebarLeft = 'sidebar_left';
    case SingleColumn = 'single_column';
    case TwoColumn = 'two_column';

    public function label(): string
    {
        return match ($this) {
            self::SidebarLeft => 'Left sidebar',
            self::SingleColumn => 'Single column',
            self::TwoColumn => 'Two columns',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
