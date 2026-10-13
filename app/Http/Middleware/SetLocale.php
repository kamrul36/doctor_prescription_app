<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the UI language for the request (spec §6).
 * Web: the user's saved choice, then the session (guests), then the app default.
 * API: the token user's saved choice, then `Accept-Language` (bn|en; browsers always send one,
 * so it only decides for guests and users who never chose), then the default.
 */
class SetLocale
{
    public const SESSION_KEY = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::resolve($request);

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    public static function resolve(Request $request): string
    {
        $candidates = $request->is('api/*')
            ? [$request->user('api')?->locale, self::fromHeader($request)]
            : [$request->user()?->locale, $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null];

        foreach ($candidates as $candidate) {
            if (self::supported($candidate)) {
                return $candidate;
            }
        }

        return (string) config('app.locale');
    }

    public static function supported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, config('app.supported_locales'), true);
    }

    /** The first supported language in `Accept-Language` (`bn-BD` counts as `bn`). */
    private static function fromHeader(Request $request): ?string
    {
        if (! $request->headers->has('Accept-Language')) {
            return null;
        }

        foreach ($request->getLanguages() as $language) {
            $base = strtolower(strtok($language, '_-'));
            if (self::supported($base)) {
                return $base;
            }
        }

        return null;
    }
}
