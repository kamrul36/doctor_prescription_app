@extends('layouts.app')

@section('title', 'Prescription templates')

@section('content')
    <div class="mb-4 flex items-center gap-4">
        <h1 class="text-2xl font-semibold">Prescription templates</h1>
        @can('templates.manage')
            <a href="{{ route('settings.templates.create') }}" class="ml-auto rounded bg-teal-700 shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none px-3 py-1.5 text-sm text-white">New template</a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Code</th>
                    <th class="px-4 py-2">Specialty</th>
                    <th class="px-4 py-2">Layout</th>
                    <th class="px-4 py-2">Print mode</th>
                    <th class="px-4 py-2">Owner</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($templates as $template)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2 font-medium">{{ $template->name }}</td>
                        <td class="px-4 py-2"><code>{{ $template->code }}</code></td>
                        <td class="px-4 py-2">{{ config('practice.specialties')[$template->specialty_code] ?? $template->specialty_code }}</td>
                        <td class="px-4 py-2">{{ $template->layout->label() }} · {{ $template->paper_size }}</td>
                        <td class="px-4 py-2">{{ $template->default_print_mode->label() }}</td>
                        <td class="px-4 py-2">{{ $template->doctor?->name_en ?? 'Shared' }}</td>
                        <td class="px-4 py-2">
                            {{ $template->is_active ? 'Active' : 'Inactive' }}{{ $template->is_default ? ' · default' : '' }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            @can('templates.manage')
                                <a href="{{ route('settings.templates.edit', $template) }}" class="hover:underline">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
