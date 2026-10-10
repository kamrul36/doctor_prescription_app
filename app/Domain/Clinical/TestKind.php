<?php

namespace App\Domain\Clinical;

/** An investigation advised at this visit, or an earlier result reviewed. */
enum TestKind: string
{
    case Advised = 'advised';
    case Reviewed = 'reviewed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
