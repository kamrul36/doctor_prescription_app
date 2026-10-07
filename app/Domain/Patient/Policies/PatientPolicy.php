<?php

namespace App\Domain\Patient\Policies;

use App\Domain\Access\Permission;
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

    public function delete(User $actor, mixed $model = null): bool
    {
        return $this->create($actor);
    }
}
