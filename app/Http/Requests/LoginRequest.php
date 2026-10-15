<?php

namespace App\Http\Requests;

use App\Exceptions\LoginLockedOut;
use App\Support\Phone;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Shared by the web (session) and API (JWT) login. `login` is the user's email
 * or phone: a value with `@` is an email, anything else a phone (separators
 * allowed). The API also accepts the older `email` key.
 */
class LoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('login') && $this->filled('email')) {
            $this->merge(['login' => $this->input('email')]);
        }
    }

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
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['login' => __('Email or phone')];
    }

    /**
     * Which column to look the user up by, and the value to look for.
     *
     * @return array{field: 'email'|'phone', value: string, password: string}
     */
    public function credentials(): array
    {
        $login = trim((string) $this->string('login'));
        $isEmail = str_contains($login, '@');

        return [
            'field' => $isEmail ? 'email' : 'phone',
            'value' => $isEmail ? $login : (string) Phone::normalize($login),
            'password' => (string) $this->string('password'),
        ];
    }

    /**
     * Rejects the attempt with 429 once the login + IP pair, or the IP on
     * its own, has used up its failed attempts. The IP-only limit is looser
     * and stops one address spraying a common password across many accounts.
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
     * Clears only the login + IP count. The IP count is left to expire, or
     * an attacker could reset it by logging into an account of their own.
     */
    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->credentials()['value']).'|'.$this->ip());
    }

    public function ipThrottleKey(): string
    {
        return 'login-ip|'.$this->ip();
    }
}
