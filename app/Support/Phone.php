<?php

namespace App\Support;

use Closure;

/**
 * One phone format for every form (spec §10.1), mirrored by the `phone` and
 * `phone-list` rules in resources/js/components/validatedForm.js.
 * Separators (spaces, dashes, dots, brackets) are removed; anything else,
 * such as letters, makes the number invalid.
 */
final class Phone
{
    /** A person's number: 6 to 15 digits, optional leading +. */
    public const RULE = 'regex:/^\+?\d{6,15}$/';

    public const MESSAGE = 'Enter a phone number of 6 to 15 digits (spaces and dashes are fine).';

    /** A local number without country code: 6 to 15 digits, nothing else. */
    public const LOCAL_RULE = 'regex:/^\d{6,15}$/';

    public const LOCAL_MESSAGE = 'Enter a phone number of 6 to 15 digits, without country code.';

    /** Keeps digits only; every other character is dropped. */
    public static function normalizeLocal(mixed $value): mixed
    {
        return is_string($value) ? (preg_replace('/\D+/', '', $value) ?: null) : $value;
    }

    public static function normalize(mixed $value): mixed
    {
        return is_string($value) ? (preg_replace('/[\s().-]/', '', $value) ?: null) : $value;
    }

    /**
     * Contact numbers of a chamber or branch: a comma-separated string or a list,
     * each 5 to 15 digits (5-digit hotlines are common). Stored as typed.
     */
    public static function contactList(int $maxNumbers = 10): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($maxNumbers): void {
            $numbers = is_array($value) ? $value : explode(',', (string) $value);
            $numbers = array_values(array_filter(array_map(fn ($n) => is_string($n) ? trim($n) : $n, $numbers), fn ($n) => $n !== '' && $n !== null));

            if (count($numbers) > $maxNumbers) {
                $fail(__('Enter at most :max numbers.', ['max' => $maxNumbers]));

                return;
            }

            foreach ($numbers as $number) {
                if (! is_string($number) || mb_strlen($number) > 32 || ! preg_match('/^\+?\d{5,15}$/', (string) self::normalize($number))) {
                    $fail(__('Enter phone numbers of 5 to 15 digits, separated by commas.'));

                    return;
                }
            }
        };
    }
}
