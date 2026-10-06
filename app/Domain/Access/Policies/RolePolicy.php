<?php

namespace App\Domain\Access\Policies;

use App\Domain\Access\Permission;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::RolesManage->value);
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->can(Permission::RolesManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::RolesManage->value);
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->can(Permission::RolesManage->value);
    }

    public function delete(User $actor, Role $role): bool
    {
        return $actor->can(Permission::RolesManage->value);
    }
}
