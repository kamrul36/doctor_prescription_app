<?php

namespace App\Domain\Patient\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Patient\Models\Patient;
use Illuminate\Support\Facades\DB;

/** Soft delete only: the code is never reused and history stays intact. */
class DeletePatientAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Patient $patient): void
    {
        DB::transaction(function () use ($patient) {
            $patient->delete();
            $this->audit->log('patient.deleted', $patient, ['code' => $patient->code]);
        });
    }
}
