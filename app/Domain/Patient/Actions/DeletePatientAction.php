<?php

namespace App\Domain\Patient\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Patient\Models\Patient;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Soft delete only: the code is never reused and history stays intact.
 * A patient with any visit can be deleted only by a super admin; checked
 * again here with the row locked, so a visit saved after the policy check
 * still blocks the delete.
 */
class DeletePatientAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @throws AuthorizationException */
    public function handle(User $actor, Patient $patient): void
    {
        DB::transaction(function () use ($actor, $patient) {
            Patient::query()->whereKey($patient->id)->lockForUpdate()->first();

            if ($patient->hasVisits() && ! $actor->isSuperAdmin()) {
                throw new AuthorizationException(__('This patient has prescriptions; only an administrator can delete them.'));
            }

            $patient->delete();
            $this->audit->log('patient.deleted', $patient, ['code' => $patient->code]);
        });
    }
}
