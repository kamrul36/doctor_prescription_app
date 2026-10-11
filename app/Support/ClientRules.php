<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Turns a field's Laravel rules into the attributes the Alpine `validatedForm`
 * component reads (spec §10.1), so rules kept in PHP (Vitals, specialty
 * schemas) are checked in the browser too. Only simple string rules are
 * mapped; the server still checks everything.
 */
final class ClientRules
{
    /** @param  list<mixed>  $rules */
    public static function attributes(array $rules): HtmlString
    {
        $attributes = [];

        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);

            match ($name) {
                'required' => $attributes['required'] = null,
                'integer' => $attributes['data-rule'] = 'integer',
                'numeric' => $attributes['data-rule'] ??= 'decimal',
                'before_or_equal' => $argument === 'today' ? $attributes['data-rule'] = 'past-date' : null,
                'after_or_equal' => $argument === 'today' ? $attributes['data-rule'] = 'future-date' : null,
                default => null,
            };

            if ($name === 'between' && $argument !== null) {
                [$attributes['data-min'], $attributes['data-max']] = explode(',', $argument, 2);
            } elseif (in_array($name, ['min', 'max'], true) && $argument !== null) {
                $attributes[$name] = $argument;
            }
        }

        // min/max mean a length for text and a value for numbers.
        $numeric = in_array($attributes['data-rule'] ?? null, ['integer', 'decimal'], true);
        foreach (['min', 'max'] as $bound) {
            if (isset($attributes[$bound])) {
                $attributes[$numeric ? "data-{$bound}" : "{$bound}length"] = $attributes[$bound];
                unset($attributes[$bound]);
            }
        }

        return new HtmlString(collect($attributes)
            ->map(fn (?string $value, string $key) => $value === null ? $key : $key.'="'.e($value).'"')
            ->implode(' '));
    }
}
