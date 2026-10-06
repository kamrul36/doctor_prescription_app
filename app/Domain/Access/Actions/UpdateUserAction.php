<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\UserAccessRules;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUserAction
{
    public function __construct(
        private readonly UserAccessRules $rules,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Fields left out of $data are not changed; an empty password is ignored.
     *
     * @param  array{name?: string, email?: string, phone?: string|null, password?: string|null, is_active?: bool, roles?: list<string>}  $data
     */
    public function handle(User $actor, User $user, array $data): User
    {
        $currentRoles = $user->getRoleNames()->values()->all();
        $newRoles = $data['roles'] ?? $currentRoles;
        $willBeActive = $data['is_active'] ?? $user->is_active;

        return DB::transaction(function () use ($actor, $user, $data, $currentRoles, $newRoles, $willBeActive) {
            $this->rules->ensureCanUpdate($actor, $user, $newRoles, $willBeActive);

            $attributes = array_intersect_key($data, array_flip(['name', 'email', 'phone', 'is_active']));
            if (! empty($data['password'])) {
                $attributes['password'] = $data['password'];
            }

            $user->fill($attributes);
            $changed = array_keys($user->getDirty());
            $user->save();

            if (array_key_exists('roles', $data)) {
                $user->syncRoles($newRoles);
            }

            $properties = array_filter([
                'changed' => $changed,
                'roles_added' => array_values(array_diff($newRoles, $currentRoles)),
                'roles_removed' => array_values(array_diff($currentRoles, $newRoles)),
            ]);

            if ($properties !== []) {
                $this->audit->log('user.updated', $user, $properties);
            }

            return $user;
        });
    }
}
