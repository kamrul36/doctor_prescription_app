@extends('layouts.app')

@section('title', 'Doctors')

@section('content')
    <div class="mb-4">
        <h1 class="text-2xl font-semibold">Doctors</h1>
        <p class="text-sm text-gray-600">Choose the chambers each doctor works at. The doctor then sets their own visiting hours and fees in My profile. Users get the doctor role under <a href="{{ route('admin.users.index') }}" class="underline">Users</a>.</p>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">Doctor</th>
                    <th class="px-4 py-2">Email</th>
                    <th class="px-4 py-2">Specialties</th>
                    <th class="px-4 py-2">Chambers</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2 font-medium">
                            {{ $user->doctor?->name_en ?? $user->name }}
                            @unless ($user->is_active)
                                <span class="ml-1 text-xs text-gray-500">(inactive user)</span>
                            @endunless
                        </td>
                        <td class="px-4 py-2">{{ $user->email }}</td>
                        <td class="px-4 py-2">{{ $user->doctor?->specialties->pluck('name')->join(', ') ?: 'General' }}</td>
                        <td class="px-4 py-2">
                            @forelse ($user->doctor?->chambers ?? [] as $chamber)
                                <span @class(['mr-1 inline-block rounded px-2 py-0.5 text-xs', 'bg-teal-50 text-teal-800' => $chamber->is_active, 'bg-gray-100 text-gray-500' => ! $chamber->is_active])>{{ $chamber->name_en }}</span>
                            @empty
                                <span class="text-amber-700">None assigned</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.doctors.edit', $user) }}" class="hover:underline">Assign chambers</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No users have the doctor role yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
