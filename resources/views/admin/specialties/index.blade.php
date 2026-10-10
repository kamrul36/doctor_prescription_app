@extends('layouts.app')

@section('title', 'Specialties')

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Specialties</h1>
            <p class="text-sm text-gray-600">Doctors pick their specialties from this list (or type a new one, which is added here). Catalog items tagged with a specialty are suggested first to its doctors.</p>
        </div>
        <a href="{{ route('admin.specialties.create') }}" class="ml-auto rounded bg-teal-700 px-3 py-1.5 text-sm text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">New specialty</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Code</th>
                    <th class="px-4 py-2">Prescription block</th>
                    <th class="px-4 py-2">Doctors</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($specialties as $specialty)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2 font-medium">{{ $specialty->name }}</td>
                        <td class="px-4 py-2"><code>{{ $specialty->code }}</code></td>
                        <td class="px-4 py-2">{{ $specialty->block?->label() ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $specialty->doctors_count }}</td>
                        <td class="px-4 py-2">{{ $specialty->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.specialties.edit', $specialty) }}" class="hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No specialties yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
