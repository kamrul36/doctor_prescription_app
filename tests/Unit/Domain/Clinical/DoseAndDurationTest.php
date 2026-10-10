<?php

namespace Tests\Unit\Domain\Clinical;

use App\Domain\Clinical\DurationUnit;
use App\Domain\Clinical\ValueObjects\DosePattern;
use App\Domain\Clinical\ValueObjects\DoseShortcut;
use App\Domain\Clinical\ValueObjects\Duration;
use App\Domain\Document\Support\BanglaDigits;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DoseAndDurationTest extends TestCase
{
    public function test_dose_pattern_parses_and_prints(): void
    {
        $dose = DosePattern::parse('2+0+2');

        $this->assertSame('2', $dose->morning);
        $this->assertSame('0', $dose->noon);
        $this->assertSame('2+0+2', $dose->format());
        $this->assertSame('২+০+২', $dose->format(banglaDigits: true));
        $this->assertSame(['dose_morning' => '2', 'dose_noon' => '0', 'dose_night' => '2'], $dose->toArray());
    }

    /** @return array<string, array{string, string}> */
    public static function halves(): array
    {
        return [
            'half sign' => ['½+0+½', '½+0+½'],
            'slash' => ['1/2+0+1/2', '½+0+½'],
            'decimal' => ['0.5+1.5+0', '½+1½+0'],
            'one and a half' => ['1 1/2+0+1', '1½+0+1'],
            'leading zero' => ['01+0+01', '1+0+1'],
        ];
    }

    #[DataProvider('halves')]
    public function test_halves_are_normalised(string $input, string $expected): void
    {
        $this->assertSame($expected, DosePattern::parse($input)->format());
    }

    /** @return array<string, array{string}> */
    public static function badDoses(): array
    {
        return [
            'nothing' => ['0+0+0'],
            'two slots' => ['1+1'],
            'four slots' => ['1+0+1+1'],
            'word' => ['one+0+1'],
            'negative' => ['-1+0+1'],
            'quarter' => ['¼+0+1'],
        ];
    }

    #[DataProvider('badDoses')]
    public function test_invalid_doses_are_rejected(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        DosePattern::parse($input);
    }

    public function test_try_from_slots_returns_null_for_incomplete_dose(): void
    {
        $this->assertNull(DosePattern::tryFromSlots('1', null, '1'));
        $this->assertSame('1+0+1', DosePattern::tryFromSlots('1', '0', '1')?->format());
    }

    public function test_duration_rules_and_printing(): void
    {
        $this->assertSame('7 days', Duration::of(7, DurationUnit::Day)->format());
        $this->assertSame('1 month', Duration::of('1', 'month')->format());
        $this->assertSame('৬ মাস', Duration::of(6, DurationUnit::Month)->format(bangla: true));
        $this->assertSame('continue', Duration::of(null, DurationUnit::Continuous)->format());
        // Open-ended durations ignore a stray number.
        $this->assertNull(Duration::of(5, DurationUnit::AsNeeded)->value);

        foreach ([[null, 'day'], [0, 'week'], [1000, 'day'], ['x', 'day'], [3, 'fortnight'], [3, null]] as [$value, $unit]) {
            try {
                Duration::of($value, $unit);
                $this->fail("Duration {$value} {$unit} should be rejected");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** @return array<string, array{string, string, string|null}> */
    public static function shortcuts(): array
    {
        return [
            'days' => ['2+0+2 7d', '2+0+2', '7 days'],
            'months' => ['1+0+0 6m', '1+0+0', '6 months'],
            'weeks spelled' => ['1+1+1 2 weeks', '1+1+1', '2 weeks'],
            'continuous' => ['0+0+1 cont', '0+0+1', 'continue'],
            'as needed' => ['1+0+1 sos', '1+0+1', 'as needed'],
            'dose only' => ['1+0+1', '1+0+1', null],
            'spaces and case' => [' 1 + 0 + 1  7D ', '1+0+1', '7 days'],
        ];
    }

    #[DataProvider('shortcuts')]
    public function test_dose_shortcut(string $input, string $dose, ?string $duration): void
    {
        $parsed = DoseShortcut::parse($input);

        $this->assertSame($dose, $parsed['dose']->format());
        $this->assertSame($duration, $parsed['duration']?->format());
    }

    public function test_bad_shortcuts_are_rejected(): void
    {
        foreach (['', '2+0+2 7x', '2+0+2 d', '0+0+0 7d', 'two tablets'] as $input) {
            try {
                DoseShortcut::parse($input);
                $this->fail("Shortcut '{$input}' should be rejected");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_bangla_digits(): void
    {
        $this->assertSame('২৬০০০১২৩', BanglaDigits::convert('26000123'));
        $this->assertSame('RX-২০২৬', BanglaDigits::convert('RX-2026'));
    }
}
