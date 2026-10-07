<?php

namespace App\Http\Requests;

use App\Exceptions\LoginLockedOut;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Shared by the web (session) and API (JWT) login. */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /** @return array{email: string, password: string} */
    public function credentials(): array
    {
        return [
            'email' => (string) $this->string('email'),
            'password' => (string) $this->string('password'),
        ];
    }

    /**
     * Rejects the attempt with 429 once the email + IP pair, or the IP on
     * its own, has used up its failed attempts. The IP-only limit is looser
     * and stops one address spraying a common password across many emails.
     */
    public function ensureIsNotRateLimited(): void
    {
        $limits = [
            $this->throttleKey() => (int) config('access.login_max_attempts'),
            $this->ipThrottleKey() => (int) config('access.login_ip_max_attempts'),
        ];

        $seconds = 0;
        foreach ($limits as $key => $maxAttempts) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $seconds = max($seconds, RateLimiter::availableIn($key));
            }
        }

        if ($seconds === 0) {
            return;
        }

        event(new Lockout($this));

        throw LoginLockedOut::after(
            $seconds,
            trans('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]),
        );
    }

    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), (int) config('access.login_decay_seconds'));
        RateLimiter::hit($this->ipThrottleKey(), (int) config('access.login_ip_decay_seconds'));
    }

    /**
     * Clears only the email + IP count. The IP count is left to expire, or
     * an attacker could reset it by logging into an account of their own.
     */
    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->string('email')).'|'.$this->ip());
    }

    public function ipThrottleKey(): string
    {
        return 'login-ip|'.$this->ip();
    }
}
