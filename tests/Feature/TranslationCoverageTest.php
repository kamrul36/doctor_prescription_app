<?php

namespace Tests\Feature;

use App\Support\Concerns\HasTranslatedLabel;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Tests\Support\TranslationKeys;
use Tests\TestCase;

/** Every UI string has a Bangla translation, so a new screen cannot ship English-only by accident. */
class TranslationCoverageTest extends TestCase
{
    public function test_every_ui_string_has_a_bangla_translation(): void
    {
        $bangla = json_decode((string) file_get_contents(lang_path('bn.json')), true, flags: JSON_THROW_ON_ERROR);

        $missing = array_values(array_filter(TranslationKeys::all(), fn (string $key) => ! array_key_exists($key, $bangla)));

        $this->assertSame([], $missing, "Add these keys to lang/bn.json:\n".implode("\n", $missing));
    }

    public function test_bangla_translations_keep_their_placeholders(): void
    {
        $bangla = json_decode((string) file_get_contents(lang_path('bn.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($bangla as $english => $text) {
            preg_match_all('/:([a-z_]+)/', $english, $a);
            preg_match_all('/:([a-z_]+)/', $text, $b);
            $this->assertEqualsCanonicalizing(array_unique($a[1]), array_unique($b[1]), "Placeholders differ for \"{$english}\"");
        }
    }

    public function test_every_enum_label_exists_in_both_languages(): void
    {
        $finder = (new Finder)->files()->in(app_path('Domain'))->name('*.php')->contains('use HasTranslatedLabel;');
        $this->assertNotEmpty(iterator_to_array($finder));

        foreach ($finder as $file) {
            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], Str::after($file->getRealPath(), app_path().DIRECTORY_SEPARATOR));
            $this->assertContains(HasTranslatedLabel::class, class_uses($class));
            foreach ($class::cases() as $case) {
                foreach (['en', 'bn'] as $locale) {
                    $key = 'enums.'.Str::snake(class_basename($class)).'.'.$case->value;
                    $this->assertTrue(trans()->hasForLocale($key, $locale), "Missing {$locale} label {$key}");
                }
            }
        }

        foreach (['duration_unit', 'period_unit'] as $group) {
            $this->assertSame(array_keys(trans("enums.{$group}", [], 'en')), array_keys(trans("enums.{$group}", [], 'bn')));
        }
    }
}
