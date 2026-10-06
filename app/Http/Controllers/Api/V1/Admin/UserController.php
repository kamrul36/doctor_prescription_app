<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Access\Actions\CreateUserAction;
use App\Domain\Access\Actions\UpdateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** Users are never deleted; deactivate them with `is_active: false`. */
class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->when($request->string('search')->trim()->value(), fn ($q, $search) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request, CreateUserAction $create): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new UserResource($create->handle($actor, $request->validated()));
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return (new UserResource($user->load('roles')))->withPermissions();
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $update): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new UserResource($update->handle($actor, $user, $request->validated()));
    }
}
