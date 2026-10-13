<?php

namespace App\Support;

/**
 * Picks the English or Bangla twin of a `_en` / `_bn` field pair (spec §6):
 * the asked language when it has text, otherwise the other one, so a value
 * entered in only one language is never shown blank.
 */
final class Bilingual
{
    public static function pick(?string $en, ?string $bn, ?string $locale = null): ?string
    {
        [$first, $second] = ($locale ?? app()->getLocale()) === 'bn' ? [$bn, $en] : [$en, $bn];

        return filled($first) ? $first : (filled($second) ? $second : null);
    }
}
