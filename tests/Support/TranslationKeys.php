<?php

namespace Tests\Support;

use App\Domain\Access\Permission;
use App\Domain\Clinical\Vitals;
use App\Support\Phone;
use Symfony\Component\Finder\Finder;

/**
 * Every English key the UI translates with `__()` (PHP, Blade) or `t()` (scripts), plus the
 * labels translated from data (vitals, specialty schemas, permissions). Used by the coverage test.
 */
final class TranslationKeys
{
    /** @return list<string> */
    public static function all(): array
    {
        $keys = [];
        $finder = (new Finder)->files()->in([base_path('app'), base_path('resources/views'), base_path('resources/js')])->name(['*.php', '*.js']);
        foreach ($finder as $file) {
            $source = $file->getContents();
            $pattern = str_ends_with($file->getFilename(), '.js')
                ? '/(?<![\w.])t\(\'((?:[^\'\\\\]|\\\\.)*)\'/'
                : '/(?:(?<![\w>:])__|->ui)\(\'((?:[^\'\\\\]|\\\\.)*)\'/';
            preg_match_all($pattern, $source, $m);
            foreach ($m[1] as $key) {
                $keys[] = stripslashes($key);
            }
        }

        foreach (Vitals::FIELDS as $field) {
            $keys[] = $field['label'];
        }
        foreach (Permission::cases() as $permission) {
            $keys[] = $permission->label('en');
            $keys[] = $permission->group();
        }
        array_push($keys, Phone::MESSAGE, ':n y', ':n m', ':n d', 'Planned', 'Done');

        // `enums.*` keys live in lang/{locale}/enums.php, not in the JSON file.
        // A key built by concatenation (`':n '.$unit`) is listed in full above.
        $keys = array_filter($keys, fn (string $key) => trim($key) === $key && $key !== '' && ! str_starts_with($key, 'enums.'));

        return array_values(array_unique($keys));
    }
}
