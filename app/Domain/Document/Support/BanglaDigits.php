<?php

namespace App\Domain\Document\Support;

/** Western digits to Bangla digits (০-৯) for Bangla prints; other characters are kept. */
final class BanglaDigits
{
    private const MAP = ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯'];

    public static function convert(string|int $text): string
    {
        return strtr((string) $text, self::MAP);
    }
}
