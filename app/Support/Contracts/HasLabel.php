<?php

namespace App\Support\Contracts;

/** An enum whose cases have a label in each UI language (see HasTranslatedLabel). */
interface HasLabel
{
    public function label(?string $locale = null): string;
}
