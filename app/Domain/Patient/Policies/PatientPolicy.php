<?php

namespace App\Domain\Patient\Policies;

use App\Domain\Access\Permission;
use App\Domain\Patient\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::PatientsRead->value);
    }

    public function view(User $actor, mixed $model = null): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::PatientsWrite->value);
    }

    public function update(User $actor, mixed $model = null): bool
    {
        return $this->create($actor);
    }

    /**
     * Needs `patients.delete`, and only a patient without visits: one with
     * prescriptions can be deleted by a super admin alone (Gate::before).
     * DeletePatientAction checks the visits again under a row lock.
     */
    public function delete(User $actor, mixed $model = null): bool
    {
        if (! $actor->can(Permission::PatientsDelete->value)) {
            return false;
        }

        return ! ($model instanceof Patient && $model->hasVisits());
    }
}
