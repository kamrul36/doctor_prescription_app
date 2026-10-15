<?php

namespace App\Domain\Access\Actions;

use App\Domain\Audit\AuditLogger;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Checks login credentials (email or phone + password) for both the web and
 * the API login. The caller starts the session or issues the token.
 */
class AuthenticateAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @throws ValidationException */
    public function handle(LoginRequest $request, string $guard): User
    {
        $request->ensureIsNotRateLimited();

        $credentials = $request->credentials();
        $user = $credentials['value'] === ''
            ? null
            : User::query()->where($credentials['field'], $credentials['value'])->first();

        // Hash once on every path, so the response time does not reveal
        // whether the email or phone exists or the account is inactive.
        if ($user === null) {
            Hash::make($credentials['password']);
        }
        $valid = $user !== null && Auth::guard('web')->getProvider()->validateCredentials($user, ['password' => $credentials['password']]);

        // Inactive accounts get the same message as a wrong password.
        if (! $user || ! $valid || ! $user->is_active) {
            $request->hitRateLimiter();
            // No actor: nobody is authenticated. The account is the subject.
            $this->audit->log('auth.login_failed', $user, ['login' => $credentials['value'], 'guard' => $guard]);

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ])->status(401);
        }

        $request->clearRateLimiter();
        $this->audit->log('auth.login', $user, ['guard' => $guard], $user->id);

        return $user;
    }
}
