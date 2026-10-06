<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Role;
use App\Domain\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class DeleteRoleAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(RoleModel $role): void
    {
        if ($role->name === Role::SUPER_ADMIN) {
            throw ValidationException::withMessages([
                'role' => 'The super admin role cannot be deleted.',
            ]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'A role that is assigned to users cannot be deleted.',
            ]);
        }

        DB::transaction(function () use ($role) {
            $this->audit->log('role.deleted', $role, ['name' => $role->name]);
            $role->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
