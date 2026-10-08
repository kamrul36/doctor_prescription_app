@extends('layouts.app')

@section('title', 'Advice templates')

@section('content')
    @include('catalog._tabs')

    <div class="mb-4 flex flex-wrap items-center gap-4">
        <h1 class="text-2xl font-semibold">Advice templates</h1>
        <form method="GET" class="ml-auto flex items-center gap-3">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search" aria-label="Search Advice templates"
                   class="rounded px-3 py-1.5 ring-1 ring-gray-300">
            @can('catalog.manage')
                <label class="flex items-center gap-1 text-sm text-gray-600">
                    <input type="checkbox" name="all" value="1" @checked($all) onchange="this.form.submit()"> Show inactive
                </label>
            @endcan
        </form>
        @can("create", \App\Domain\Catalog\Models\AdviceTemplate::class)
            <a href="{{ route('catalog.advice-templates.create') }}" class="rounded bg-teal-700 shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none px-3 py-1.5 text-sm text-white">New advice template</a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">Title</th>
                    <th class="px-4 py-2">Specialty</th>
                    <th class="px-4 py-2">Owner</th>
                    <th class="px-4 py-2">Default</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2 font-medium">{{ $item->title }}</td>
                        <td class="px-4 py-2">{{ config("practice.specialties")[$item->specialty_code] ?? $item->specialty_code }}</td>
                        <td class="px-4 py-2">{{ $item->doctor_id === null ? "Shared" : "Private" }}</td>
                        <td class="px-4 py-2">{{ $item->defaultForTemplate?->name ?? "—" }}</td>
                        <td class="px-4 py-2">{{ $item->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $item)
                                <a href="{{ route('catalog.advice-templates.edit', $item) }}" class="hover:underline">Edit</a>
                            @endcan
                            @can('delete', $item)
                                <form method="POST" action="{{ route('catalog.advice-templates.destroy', $item) }}" class="ml-3 inline" onsubmit="return confirm('Delete this entry?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-700 hover:underline">Delete</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-6 text-center text-gray-500">Nothing found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
@endsection
