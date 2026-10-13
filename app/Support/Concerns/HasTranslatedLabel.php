<?php

namespace App\Support\Concerns;

use Illuminate\Support\Str;

/**
 * Enum labels from `lang/{locale}/enums.php`, keyed by the enum's snake-cased
 * name and the case value (`enums.dose_unit.tablet`). Without a locale the
 * current UI language is used; prints pass their own (spec §6).
 */
trait HasTranslatedLabel
{
    public function label(?string $locale = null): string
    {
        return __(self::labelKey($this->value), [], $locale);
    }

    private static function labelKey(string $value): string
    {
        return 'enums.'.Str::snake(class_basename(self::class)).'.'.$value;
    }
}
