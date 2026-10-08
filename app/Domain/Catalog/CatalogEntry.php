<?php

namespace App\Domain\Catalog;

/** What the typeahead needs to know about a catalog model. */
interface CatalogEntry
{
    /** @return list<string> columns the typeahead matches against */
    public static function searchColumns(): array;

    public static function sortColumn(): string;
}
