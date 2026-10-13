<?php

namespace App\Domain\Document\Support;

use App\Support\Concerns\HasTranslatedLabel;
use App\Support\Contracts\HasLabel;

/** The language a prescription is printed in (spec §6). */
enum PrintLocale: string implements HasLabel
{
    use HasTranslatedLabel;

    case En = 'en';
    case Bn = 'bn';
    // Labels "Advice / পরামর্শ"; content in English with the Bangla below when it differs.
    case Bilingual = 'bilingual';

    /** The UI language's twin: Bangla prints for the Bangla UI (the default), English for English. */
    public static function forUi(?string $locale = null): self
    {
        return ($locale ?? app()->getLocale()) === 'en' ? self::En : self::Bn;
    }

    /** The UI languages printed, in order. @return list<'en'|'bn'> */
    public function languages(): array
    {
        return match ($this) {
            self::En => ['en'],
            self::Bn => ['bn'],
            self::Bilingual => ['en', 'bn'],
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
