<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Role;
use App\Domain\Access\UserAccessRules;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/** Creates a role, or renames it and replaces its permission set. */
class SaveRoleAction
{
    public function __construct(
        private readonly UserAccessRules $rules,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, permissions?: list<string>}  $data
     */
    public function handle(User $actor, ?RoleModel $role, array $data): RoleModel
    {
        if ($role !== null && $role->name === Role::SUPER_ADMIN) {
            throw ValidationException::withMessages([
                'name' => 'The super admin role cannot be changed.',
            ]);
        }

        $permissions = $data['permissions'] ?? [];
        $before = $role === null ? [] : $role->permissions->pluck('name')->all();

        $this->rules->ensureCanChangePermissions($actor, $before, $permissions);

        $saved = DB::transaction(function () use ($role, $data, $permissions, $before) {
            $creating = $role === null;
            $role ??= new RoleModel(['guard_name' => Role::GUARD]);

            $role->name = $data['name'];
            $role->save();
            $role->syncPermissions($permissions);

            $this->audit->log($creating ? 'role.created' : 'role.updated', $role, array_filter([
                'name' => $role->name,
                'permissions_added' => array_values(array_diff($permissions, $before)),
                'permissions_removed' => array_values(array_diff($before, $permissions)),
            ]));

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $saved->load('permissions');
    }
}
