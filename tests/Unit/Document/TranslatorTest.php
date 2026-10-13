<?php

namespace Tests\Unit\Document;

use App\Domain\Clinical\DoseUnit;
use App\Domain\Clinical\DurationUnit;
use App\Domain\Clinical\PeriodUnit;
use App\Domain\Document\Support\PrintLocale;
use App\Domain\Document\Support\PrintOptions;
use App\Domain\Document\Support\Translator;
use App\Domain\Patient\Models\Patient;
use App\Domain\Practice\PrintMode;
use App\Support\Bilingual;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The printing rules of spec §6: labels, fallback, bilingual lines, Bangla digits. */
class TranslatorTest extends TestCase
{
    public function test_labels_per_print_language(): void
    {
        $this->assertSame('Advice', $this->t('en')->label('Advice', 'পরামর্শ'));
        $this->assertSame('পরামর্শ', $this->t('bn')->label('Advice', 'পরামর্শ'));
        $this->assertSame('Advice / পরামর্শ', $this->t('bilingual')->label('Advice', 'পরামর্শ'));
        $this->assertSame('Rx', $this->t('bilingual')->label('Rx', 'Rx'), 'the same word prints once');
        $this->assertSame('Age / বয়স', $this->t('bilingual')->ui('Age'));
    }

    public function test_content_falls_back_to_the_other_language_and_is_never_blank(): void
    {
        $this->assertSame(['Only English'], $this->t('bn')->text('Only English', null));
        $this->assertSame(['শুধু বাংলা'], $this->t('en')->text('', 'শুধু বাংলা'));
        $this->assertSame([], $this->t('bn')->text(null, '  '));

        $this->assertSame(['Rest', 'বিশ্রাম'], $this->t('bilingual')->text('Rest', 'বিশ্রাম'));
        $this->assertSame(['Same'], $this->t('bilingual')->text('Same', 'Same'));
        $this->assertSame(['শুধু বাংলা'], $this->t('bilingual')->text(null, 'শুধু বাংলা'));

        $this->assertSame('বাংলা', Bilingual::pick('English', 'বাংলা', 'bn'));
        $this->assertSame('English', Bilingual::pick('English', null, 'bn'));
    }

    public function test_bangla_digits_only_where_asked(): void
    {
        $this->assertSame('৭', $this->t('bn')->number(7));
        $this->assertSame('7', $this->t('bn', digits: false)->number(7));
        $this->assertSame('7', $this->t('bilingual')->number(7), 'a bilingual line keeps Western digits');
        $this->assertSame('১৩ অক্টোবর ২০২৬', $this->t('bn')->date(Carbon::parse('2026-10-13')));
        $this->assertSame('13 Oct 2026', $this->t('en')->date(Carbon::parse('2026-10-13')));
    }

    public function test_enum_labels_in_both_languages(): void
    {
        $this->assertSame('Tablet', DoseUnit::Tablet->label('en'));
        $this->assertSame('ট্যাবলেট', DoseUnit::Tablet->label('bn'));
        $this->assertSame('ট্যাবলেট', DoseUnit::Tablet->labelBn());
        $this->assertSame('day', DurationUnit::Day->label(1, 'en'));
        $this->assertSame('days', DurationUnit::Day->label(7, 'en'));
        $this->assertSame('দিন', DurationUnit::Day->label(7, 'bn'));
        $this->assertSame('weeks', PeriodUnit::Week->label());
        $this->assertSame('Tablet / ট্যাবলেট', $this->t('bilingual')->enum(DoseUnit::Tablet));
        $this->assertSame('days / দিন', $this->t('bilingual')->unit(DurationUnit::Day, 3));
    }

    public function test_age_text_per_language(): void
    {
        $this->assertSame('34 y', Patient::ageLabel('34 y', 'en'));
        $this->assertSame('34 বছর', Patient::ageLabel('34 y', 'bn'));
        $this->assertSame('7 মাস', Patient::ageLabel('7 m', 'bn'));
        $this->assertSame('', Patient::ageLabel(null));
    }

    public function test_print_option_defaults(): void
    {
        app()->setLocale('bn');
        $bn = PrintOptions::fromArray([]);
        $this->assertSame(PrintLocale::Bn, $bn->locale);
        $this->assertTrue($bn->banglaDigits);
        $this->assertSame(PrintMode::WithLetterhead, $bn->mode);

        app()->setLocale('en');
        $en = PrintOptions::fromArray([], PrintMode::PadOnly);
        $this->assertSame(PrintLocale::En, $en->locale);
        $this->assertFalse($en->banglaDigits);
        $this->assertSame(PrintMode::PadOnly, $en->mode);

        $this->assertFalse(PrintOptions::fromArray(['lang' => 'bn', 'bn_digits' => '0'])->banglaDigits);
        $this->assertSame(['lang' => 'bilingual', 'mode' => 'pad_only', 'bn_digits' => 1],
            PrintOptions::fromArray(['lang' => 'bilingual', 'mode' => 'pad_only'])->toQuery());
    }

    private function t(string $lang, bool $digits = true): Translator
    {
        return new Translator(new PrintOptions(PrintLocale::from($lang), PrintMode::WithLetterhead, $digits));
    }
}
