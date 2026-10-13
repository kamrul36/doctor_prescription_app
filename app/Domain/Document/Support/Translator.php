<?php

namespace App\Domain\Document\Support;

use App\Domain\Clinical\DurationUnit;
use App\Domain\Clinical\PeriodUnit;
use App\Support\Bilingual;
use App\Support\Contracts\HasLabel;
use Illuminate\Support\Carbon;

/**
 * The printing rules of spec §6, in one place:
 *  - en: English labels and `_en` fields; bn: Bangla labels and `_bn` fields (+ Bangla digits);
 *  - bilingual: labels "Name / নাম", content `_en` first and `_bn` below when different;
 *  - a value missing in the chosen language falls back to the other one, never blank;
 *  - free text typed by the doctor prints as entered.
 */
final class Translator
{
    public function __construct(public readonly PrintOptions $options) {}

    public function locale(): PrintLocale
    {
        return $this->options->locale;
    }

    /** A fixed label given in both languages, e.g. a template section's `label_en` / `label_bn`. */
    public function label(?string $en, ?string $bn): string
    {
        return $this->join(array_map(fn (string $lang) => (string) Bilingual::pick($en, $bn, $lang), $this->locale()->languages()));
    }

    /** A UI string from the lang files (`__()` key = the English text), e.g. "Age". */
    public function ui(string $key, array $replace = []): string
    {
        return $this->join(array_map(fn (string $lang) => $this->digits(__($key, $replace, $lang), $lang), $this->locale()->languages()));
    }

    /** An enum label, e.g. the sex or a dose unit. */
    public function enum(HasLabel $enum): string
    {
        return $this->join(array_map(fn (string $lang) => $enum->label($lang), $this->locale()->languages()));
    }

    /** A duration or period unit, singular for 1 ("day" / "days"). */
    public function unit(DurationUnit|PeriodUnit $unit, int $count): string
    {
        return $this->join(array_map(fn (string $lang) => $unit->label($count, $lang), $this->locale()->languages()));
    }

    /**
     * A `_en` / `_bn` content pair as print lines: one line, or (bilingual) the English and,
     * when it differs, the Bangla below. Empty only when both are empty.
     *
     * @return list<string>
     */
    public function text(?string $en, ?string $bn): array
    {
        if ($this->locale() !== PrintLocale::Bilingual) {
            $text = Bilingual::pick($en, $bn, $this->locale()->value);

            return $text === null ? [] : [$text];
        }

        $first = Bilingual::pick($en, $bn, 'en');
        if ($first === null) {
            return [];
        }

        return filled($bn) && filled($en) && trim((string) $bn) !== trim((string) $en) ? [$first, (string) $bn] : [$first];
    }

    /** Bangla digits on a Bangla line when the print asks for them. */
    public function digits(string|int|null $text, string $lang = 'bn'): string
    {
        $text = (string) $text;

        return $lang === 'bn' && $this->options->banglaDigits ? BanglaDigits::convert($text) : $text;
    }

    /** Numbers in a line that is entirely Bangla (bn prints only). */
    public function number(string|int|null $text): string
    {
        return $this->locale() === PrintLocale::Bn ? $this->digits($text) : (string) $text;
    }

    public function date(?Carbon $date): string
    {
        if ($date === null) {
            return '';
        }

        return $this->locale() === PrintLocale::Bn
            ? $this->digits($date->copy()->locale('bn')->translatedFormat('j F Y'))
            : $date->copy()->locale('en')->format('j M Y');
    }

    /** @param  list<string>  $parts */
    private function join(array $parts): string
    {
        return implode(' / ', array_values(array_unique(array_filter($parts, 'filled'))));
    }
}
