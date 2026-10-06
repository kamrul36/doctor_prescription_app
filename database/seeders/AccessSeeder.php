<?php

namespace Database\Seeders;

use App\Domain\Access\Permission;
use App\Domain\Access\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Syncs the Permission enum to the database and creates the system roles.
 *
 * Safe to re-run on every deploy: default permission sets are applied only
 * to roles that have none yet (new roles, or roles carried over from the old
 * `users.role` column), so changes made in the role admin survive.
 */
class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (Permission::values() as $name) {
            PermissionModel::findOrCreate($name, Role::GUARD);
        }

        PermissionModel::query()
            ->where('guard_name', Role::GUARD)
            ->whereNotIn('name', Permission::values())
            ->get()
            ->each->delete();

        foreach (Role::defaultPermissions() as $name => $permissions) {
            /** @var RoleModel $role */
            $role = RoleModel::findOrCreate($name, Role::GUARD);

            if ($role->permissions()->doesntExist()) {
                $role->syncPermissions(array_map(fn (Permission $p) => $p->value, $permissions));
            }
        }

        $registrar->forgetCachedPermissions();
    }
}
