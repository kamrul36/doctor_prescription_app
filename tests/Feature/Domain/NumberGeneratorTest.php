<?php

namespace Tests\Feature\Domain;

use App\Domain\Finance\NumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_code_uses_short_year_and_six_digit_serial(): void
    {
        $gen = new NumberGenerator;
        $at = Carbon::create(2026, 3, 1);

        $this->assertSame('26000001', $gen->next(NumberGenerator::PATIENT, $at));
        $this->assertSame('26000002', $gen->next(NumberGenerator::PATIENT, $at));
    }

    public function test_document_numbers_are_prefixed(): void
    {
        $gen = new NumberGenerator;
        $at = Carbon::create(2026, 3, 1);

        $this->assertSame('RX-2026-000001', $gen->next(NumberGenerator::PRESCRIPTION, $at));
        $this->assertSame('INV-2026-000001', $gen->next(NumberGenerator::INVOICE, $at));
        $this->assertSame('RC-2026-000001', $gen->next(NumberGenerator::RECEIPT, $at));
    }

    public function test_serial_restarts_each_year_and_sequences_are_independent(): void
    {
        $gen = new NumberGenerator;

        $gen->next(NumberGenerator::PATIENT, Carbon::create(2026, 12, 31));
        $gen->next(NumberGenerator::PATIENT, Carbon::create(2026, 12, 31));

        $this->assertSame('27000001', $gen->next(NumberGenerator::PATIENT, Carbon::create(2027, 1, 1)));
        $this->assertSame('INV-2026-000001', $gen->next(NumberGenerator::INVOICE, Carbon::create(2026, 12, 31)));
    }

    public function test_unknown_sequence_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new NumberGenerator)->next('nope');
    }
}
