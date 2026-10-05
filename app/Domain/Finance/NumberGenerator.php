<?php

namespace App\Domain\Finance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free yearly sequences backed by a row-locked counter.
 *
 * Call inside the caller's transaction when the number must roll back with it.
 */
class NumberGenerator
{
    public const PATIENT = 'patient';

    public const PRESCRIPTION = 'prescription';

    public const INVOICE = 'invoice';

    public const RECEIPT = 'receipt';

    private const FORMATS = [
        self::PATIENT => ['prefix' => '', 'separator' => '', 'short_year' => true],
        self::PRESCRIPTION => ['prefix' => 'RX', 'separator' => '-', 'short_year' => false],
        self::INVOICE => ['prefix' => 'INV', 'separator' => '-', 'short_year' => false],
        self::RECEIPT => ['prefix' => 'RC', 'separator' => '-', 'short_year' => false],
    ];

    private const PADDING = 6;

    public function next(string $key, ?Carbon $at = null): string
    {
        $format = self::FORMATS[$key] ?? throw new \InvalidArgumentException("Unknown sequence '{$key}'.");
        $year = ($at ?? now())->year;

        $value = DB::transaction(function () use ($key, $year) {
            DB::table('sequence_counters')->insertOrIgnore([
                'key' => $key,
                'year' => $year,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('sequence_counters')
                ->where('key', $key)->where('year', $year)
                ->lockForUpdate()->first();

            $next = $row->last_value + 1;

            DB::table('sequence_counters')
                ->where('id', $row->id)
                ->update(['last_value' => $next, 'updated_at' => now()]);

            return $next;
        });

        $yearPart = $format['short_year'] ? substr((string) $year, -2) : (string) $year;
        $serial = str_pad((string) $value, self::PADDING, '0', STR_PAD_LEFT);

        return $format['prefix'].$format['separator'].$yearPart.($format['short_year'] ? '' : $format['separator']).$serial;
    }
}
