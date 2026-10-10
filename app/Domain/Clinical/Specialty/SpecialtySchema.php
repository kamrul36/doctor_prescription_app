<?php

namespace App\Domain\Clinical\Specialty;

use App\Domain\Practice\SpecialtyBlock;

/**
 * The fields of one specialty block on the pad, stored under its key in
 * `case_histories.specialty_data` (e.g. `{"gynae": {...}}`).
 */
interface SpecialtySchema
{
    public function block(): SpecialtyBlock;

    /**
     * Field definitions: key => [label, input type (text|number|date|select|textarea), rules, options?].
     *
     * @return array<string, array{label: string, type: string, rules: list<mixed>, options?: array<string, string>, hint?: string}>
     */
    public function fields(): array;
}
