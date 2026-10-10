<?php

namespace App\Domain\Clinical\ValueObjects;

use App\Domain\Document\Support\BanglaDigits;
use InvalidArgumentException;

/**
 * The `M+N+E` dose: morning, noon and night slots, e.g. `2+0+2`. Each slot is
 * a whole number or a half (`½`, `1½`); `0+0+0` is not a dose.
 * Typed halves (`1/2`, `0.5`, `1.5`, `1 1/2`) are normalised to `½` forms.
 */
final class DosePattern
{
    private const SLOT = '/^(?:\d{1,2}½?|½)$/u';

    private function __construct(
        public readonly string $morning,
        public readonly string $noon,
        public readonly string $night,
    ) {}

    public static function fromSlots(?string $morning, ?string $noon, ?string $night): self
    {
        $slots = array_map(fn ($slot) => self::normaliseSlot((string) $slot), [$morning, $noon, $night]);

        foreach ($slots as $slot) {
            if (preg_match(self::SLOT, $slot) !== 1) {
                throw new InvalidArgumentException('Each dose slot is a number such as 0, ½, 1, 1½ or 2.');
            }
        }

        if ($slots === ['0', '0', '0']) {
            throw new InvalidArgumentException('A dose of 0+0+0 gives nothing.');
        }

        return new self(...$slots);
    }

    public static function tryFromSlots(?string $morning, ?string $noon, ?string $night): ?self
    {
        try {
            return self::fromSlots($morning, $noon, $night);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** Parses "2+0+2". */
    public static function parse(string $pattern): self
    {
        $parts = array_map('trim', explode('+', $pattern));

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Write the dose as morning+noon+night, e.g. 1+0+1.');
        }

        return self::fromSlots(...$parts);
    }

    public static function normaliseSlot(string $slot): string
    {
        $slot = (string) preg_replace('/\s+/u', '', trim($slot));
        $slot = str_replace('1/2', '½', $slot);

        if (in_array($slot, ['.5', '0.5', '0½'], true)) {
            return '½';
        }

        if (preg_match('/^(\d{1,2})\.5$/', $slot, $m) === 1) {
            return $m[1].'½';
        }

        // "01" -> "1", but keep "0".
        if (preg_match('/^\d+$/', $slot) === 1) {
            return (string) (int) $slot;
        }

        return $slot;
    }

    public function format(bool $banglaDigits = false): string
    {
        $text = "{$this->morning}+{$this->noon}+{$this->night}";

        return $banglaDigits ? BanglaDigits::convert($text) : $text;
    }

    /** @return array{dose_morning: string, dose_noon: string, dose_night: string} */
    public function toArray(): array
    {
        return ['dose_morning' => $this->morning, 'dose_noon' => $this->noon, 'dose_night' => $this->night];
    }
}
