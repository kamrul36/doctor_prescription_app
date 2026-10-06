<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * Too many failed logins. Still a ValidationException, so the web login
 * redirects back with the message; the API renders 429 + Retry-After.
 */
class LoginLockedOut extends ValidationException
{
    public int $retryAfter = 0;

    public static function after(int $seconds, string $message): self
    {
        $e = self::withMessages(['email' => $message]);
        $e->status = 429;
        $e->retryAfter = $seconds;

        return $e;
    }
}
