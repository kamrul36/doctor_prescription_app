<?php

namespace App\Domain\Finance;

use App\Support\Concerns\HasTranslatedLabel;
use App\Support\Contracts\HasLabel;

enum InvoiceLineKind: string implements HasLabel
{
    use HasTranslatedLabel;

    case Consultation = 'consultation';
    case Procedure = 'procedure';
    case Test = 'test';
    case Other = 'other';
}
