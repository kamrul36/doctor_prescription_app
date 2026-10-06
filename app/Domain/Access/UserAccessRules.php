<?php

namespace App\Domain\Access;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Guards against privilege escalation and lock-out when users and roles are
 * created or changed. Enforced in the actions, so web and API share it, and
 * it applies even to super admins (who bypass policies).
 *
 * Besides the super admin rules, a non-super-admin can only grant, revoke or
 * touch what they hold themselves: otherwise users.manage or roles.manage
 * would be a path to every permission.
 */
class UserAccessRules
{
    /**
     * Only a super admin may grant or revoke the super admin role; anyone
     * else may only grant or revoke roles whose permissions they hold.
     *
     * @param  list<string>  $currentRoles
     * @param  list<string>  $newRoles
     */
    public function ensureCanAssign(User $actor, array $currentRoles, array $newRoles): void
    {
        $was = in_array(Role::SUPER_ADMIN, $currentRoles, true);
        $will = in_array(Role::SUPER_ADMIN, $newRoles, true);

        if ($was !== $will && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'roles' => 'Only a super admin can grant or revoke the super admin role.',
            ]);
        }

        $changed = array_merge(array_diff($newRoles, $currentRoles), array_diff($currentRoles, $newRoles));

        if (! $this->holdsAll($actor, $this->permissionsOfRoles($changed))) {
            throw ValidationException::withMessages([
                'roles' => 'You can only grant or revoke roles whose permissions you have yourself.',
            ]);
        }
    }

    /**
     * Call inside the transaction that applies the change: the last super
     * admin check locks the super admin rows.
     *
     * @param  list<string>  $newRoles
     */
    public function ensureCanUpdate(User $actor, User $user, array $newRoles, bool $willBeActive): void
    {
        if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'user' => 'Only a super admin can change a super admin account.',
            ]);
        }

        $this->ensureCanAssign($actor, $user->getRoleNames()->values()->all(), $newRoles);

        if (! $this->holdsAll($actor, $user->getAllPermissions()->pluck('name')->all())) {
            throw ValidationException::withMessages([
                'user' => 'You cannot change a user who has permissions you do not have.',
            ]);
        }

        if ($actor->is($user) && ! $willBeActive) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate your own account.',
            ]);
        }

        $losesSuperAdmin = $user->isSuperAdmin()
            && $user->is_active
            && (! $willBeActive || ! in_array(Role::SUPER_ADMIN, $newRoles, true));

        if ($losesSuperAdmin && $this->otherActiveSuperAdmins($user) === 0) {
            throw ValidationException::withMessages([
                'roles' => 'At least one active super admin must remain.',
            ]);
        }
    }

    /**
     * A non-super-admin may only add or remove permissions they hold.
     *
     * @param  list<string>  $currentPermissions
     * @param  list<string>  $newPermissions
     */
    public function ensureCanChangePermissions(User $actor, array $currentPermissions, array $newPermissions): void
    {
        $changed = array_merge(
            array_diff($newPermissions, $currentPermissions),
            array_diff($currentPermissions, $newPermissions),
        );

        if (! $this->holdsAll($actor, $changed)) {
            throw ValidationException::withMessages([
                'permissions' => 'You can only grant or revoke permissions you have yourself.',
            ]);
        }
    }

    /** @param  list<string>  $permissions */
    private function holdsAll(User $actor, array $permissions): bool
    {
        if ($permissions === [] || $actor->isSuperAdmin()) {
            return true;
        }

        return array_diff($permissions, $actor->getAllPermissions()->pluck('name')->all()) === [];
    }

    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    private function permissionsOfRoles(array $roles): array
    {
        if ($roles === []) {
            return [];
        }

        return RoleModel::query()
            ->whereIn('name', $roles)
            ->where('guard_name', Role::GUARD)
            ->with('permissions')
            ->get()
            ->flatMap(fn (RoleModel $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Locks every active super admin row, the target's included, so two
     * concurrent demotions run one after the other and the second sees the
     * first one's result.
     */
    private function otherActiveSuperAdmins(User $user): int
    {
        return User::role(Role::SUPER_ADMIN)
            ->where('is_active', true)
            ->lockForUpdate()
            ->pluck('id')
            ->reject(fn ($id) => $id === $user->getKey())
            ->count();
    }
}
