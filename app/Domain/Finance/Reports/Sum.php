<?php

namespace App\Domain\Finance\Reports;

use App\Domain\Finance\Money;

/** Adds Money values in PHP: never SQL SUM(), which SQLite returns as floating point (spec §8.1). */
final class Sum
{
    /** @param  iterable<Money|null>  $amounts */
    public static function of(iterable $amounts): Money
    {
        $total = Money::zero();
        foreach ($amounts as $amount) {
            if ($amount !== null) {
                $total = $total->add($amount);
            }
        }

        return $total;
    }
}
