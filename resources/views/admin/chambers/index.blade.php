@extends('layouts.app')

@section('title', __('Chambers'))

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ __('Chambers') }}</h1>
            <p class="text-sm text-gray-600">{!! __('Assign chambers to doctors under :link; each doctor sets their own visiting hours and fees.', ['link' => '<a href="'.e(route('admin.doctors.index')).'" class="underline">'.e(__('Doctors')).'</a>']) !!}</p>
        </div>
        <a href="{{ route('admin.chambers.create') }}" class="ml-auto rounded bg-teal-700 px-3 py-1.5 text-sm text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">{{ __('New chamber') }}</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">{{ __('Name') }}</th>
                    <th class="px-4 py-2">{{ __('Address') }}</th>
                    <th class="px-4 py-2">{{ __('Phone') }}</th>
                    <th class="px-4 py-2">{{ __('Branches') }}</th>
                    <th class="px-4 py-2">{{ __('Doctors') }}</th>
                    <th class="px-4 py-2">{{ __('Status') }}</th>
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
                        <td class="px-4 py-2">{{ $chamber->is_active ? __('Active') : __('Inactive') }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.chambers.edit', $chamber) }}" class="hover:underline">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('No chambers yet. Create the first one.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
