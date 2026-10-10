<?php

namespace App\Domain\Clinical\ValueObjects;

use App\Domain\Clinical\DurationUnit;
use InvalidArgumentException;

/**
 * The typing shortcut on the pad (mirrored in resources/js/components/dose.js):
 * `2+0+2 7d`, `1+0+0 6m`, `1+1+1 2w`, `0+0+1 cont`, `1+0+1 sos`, or just `1+0+1`.
 */
final class DoseShortcut
{
    private const UNITS = [
        'd' => DurationUnit::Day, 'day' => DurationUnit::Day, 'days' => DurationUnit::Day,
        'w' => DurationUnit::Week, 'wk' => DurationUnit::Week, 'week' => DurationUnit::Week, 'weeks' => DurationUnit::Week,
        'm' => DurationUnit::Month, 'mo' => DurationUnit::Month, 'month' => DurationUnit::Month, 'months' => DurationUnit::Month,
        'cont' => DurationUnit::Continuous, 'continuous' => DurationUnit::Continuous,
        'sos' => DurationUnit::AsNeeded, 'prn' => DurationUnit::AsNeeded, 'as_needed' => DurationUnit::AsNeeded,
    ];

    /** @return array{dose: DosePattern, duration: Duration|null} */
    public static function parse(string $input): array
    {
        $text = mb_strtolower(trim($input));

        if (preg_match('/^(\S+(?:\s*\+\s*[^\s+]+){2})(?:\s+(.+))?$/u', $text, $m) !== 1) {
            throw new InvalidArgumentException('Write the dose as morning+noon+night, e.g. 1+0+1 7d.');
        }

        $dose = DosePattern::parse($m[1]);
        $rest = trim($m[2] ?? '');

        if ($rest === '') {
            return ['dose' => $dose, 'duration' => null];
        }

        if (isset(self::UNITS[$rest]) && ! self::UNITS[$rest]->needsValue()) {
            return ['dose' => $dose, 'duration' => Duration::of(null, self::UNITS[$rest])];
        }

        if (preg_match('/^(\d{1,3})\s*([a-z_]+)$/', $rest, $d) !== 1 || ! isset(self::UNITS[$d[2]]) || ! self::UNITS[$d[2]]->needsValue()) {
            throw new InvalidArgumentException('Duration: a number with d, w or m (7d, 2w, 6m), or cont / sos.');
        }

        return ['dose' => $dose, 'duration' => Duration::of($d[1], self::UNITS[$d[2]])];
    }
}
