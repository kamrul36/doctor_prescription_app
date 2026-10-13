<?php

namespace App\Support\Concerns;

use App\Support\Bilingual;

/** `$chamber->localized('name')`: `name_bn` in the Bangla UI, `name_en` in English, the other one when empty. */
trait HasBilingualFields
{
    public function localized(string $field, ?string $locale = null): ?string
    {
        return Bilingual::pick($this->getAttribute($field.'_en'), $this->getAttribute($field.'_bn'), $locale);
    }
}
