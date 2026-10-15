<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Deleting is its own permission now: `patients.delete` and `catalog.delete`
 * (before, `patients.write` and `catalog.manage` included delete). The admin
 * grants or removes them per role in Roles.
 *
 * AccessSeeder never changes a role that already has permissions, so this
 * migration grants both to the existing `doctor` role. Assistants get neither
 * (they could delete patients before; the admin can grant it back). On a fresh
 * database the roles do not exist yet and AccessSeeder applies the defaults.
 *
 * down() removes the two permissions and their role/user links, which this
 * migration created; nothing else is touched.
 */
return new class extends Migration
{
    private const NAMES = ['patients.delete', 'catalog.delete'];

    private const GUARD = 'web';

    public function up(): void
    {
        $now = now();

        foreach (self::NAMES as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name, 'guard_name' => self::GUARD, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $doctorRoleId = DB::table('roles')->where('name', 'doctor')->where('guard_name', self::GUARD)->value('id');

        if ($doctorRoleId !== null) {
            $permissionIds = DB::table('permissions')->whereIn('name', self::NAMES)->where('guard_name', self::GUARD)->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $doctorRoleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', self::NAMES)->where('guard_name', self::GUARD)->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
