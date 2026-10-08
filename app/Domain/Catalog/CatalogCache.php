<?php

namespace App\Domain\Catalog;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Short-lived cache for typeahead results. Keys carry a version that every
 * catalog write replaces, so an edit or deactivation shows up on the next
 * search instead of after the TTL. A result computed just before a write is
 * stored under the old version and is never read again.
 */
class CatalogCache
{
    private const VERSION_KEY = 'catalog:version';

    private const TTL_SECONDS = 60;

    /**
     * @template T
     *
     * @param  array<string, scalar|null>  $parts  everything the result depends on
     * @param  Closure(): T  $compute
     * @return T
     */
    public function remember(string $kind, array $parts, Closure $compute): mixed
    {
        $version = $this->version();
        $key = "catalog:{$version}:{$kind}:".sha1(json_encode($parts, JSON_THROW_ON_ERROR));

        return Cache::remember($key, self::TTL_SECONDS, $compute);
    }

    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::uuid());
    }

    private function version(): string
    {
        $version = Cache::get(self::VERSION_KEY);

        if (! is_string($version)) {
            $version = (string) Str::uuid();
            Cache::forever(self::VERSION_KEY, $version);
        }

        return $version;
    }
}
