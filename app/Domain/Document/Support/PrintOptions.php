<?php

namespace App\Domain\Document\Support;

use App\Domain\Practice\PrintMode;

/**
 * How one print is made: language, pad-only or letterhead, Bangla digits.
 * Built from the validated `lang`, `mode`, `bn_digits` query (web and API).
 */
final readonly class PrintOptions
{
    public function __construct(
        public PrintLocale $locale,
        public PrintMode $mode,
        public bool $banglaDigits,
    ) {}

    /**
     * Missing values: the UI language, the template's print mode, and Bangla digits on
     * whenever Bangla is printed (spec §15 open item; the doctor can untick it).
     *
     * @param  array{lang?: string|null, mode?: string|null, bn_digits?: mixed}  $input
     */
    public static function fromArray(array $input, PrintMode $defaultMode = PrintMode::WithLetterhead): self
    {
        $locale = PrintLocale::tryFrom((string) ($input['lang'] ?? '')) ?? PrintLocale::forUi();
        $mode = PrintMode::tryFrom((string) ($input['mode'] ?? '')) ?? $defaultMode;
        $digits = array_key_exists('bn_digits', $input) && $input['bn_digits'] !== null
            ? filter_var($input['bn_digits'], FILTER_VALIDATE_BOOLEAN)
            : $locale !== PrintLocale::En;

        return new self($locale, $mode, $digits);
    }

    /** @return array{lang: string, mode: string, bn_digits: int} */
    public function toQuery(): array
    {
        return ['lang' => $this->locale->value, 'mode' => $this->mode->value, 'bn_digits' => (int) $this->banglaDigits];
    }

    /** @param  array<string, mixed>  $changes */
    public function with(array $changes): self
    {
        return self::fromArray([...$this->toQuery(), ...$changes], $this->mode);
    }
}
