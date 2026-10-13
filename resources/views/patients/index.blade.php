@extends('layouts.app')

@section('title', __('Patients'))

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <h1 class="text-2xl font-semibold">{{ __('Patients') }}</h1>
        <form method="GET" class="ml-auto">
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Name, phone or code') }}" aria-label="{{ __('Search patients') }}"
                   class="rounded px-3 py-1.5 ring-1 ring-gray-300">
        </form>
        @can('create', \App\Domain\Clinical\Models\CaseHistory::class)
            {{-- No need to register first: the pad registers a new patient on save. --}}
            <a href="{{ route('prescriptions.create') }}" class="rounded bg-teal-700 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">{{ __('+ New prescription') }}</a>
        @endcan
        @can('create', \App\Domain\Patient\Models\Patient::class)
            <a href="{{ route('patients.create') }}" class="rounded px-3 py-1.5 text-sm text-teal-800 ring-1 ring-teal-700 transition hover:bg-teal-50">{{ __('Register patient') }}</a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">{{ __('Code') }}</th>
                    <th class="px-4 py-2">{{ __('Name') }}</th>
                    <th class="px-4 py-2">{{ __('Age') }}</th>
                    <th class="px-4 py-2">{{ __('Gender') }}</th>
                    <th class="px-4 py-2">{{ __('Phone') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($patients as $patient)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2 font-mono">{{ $patient->code }}</td>
                        <td class="px-4 py-2">{{ $patient->name }}</td>
                        <td class="px-4 py-2">{{ $patient->age_text }}</td>
                        <td class="px-4 py-2">{{ $patient->gender->label() }}</td>
                        <td class="px-4 py-2">{{ $patient->phone }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            @can('create', \App\Domain\Clinical\Models\CaseHistory::class)
                                <a href="{{ route('prescriptions.create', ['patient' => $patient->id]) }}" class="mr-3 rounded bg-teal-700 px-3 py-1 text-xs font-medium text-white shadow-sm transition hover:bg-teal-800">{{ __('Prescribe') }}</a>
                            @endcan
                            <a href="{{ route('patients.show', $patient) }}" class="hover:underline">{{ __('Open') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('No patients found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $patients->links() }}</div>
@endsection
