<?php

namespace App\Domain\Practice;

use App\Support\Concerns\HasTranslatedLabel;
use App\Support\Contracts\HasLabel;

enum TemplateLayout: string implements HasLabel
{
    use HasTranslatedLabel;

    case SidebarLeft = 'sidebar_left';
    case SingleColumn = 'single_column';
    case TwoColumn = 'two_column';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
