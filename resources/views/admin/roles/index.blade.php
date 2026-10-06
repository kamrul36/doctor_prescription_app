@extends('layouts.app')

@section('title', 'Roles')

@section('content')
    <div class="mb-4 flex items-center gap-4">
        <h1 class="text-2xl font-semibold">Roles</h1>
        <a href="{{ route('admin.roles.create') }}" class="ml-auto rounded bg-gray-900 px-3 py-1.5 text-sm text-white">New role</a>
    </div>

    @include('admin._errors')

    <div class="overflow-x-auto rounded border bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">Role</th>
                    <th class="px-4 py-2">Permissions</th>
                    <th class="px-4 py-2">Users</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2 font-medium">{{ $role->name }}</td>
                        <td class="px-4 py-2">
                            {{ $role->name === \App\Domain\Access\Role::SUPER_ADMIN ? 'All' : $role->permissions_count }}
                        </td>
                        <td class="px-4 py-2">{{ $role->users_count }}</td>
                        <td class="px-4 py-2 text-right">
                            @unless ($role->name === \App\Domain\Access\Role::SUPER_ADMIN)
                                <a href="{{ route('admin.roles.edit', $role) }}" class="hover:underline">Edit</a>
                                @if ($role->users_count === 0)
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="ml-3 inline"
                                          onsubmit="return confirm('Delete role {{ $role->name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-700 hover:underline">Delete</button>
                                    </form>
                                @endif
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
