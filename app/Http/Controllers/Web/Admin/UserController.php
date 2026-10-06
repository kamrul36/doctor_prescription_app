<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Access\Actions\CreateUserAction;
use App\Domain\Access\Actions\UpdateUserAction;
use App\Domain\Access\Role as AccessRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $search = $request->string('search')->trim()->value();

        $users = User::query()
            ->with('roles')
            ->when($search, fn ($q) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', ['user' => new User(['is_active' => true]), 'roles' => $this->roleNames()]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $create): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $user = $create->handle($actor, $request->validated());

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} created.");
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', ['user' => $user, 'roles' => $this->roleNames()]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $update): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        // The edit form always submits the full role list; no ticks means no roles.
        $update->handle($actor, $user, $request->validated() + ['roles' => []]);

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} updated.");
    }

    /** @return list<string> */
    private function roleNames(): array
    {
        return Role::query()->where('guard_name', AccessRole::GUARD)->orderBy('name')->pluck('name')->all();
    }
}
