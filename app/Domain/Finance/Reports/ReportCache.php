<?php

namespace App\Domain\Finance\Reports;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cache for reports of closed periods (spec §8.4: "closed past months are
 * cached; today is live"). Keys carry a version that every money change
 * replaces (InvoiceTotals and the expense Actions call flush()), so a late
 * void or a back-dated expense shows at once. The only place report cache
 * keys are built (rule A3).
 */
final class ReportCache
{
    private const VERSION_KEY = 'reports:version';

    private const TTL_SECONDS = 86400;

    /**
     * @template T
     *
     * @param  array<string, scalar|null>  $parts
     * @param  Closure(): T  $compute
     * @return T
     */
    public static function remember(string $report, array $parts, Closure $compute): mixed
    {
        $key = 'reports:'.self::version().":{$report}:".sha1(json_encode($parts, JSON_THROW_ON_ERROR));

        return Cache::remember($key, self::TTL_SECONDS, $compute);
    }

    /**
     * After the surrounding transaction commits (at once when there is none): flushing
     * earlier would let a report built from the old rows be cached under the new version.
     */
    public static function flush(): void
    {
        DB::afterCommit(fn () => Cache::forever(self::VERSION_KEY, (string) Str::uuid()));
    }

    private static function version(): string
    {
        $version = Cache::get(self::VERSION_KEY);
        if (! is_string($version)) {
            $version = (string) Str::uuid();
            Cache::forever(self::VERSION_KEY, $version);
        }

        return $version;
    }
}
