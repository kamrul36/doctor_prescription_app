<?php

namespace App\Domain\Clinical\Exceptions;

use App\Domain\Patient\Models\Patient;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * The pad was about to register a new patient who may already exist. The
 * doctor chooses: use one of the candidates, or register anyway
 * (`patient_choice` = a patient id or `new`).
 */
class PossibleDuplicatePatients extends RuntimeException
{
    /** @param  Collection<int, Patient>  $candidates */
    public function __construct(public readonly Collection $candidates)
    {
        parent::__construct('This patient may already be registered.');
    }
}
