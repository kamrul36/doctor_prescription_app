@extends('layouts.app')

@section('title', 'Chambers')

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Chambers</h1>
            <p class="text-sm text-gray-600">Assign chambers to doctors under <a href="{{ route('admin.doctors.index') }}" class="underline">Doctors</a>; each doctor sets their own visiting hours and fees.</p>
        </div>
        <a href="{{ route('admin.chambers.create') }}" class="ml-auto rounded bg-teal-700 px-3 py-1.5 text-sm text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">New chamber</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Address</th>
                    <th class="px-4 py-2">Phone</th>
                    <th class="px-4 py-2">Branches</th>
                    <th class="px-4 py-2">Doctors</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($chambers as $chamber)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2 font-medium">{{ $chamber->name_en }}</td>
                        <td class="px-4 py-2">{{ $chamber->address_en }}</td>
                        <td class="px-4 py-2">{{ $chamber->phone }}</td>
                        <td class="px-4 py-2">{{ $chamber->branches->count() }}</td>
                        <td class="px-4 py-2">{{ $chamber->doctors_count }}</td>
                        <td class="px-4 py-2">{{ $chamber->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.chambers.edit', $chamber) }}" class="hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No chambers yet. Create the first one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
