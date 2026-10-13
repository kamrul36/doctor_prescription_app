<?php

namespace App\Domain\Clinical;

use App\Support\Concerns\HasTranslatedLabel;
use App\Support\Contracts\HasLabel;

/** A visit is written as a draft, then finalized (read-only, numbered) or cancelled. */
enum VisitStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';
}
