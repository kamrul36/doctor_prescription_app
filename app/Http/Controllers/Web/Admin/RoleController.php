<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Access\Actions\DeleteRoleAction;
use App\Domain\Access\Actions\SaveRoleAction;
use App\Domain\Access\Permission;
use App\Domain\Access\Role as AccessRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::query()
            ->where('guard_name', AccessRole::GUARD)
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        Gate::authorize('create', Role::class);

        return view('admin.roles.create', ['role' => new Role, 'granted' => [], 'groups' => Permission::grouped()]);
    }

    public function store(SaveRoleRequest $request, SaveRoleAction $save): RedirectResponse
    {
        $role = $save->handle($this->actor($request), null, $request->validated());

        return redirect()->route('admin.roles.index')->with('status', "Role {$role->name} created.");
    }

    public function edit(Role $role): View
    {
        Gate::authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role,
            'granted' => $role->permissions->pluck('name')->all(),
            'groups' => Permission::grouped(),
        ]);
    }

    public function update(SaveRoleRequest $request, Role $role, SaveRoleAction $save): RedirectResponse
    {
        $save->handle($this->actor($request), $role, $request->validated());

        return redirect()->route('admin.roles.index')->with('status', "Role {$role->name} updated.");
    }

    public function destroy(Role $role, DeleteRoleAction $delete): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $delete->handle($role);

        return redirect()->route('admin.roles.index')->with('status', "Role {$role->name} deleted.");
    }

    private function actor(SaveRoleRequest $request): User
    {
        /** @var User */
        return $request->user();
    }
}
