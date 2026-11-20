<?php

namespace App\Support\Rules;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * The one place new `exists` / `unique` rules are built (spec §16, rule A8).
 * Plain rules for now; the multi-tenant phase adds the tenant scope here.
 */
final class Owned
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column);
    }

    public static function unique(string $table, string $column = 'NULL'): Unique
    {
        return Rule::unique($table, $column);
    }
}
