@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <h1 class="text-2xl font-semibold">Users</h1>
        <form method="GET" class="ml-auto">
            <input type="search" name="search" value="{{ $search }}" placeholder="Name or email"
                   class="rounded px-3 py-1.5 ring-1 ring-gray-300">
        </form>
        <a href="{{ route('admin.users.create') }}" class="rounded bg-teal-700 shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none px-3 py-1.5 text-sm text-white">New user</a>
    </div>

    @include('admin._errors')

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Email</th>
                    <th class="px-4 py-2">Phone</th>
                    <th class="px-4 py-2">Roles</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2">{{ $user->name }}</td>
                        <td class="px-4 py-2">{{ $user->email }}</td>
                        <td class="px-4 py-2">{{ $user->phone }}</td>
                        <td class="px-4 py-2">{{ $user->roles->pluck('name')->join(', ') }}</td>
                        <td class="px-4 py-2">
                            @if ($user->is_active)
                                <span class="text-green-700">Active</span>
                            @else
                                <span class="text-gray-500">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.users.edit', $user) }}" class="hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
