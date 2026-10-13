<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Welcome')) · {{ config('app.name') }}</title>
    @include('partials.fonts')
    {{-- UI strings for the scripts (validation messages, toasts): the JSON translations of the current language. --}}
    <script>window.APP_I18N = @js(app()->getLocale() === 'en' ? new stdClass : app('translator')->getLoader()->load(app()->getLocale(), '*', '*'));</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-screen flex-col bg-slate-50 text-gray-900">
    @auth
        @php
            // Doctor work first (prescriptions, patients), then setup; admin links only for practice.manage / users.manage.
            $links = [
                ['prescriptions.index', 'prescriptions.*', __('Prescriptions'), 'cases.read'],
                ['patients.index', 'patients.*', __('Patients'), 'patients.read'],
                ['catalog.lab-tests.index', 'catalog.*', __('Catalogs'), 'cases.read'],
                ['settings.templates.index', 'settings.templates.*', __('Templates'), 'cases.read'],
                ['settings.doctor.edit', 'settings.doctor.*', __('My profile'), 'templates.manage'],
                ['admin.chambers.index', 'admin.chambers.*', __('Chambers'), 'practice.manage'],
                ['admin.doctors.index', 'admin.doctors.*', __('Doctors'), 'practice.manage'],
                ['admin.specialties.index', 'admin.specialties.*', __('Specialties'), 'practice.manage'],
                ['admin.users.index', 'admin.users.*', __('Users'), 'users.manage'],
                ['admin.roles.index', 'admin.roles.*', __('Roles'), 'roles.manage'],
            ];
        @endphp
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white/90 backdrop-blur">
            <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-1 gap-y-2 px-4 py-2.5">
                <a href="{{ route('home') }}" class="mr-4 flex items-center gap-2 font-semibold text-teal-800">
                    @include('partials.logo', ['class' => 'h-7 w-7'])
                    {{ config('app.name') }}
                </a>

                @foreach ($links as [$route, $pattern, $label, $permission])
                    @if (auth()->user()->can($permission) || ($route === 'settings.templates.index' && auth()->user()->can('templates.manage')) || ($route === 'catalog.lab-tests.index' && auth()->user()->can('catalog.manage')))
                        <a href="{{ route($route) }}"
                               @class([
                                   'rounded-md px-3 py-1.5 text-sm font-medium transition',
                                   'bg-teal-50 text-teal-800' => request()->routeIs($pattern),
                                   'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => ! request()->routeIs($pattern),
                               ])>{{ $label }}</a>
                    @endif
                @endforeach

                <div class="ml-auto flex items-center gap-3 text-sm">
                    @include('partials.locale-switch')
                    <span class="hidden items-center gap-2 text-gray-600 sm:flex">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-teal-100 text-xs font-semibold text-teal-800">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        {{ auth()->user()->name }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md px-3 py-1.5 font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900">{{ __('Log out') }}</button>
                    </form>
                </div>
            </nav>
        </header>
    @endauth

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        @auth
            @unless (request()->routeIs('home'))
                {{-- Goes to the page the user came from; the href is the fallback when there is no history. --}}
                <a href="{{ url()->previous(route('home')) }}" onclick="if (history.length > 1) { history.back(); return false; }"
                   class="mb-4 inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.7 4.3a1 1 0 010 1.4L8.4 10l4.3 4.3a1 1 0 11-1.4 1.4l-5-5a1 1 0 010-1.4l5-5a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                    {{ __('Back') }}
                </a>
            @endunless
        @endauth
        @yield('content')
    </main>

    {{-- Flash messages become toasts (spec §10.1). `status` is the older name for success. --}}
    @php
        $toasts = array_filter([
            'success' => session('success') ?? session('status'),
            'warning' => session('warning'),
            'error' => session('error') ?? match (true) {
                session('warning') !== null || $errors->isEmpty() => null,
                $errors->count() === 1 => $errors->first(),
                default => __('Please fix the :count highlighted fields.', ['count' => $errors->count()]),
            },
        ]);
    @endphp
    @if ($toasts)
        <script type="module">
            @foreach ($toasts as $type => $message)
                window.toast?.{{ $type }}(@js($message));
            @endforeach
        </script>
    @endif

    <footer class="border-t border-gray-200 py-4 text-center text-xs text-gray-500">
        {{ config('app.name') }} &middot; {{ __('Chamber management') }}
    </footer>

    @stack('scripts')
</body>
</html>
