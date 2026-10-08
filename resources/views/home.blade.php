@extends('layouts.app')

@section('title', 'Home')

@section('content')
    @php
        $user = auth()->user();
        $cards = [
            ['patients.index', 'Patients', 'Register patients and find them by name, phone or code.', 'patients.read'],
            ['settings.chamber.edit', 'Chamber', 'Name, address and branches printed on prescriptions.', 'templates.manage'],
            ['settings.doctor.edit', 'My profile', 'Credentials, registration no., visiting hours and fees.', 'templates.manage'],
            ['settings.templates.index', 'Prescription templates', 'Layouts for each specialty and pad, in A4.', 'cases.read'],
            ['catalog.lab-tests.index', 'Catalogs', 'Lab tests, procedures and advice texts to pick from at a visit.', 'cases.read'],
            ['admin.users.index', 'Users', 'Create staff accounts and assign roles.', 'users.manage'],
            ['admin.roles.index', 'Roles', 'Decide what each role is allowed to do.', 'roles.manage'],
        ];
    @endphp

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-teal-700 to-teal-900 px-6 py-8 text-white shadow-sm sm:px-10">
        <p class="text-sm font-medium tracking-wide text-teal-100">{{ now()->format('l, j F Y') }}</p>
        <h1 class="mt-1 text-3xl font-semibold text-white!">Welcome, {{ $user->name }}</h1>
        <p class="mt-2 max-w-xl text-teal-50">Your chamber, prescriptions and patient history in one place.</p>
    </section>

    @can('patients.read')
        <form method="GET" action="{{ route('patients.index') }}" class="mt-6">
            <input type="search" name="q" placeholder="Find a patient: name, phone or code" aria-label="Find a patient"
                   class="w-full rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-300 focus:ring-2 focus:ring-teal-600 focus:outline-none">
        </form>
    @endcan

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
