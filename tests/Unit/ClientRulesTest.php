<?php

namespace Tests\Unit;

use App\Support\ClientRules;
use PHPUnit\Framework\TestCase;

class ClientRulesTest extends TestCase
{
    public function test_numbers_get_a_rule_and_a_value_range(): void
    {
        $this->assertSame('data-rule="integer" data-min="40" data-max="300"', (string) ClientRules::attributes(['integer', 'between:40,300']));
        $this->assertSame('data-rule="decimal" data-min="0.5" data-max="350"', (string) ClientRules::attributes(['numeric', 'between:0.5,350']));
        $this->assertSame('data-rule="integer" data-max="9"', (string) ClientRules::attributes(['nullable', 'integer', 'max:9']));
    }

    public function test_text_length_and_dates(): void
    {
        $this->assertSame('required maxlength="32"', (string) ClientRules::attributes(['required', 'string', 'max:32']));
        $this->assertSame('minlength="3"', (string) ClientRules::attributes(['string', 'min:3']));
        $this->assertSame('data-rule="past-date"', (string) ClientRules::attributes(['date', 'before_or_equal:today']));
        $this->assertSame('data-rule="future-date"', (string) ClientRules::attributes(['date', 'after_or_equal:today']));
    }

    public function test_unknown_and_object_rules_are_left_to_the_server(): void
    {
        $this->assertSame('', (string) ClientRules::attributes(['in:regular,irregular', new \stdClass, 'before_or_equal:lmp']));
    }
}
