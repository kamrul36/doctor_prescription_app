<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends access for users deactivated after they logged in: the session is
 * closed (web) or the token rejected (API) on their next request. Sessions
 * and tokens from before a deactivation stay dead after reactivation, as
 * their token version no longer matches the user's.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $guard = Auth::guard();

        if (! $user->is_active) {
            $this->endSession($request, $guard);

            throw new AuthenticationException('Your account has been deactivated.');
        }

        if ($this->tokenVersion($request, $guard, $user) !== $user->token_version) {
            $this->endSession($request, $guard);

            throw new AuthenticationException('Your session has been revoked. Please log in again.');
        }

        return $next($request);
    }

    /**
     * The token version the request was authenticated with. Tokens and
     * sessions issued before versioning existed count as version 0. A
     * session without one (e.g. just restored from a remember-me cookie,
     * which deactivation rotates) is stamped with the current version.
     */
    private function tokenVersion(Request $request, mixed $guard, User $user): int
    {
        if ($guard instanceof JWTGuard) {
            return (int) $guard->payload()->get(User::TOKEN_VERSION_CLAIM);
        }

        if ($guard instanceof SessionGuard) {
            $session = $request->session();
            if (! $session->has(User::TOKEN_VERSION_SESSION_KEY)) {
                $session->put(User::TOKEN_VERSION_SESSION_KEY, $user->token_version);
            }

            return (int) $session->get(User::TOKEN_VERSION_SESSION_KEY);
        }

        return $user->token_version;
    }

    private function endSession(Request $request, mixed $guard): void
    {
        if ($guard instanceof SessionGuard) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
