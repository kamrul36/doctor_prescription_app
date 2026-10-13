<?php

namespace App\Domain\Clinical;

use App\Support\Concerns\HasTranslatedLabel;
use App\Support\Contracts\HasLabel;

/** What one dose slot counts (printed after the M+N+E pattern). Bangla to be checked by a native speaker. */
enum DoseUnit: string implements HasLabel
{
    use HasTranslatedLabel;

    case Tablet = 'tablet';
    case Capsule = 'capsule';
    case Ml = 'ml';
    case Spoon = 'spoon';
    case Drop = 'drop';
    case Piece = 'piece';
    case Puff = 'puff';
    case Sachet = 'sachet';
    case Injection = 'injection';
    case Application = 'application';

    public function labelBn(): string
    {
        return $this->label('bn');
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
