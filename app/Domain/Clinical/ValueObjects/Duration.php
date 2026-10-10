<?php

namespace App\Domain\Clinical\ValueObjects;

use App\Domain\Clinical\DurationUnit;
use App\Domain\Document\Support\BanglaDigits;
use InvalidArgumentException;

/** How long a medicine is taken: `7 day`, `6 month`, or `continuous` / `as_needed` (no number). */
final class Duration
{
    public const MAX_VALUE = 999;

    private function __construct(public readonly ?int $value, public readonly DurationUnit $unit) {}

    public static function of(int|string|null $value, DurationUnit|string|null $unit): self
    {
        $unit = $unit instanceof DurationUnit ? $unit : DurationUnit::tryFrom((string) $unit);

        if ($unit === null) {
            throw new InvalidArgumentException('Choose a duration (days, weeks, months, continue or as needed).');
        }

        if (! $unit->needsValue()) {
            return new self(null, $unit);
        }

        if ($value === null || $value === '' || ! ctype_digit((string) $value) || (int) $value < 1 || (int) $value > self::MAX_VALUE) {
            throw new InvalidArgumentException('Give the number of '.$unit->label().' (1 to '.self::MAX_VALUE.').');
        }

        return new self((int) $value, $unit);
    }

    public function format(bool $bangla = false): string
    {
        if (! $this->unit->needsValue()) {
            return $bangla ? $this->unit->labelBn() : $this->unit->label();
        }

        return $bangla
            ? BanglaDigits::convert((string) $this->value).' '.$this->unit->labelBn()
            : $this->value.' '.$this->unit->label((int) $this->value);
    }
}
