<?php

namespace App\Domain\Practice\Policies;

use App\Domain\Access\Permission;
use App\Models\User;

/**
 * One policy for the doctor's own setup (Doctor profile, PrescriptionTemplate):
 * gated by `templates.manage`; assigning chambers needs `practice.manage`.
 * Chambers and specialties themselves use PracticeAdminPolicy. Reading is allowed to anyone who
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

    /** Which chambers a doctor works at is decided by the admin, not the doctor. */
    public function assignChambers(User $actor, mixed $model = null): bool
    {
        return $actor->can(Permission::PracticeManage->value);
    }
}
