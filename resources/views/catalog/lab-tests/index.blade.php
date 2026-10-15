@extends('layouts.app')

@section('title', __('Lab tests'))

@section('content')
    @include('catalog._tabs')

    <div class="mb-4 flex flex-wrap items-center gap-4">
        <h1 class="text-2xl font-semibold">{{ __('Lab tests') }}</h1>
        <form method="GET" class="ml-auto flex items-center gap-3">
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Search') }}" aria-label="{{ __('Search Lab tests') }}"
                   class="rounded px-3 py-1.5 ring-1 ring-gray-300">
            @include('catalog._specialty_filter')
            @can('catalog.manage')
                <label class="flex items-center gap-1 text-sm text-gray-600">
                    <input type="checkbox" name="all" value="1" @checked($all) x-data @change="$el.form.submit()"> {{ __('Show inactive') }}
                </label>
            @endcan
        </form>
        @can("create", \App\Domain\Catalog\Models\LabTest::class)
            <a href="{{ route('catalog.lab-tests.create') }}" class="rounded bg-teal-700 shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none px-3 py-1.5 text-sm text-white">{{ __('New lab test') }}</a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">{{ __('Name') }}</th>
                    <th class="px-4 py-2">{{ __('Category') }}</th>
                    <th class="px-4 py-2">{{ __('Specialty') }}</th>
                    <th class="px-4 py-2">{{ __('Usual timing') }}</th>
                    <th class="px-4 py-2">{{ __('Status') }}</th>
                    <th class="px-4 py-2">{{ __('Updated by') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2 font-medium">{{ $item->name }}</td>
                        <td class="px-4 py-2">{{ $item->category }}</td>
                        <td class="px-4 py-2">{{ \App\Domain\Practice\Models\Specialty::labelFor($item->specialty) }}</td>
                        <td class="px-4 py-2">{{ $item->default_timing_note }}</td>
                        <td class="px-4 py-2">{{ $item->is_active ? __('Active') : __('Inactive') }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $item->updater?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $item)
                                <a href="{{ route('catalog.lab-tests.edit', $item) }}" class="hover:underline">{{ __('Edit') }}</a>
                            @endcan
                            @can('delete', $item)
                                <form method="POST" action="{{ route('catalog.lab-tests.destroy', $item) }}" class="ml-3 inline" onsubmit="return confirm(@js(__('Delete this entry?')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-700 hover:underline">{{ __('Delete') }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-6 text-center text-gray-500">{{ __('Nothing found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
@endsection
