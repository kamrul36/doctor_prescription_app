<?php

namespace App\Domain\Finance;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable BDT amount held in minor units (paisa). Never uses floats.
 */
final readonly class Money implements JsonSerializable
{
    private function __construct(private int $minor) {}

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromMinor(int $minor): self
    {
        return new self($minor);
    }

    /** Parses a plain decimal string such as "500", "500.5" or "-12.05". */
    public static function fromDecimal(string|int $value): self
    {
        $value = trim((string) $value);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $m)) {
            throw new InvalidArgumentException("Invalid money amount: '{$value}'.");
        }

        $minor = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return new self($m[1] === '-' ? -$minor : $minor);
    }

    public function minor(): int
    {
        return $this->minor;
    }

    public function add(self $other): self
    {
        return new self($this->minor + $other->minor);
    }

    public function subtract(self $other): self
    {
        return new self($this->minor - $other->minor);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->minor * $quantity);
    }

    public function negate(): self
    {
        return new self(-$this->minor);
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor;
    }

    public function greaterThan(self $other): bool
    {
        return $this->minor > $other->minor;
    }

    public function lessThan(self $other): bool
    {
        return $this->minor < $other->minor;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    /** Decimal string for storage/API, e.g. "1250.50". */
    public function toDecimal(): string
    {
        $abs = abs($this->minor);

        return ($this->minor < 0 ? '-' : '').intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Display form, e.g. "৳1,250.50". */
    public function format(): string
    {
        $abs = abs($this->minor);
        $whole = number_format(intdiv($abs, 100));

        return ($this->minor < 0 ? '-' : '').'৳'.$whole.'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    public function jsonSerialize(): string
    {
        return $this->toDecimal();
    }
}
