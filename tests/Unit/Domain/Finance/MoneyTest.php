<?php

namespace Tests\Unit\Domain\Finance;

use App\Domain\Finance\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_parses_decimal_strings_without_float_error(): void
    {
        $this->assertSame(50050, Money::fromDecimal('500.5')->minor());
        $this->assertSame(1005, Money::fromDecimal('10.05')->minor());
        $this->assertSame(50000, Money::fromDecimal(500)->minor());
        $this->assertSame(-1205, Money::fromDecimal('-12.05')->minor());
    }

    public function test_rejects_invalid_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimal('12.345');
    }

    public function test_arithmetic(): void
    {
        $total = Money::fromDecimal('0.10')->add(Money::fromDecimal('0.20'));

        $this->assertSame('0.30', $total->toDecimal());
        $this->assertSame('1500.00', Money::fromDecimal('500')->multiply(3)->toDecimal());
        $this->assertSame('-200.00', Money::fromDecimal('300')->subtract(Money::fromDecimal('500'))->toDecimal());
        $this->assertTrue(Money::fromDecimal('5')->greaterThan(Money::fromDecimal('4.99')));
        $this->assertTrue(Money::zero()->isZero());
    }

    public function test_formats_for_display(): void
    {
        $this->assertSame('৳1,250.50', Money::fromDecimal('1250.5')->format());
        $this->assertSame('-৳0.05', Money::fromDecimal('-0.05')->format());
        $this->assertSame('"1250.50"', json_encode(Money::fromDecimal('1250.5')));
    }
}
