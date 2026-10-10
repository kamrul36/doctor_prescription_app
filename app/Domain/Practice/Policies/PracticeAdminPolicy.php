<?php

namespace App\Domain\Practice\Policies;

use App\Domain\Access\Permission;
use App\Models\User;

/**
 * Chambers and the specialty list are managed by the admin (`practice.manage`,
 * super admin by default). Every staff member who works with setup or
 * clinical data can read them, because the profile and the pad choose from them.
 */
class PracticeAdminPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::PracticeManage->value)
            || $actor->can(Permission::TemplatesManage->value)
            || $actor->can(Permission::CasesRead->value);
    }

    public function view(User $actor, mixed $model = null): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::PracticeManage->value);
    }

    public function update(User $actor, mixed $model = null): bool
    {
        return $actor->can(Permission::PracticeManage->value);
    }
}
