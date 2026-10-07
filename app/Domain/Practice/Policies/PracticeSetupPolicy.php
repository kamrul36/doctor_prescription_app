<?php

namespace App\Domain\Practice\Policies;

use App\Domain\Access\Permission;
use App\Models\User;

/**
 * One policy for the setup models (Chamber, Doctor, PrescriptionTemplate):
 * everything is gated by `templates.manage`. Reading is allowed to anyone who
 * can read clinical data, because prescriptions need the template and chamber.
 */
class PracticeSetupPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::TemplatesManage->value) || $actor->can(Permission::CasesRead->value);
    }

    public function view(User $actor, mixed $model = null): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::TemplatesManage->value);
    }

    public function update(User $actor, mixed $model = null): bool
    {
        return $actor->can(Permission::TemplatesManage->value);
    }
}
