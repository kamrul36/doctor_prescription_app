<?php

namespace Tests\Unit\Domain\Access;

use App\Domain\Access\Permission;
use App\Domain\Access\Role;
use PHPUnit\Framework\TestCase;

class RoleDefaultsTest extends TestCase
{
    public function test_system_roles_have_default_sets(): void
    {
        $this->assertSame(
            [Role::SUPER_ADMIN, Role::DOCTOR, Role::ASSISTANT],
            array_keys(Role::defaultPermissions()),
        );
    }

    public function test_super_admin_gets_permissions_from_the_gate_not_the_database(): void
    {
        $this->assertSame([], Role::defaultPermissions()[Role::SUPER_ADMIN]);
    }

    public function test_no_default_role_can_manage_access(): void
    {
        foreach ([Role::DOCTOR, Role::ASSISTANT] as $role) {
            $this->assertNotContains(Permission::UsersManage, Role::defaultPermissions()[$role]);
            $this->assertNotContains(Permission::RolesManage, Role::defaultPermissions()[$role]);
        }
    }

    public function test_every_permission_has_a_label_and_group(): void
    {
        foreach (Permission::cases() as $permission) {
            $this->assertNotSame('', $permission->label());
            $this->assertArrayHasKey($permission->group(), Permission::grouped());
        }
    }
}
