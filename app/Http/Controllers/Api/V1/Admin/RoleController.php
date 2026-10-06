<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Actions\DeleteRoleAction;
use App\Domain\Access\Actions\SaveRoleAction;
use App\Domain\Access\Role as AccessRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::query()
            ->where('guard_name', AccessRole::GUARD)
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles);
    }

    public function store(SaveRoleRequest $request, SaveRoleAction $save): RoleResource
    {
        return new RoleResource($save->handle($this->actor($request), null, $request->validated()));
    }

    public function show(Role $role): RoleResource
    {
        Gate::authorize('view', $role);

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    public function update(SaveRoleRequest $request, Role $role, SaveRoleAction $save): RoleResource
    {
        return new RoleResource($save->handle($this->actor($request), $role, $request->validated()));
    }

    public function destroy(Role $role, DeleteRoleAction $delete): Response
    {
        Gate::authorize('delete', $role);

        $delete->handle($role);

        return response()->noContent();
    }

    private function actor(SaveRoleRequest $request): User
    {
        /** @var User */
        return $request->user();
    }
}
