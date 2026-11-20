<?php

namespace App\Domain\Finance;

use App\Support\Concerns\HasTranslatedLabel;
use App\Support\Contracts\HasLabel;

/** Derived from the totals and active payments (spec §8.2), never set by hand; `voided` is set by VoidInvoiceAction. */
enum InvoiceStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Voided = 'voided';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
