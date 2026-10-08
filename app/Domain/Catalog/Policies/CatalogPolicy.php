<?php

namespace App\Domain\Catalog\Policies;

use App\Domain\Access\Permission;
use App\Domain\Catalog\Models\AdviceTemplate;
use App\Domain\Practice\Models\Doctor;
use App\Models\User;

/**
 * One policy for the catalogs. Reading is open to anyone who can read clinical
 * data (the visit screen needs it); changing needs `catalog.manage`. A doctor's
 * private advice template belongs to that doctor (a super admin passes via Gate::before).
 */
class CatalogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::CatalogManage->value) || $actor->can(Permission::CasesRead->value);
    }

    public function view(User $actor, mixed $model = null): bool
    {
        return $this->viewAny($actor) && $this->ownsOrShared($actor, $model);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }

    public function update(User $actor, mixed $model = null): bool
    {
        return $this->create($actor) && $this->ownsOrShared($actor, $model);
    }

    public function delete(User $actor, mixed $model = null): bool
    {
        return $this->update($actor, $model);
    }

    private function ownsOrShared(User $actor, mixed $model): bool
    {
        if (! $model instanceof AdviceTemplate || $model->doctor_id === null) {
            return true;
        }

        return Doctor::query()->where('user_id', $actor->id)->whereKey($model->doctor_id)->exists();
    }
}
