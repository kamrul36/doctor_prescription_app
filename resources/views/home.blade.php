@extends('layouts.app')

@section('title', 'Home')

@section('content')
    @php
        $user = auth()->user();
        $isPrescriber = $doctor !== null && $user->can('cases.write');
        $cards = [
            ['prescriptions.index', 'Prescriptions', 'Today\'s prescriptions, drafts and finalized ones.', 'cases.read'],
            ['patients.index', 'Patients', 'Find patients by name, phone or code, and prescribe.', 'patients.read'],
            ['settings.doctor.edit', 'My profile', 'Specialties, credentials, and your hours and fees per chamber.', 'templates.manage'],
            ['settings.templates.index', 'Prescription templates', 'Prescription layout, sections and print settings, in A4.', 'cases.read'],
            ['catalog.lab-tests.index', 'Catalogs', 'Lab tests, procedures and advice texts to pick from at a visit.', 'cases.read'],
            ['admin.chambers.index', 'Chambers', 'Create chambers with address and branches.', 'practice.manage'],
            ['admin.doctors.index', 'Doctors', 'Assign doctors to chambers.', 'practice.manage'],
            ['admin.specialties.index', 'Specialties', 'The specialty list doctors choose from.', 'practice.manage'],
            ['admin.users.index', 'Users', 'Create staff accounts and assign roles.', 'users.manage'],
            ['admin.roles.index', 'Roles', 'Decide what each role is allowed to do.', 'roles.manage'],
        ];
    @endphp

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-teal-700 to-teal-900 px-6 py-8 text-white shadow-sm sm:px-10">
        <p class="text-sm font-medium tracking-wide text-teal-100">{{ now()->format('l, j F Y') }}</p>
        <h1 class="mt-1 text-3xl font-semibold text-white!">Welcome, {{ $doctor?->name_en ?? $user->name }}</h1>
        @if ($isPrescriber)
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('prescriptions.create') }}" class="rounded-lg bg-white px-5 py-2.5 font-semibold text-teal-800 shadow-sm transition hover:bg-teal-50">+ New prescription</a>
                <a href="{{ route('patients.index') }}" class="rounded-lg px-5 py-2.5 font-medium text-white ring-1 ring-white/60 hover:bg-white/10">Prescribe for a registered patient</a>
            </div>
            @if ($doctor->activeChambers()->isEmpty())
                <p class="mt-3 text-sm text-amber-100">No chamber is assigned to you yet; ask the admin before writing prescriptions.</p>
            @endif
        @else
            <p class="mt-2 max-w-xl text-teal-50">Your chamber, prescriptions and patient history in one place.</p>
        @endif
    </section>

    @can('patients.read')
        <form method="GET" action="{{ route('patients.index') }}" class="mt-6">
            <input type="search" name="q" placeholder="Find a patient: name, phone or code" aria-label="Find a patient"
                   class="w-full rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-300 focus:ring-2 focus:ring-teal-600 focus:outline-none">
        </form>
    @endcan

    @if ($isPrescriber)
        <div class="mt-8 mb-3 flex items-center">
            <h2 class="text-sm font-semibold tracking-wide text-gray-500 uppercase">Today's prescriptions</h2>
            <a href="{{ route('prescriptions.index') }}" class="ml-auto text-sm text-teal-800 hover:underline">All</a>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            @forelse ($today as $case)
                <a href="{{ $case->isDraft() ? route('prescriptions.edit', $case) : route('prescriptions.show', $case) }}"
                   class="flex items-center gap-3 border-b px-4 py-2.5 text-sm last:border-0 hover:bg-gray-50">
                    <span class="w-6 text-gray-500">{{ $case->visit_no_today }}</span>
                    <span class="font-medium">{{ $case->patient->name }}</span>
                    <span class="font-mono text-xs text-gray-500">{{ $case->patient->code }}</span>
                    <span @class(['ml-auto rounded px-2 py-0.5 text-xs', 'bg-amber-50 text-amber-800' => $case->isDraft(), 'bg-emerald-50 text-emerald-800' => ! $case->isDraft()])>
                        {{ $case->isDraft() ? 'Draft' : $case->prescription_no }}
                    </span>
                </a>
            @empty
                <p class="px-4 py-6 text-center text-sm text-gray-500">No prescriptions yet today.</p>
            @endforelse
        </div>
    @endif

    <h2 class="mt-8 mb-3 text-sm font-semibold tracking-wide text-gray-500 uppercase">Quick links</h2>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as [$route, $title, $text, $permission])
            @if ($permission === null || $user->can($permission))
                <a href="{{ route($route) }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-teal-300 hover:shadow-md">
                    <h3 class="font-semibold text-gray-900 group-hover:text-teal-800">{{ $title }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $text }}</p>
                </a>
            @endif
        @endforeach
    </div>
@endsection
