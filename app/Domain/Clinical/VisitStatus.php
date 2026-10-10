<?php

namespace App\Domain\Clinical;

/** A visit is written as a draft, then finalized (read-only, numbered) or cancelled. */
enum VisitStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
