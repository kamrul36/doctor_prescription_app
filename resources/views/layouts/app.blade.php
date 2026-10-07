<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Welcome') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-screen flex-col bg-slate-50 text-gray-900">
    @auth
        @php
            $links = [
                ['settings.chamber.edit', 'settings.chamber.*', 'Chamber', 'templates.manage'],
                ['settings.doctor.edit', 'settings.doctor.*', 'My profile', 'templates.manage'],
                ['settings.templates.index', 'settings.templates.*', 'Templates', 'cases.read'],
                ['admin.users.index', 'admin.users.*', 'Users', 'users.manage'],
                ['admin.roles.index', 'admin.roles.*', 'Roles', 'roles.manage'],
            ];
        @endphp
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white/90 backdrop-blur">
            <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-1 gap-y-2 px-4 py-2.5">
                <a href="{{ route('home') }}" class="mr-4 flex items-center gap-2 font-semibold text-teal-800">
                    @include('partials.logo', ['class' => 'h-7 w-7'])
                    {{ config('app.name') }}
                </a>

                @foreach ($links as [$route, $pattern, $label, $permission])
                    @if (auth()->user()->can($permission) || ($route === 'settings.templates.index' && auth()->user()->can('templates.manage')))
                        <a href="{{ route($route) }}"
                               @class([
                                   'rounded-md px-3 py-1.5 text-sm font-medium transition',
                                   'bg-teal-50 text-teal-800' => request()->routeIs($pattern),
                                   'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => ! request()->routeIs($pattern),
                               ])>{{ $label }}</a>
                    @endif
                @endforeach

                <div class="ml-auto flex items-center gap-3 text-sm">
                    <span class="hidden items-center gap-2 text-gray-600 sm:flex">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-teal-100 text-xs font-semibold text-teal-800">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        {{ auth()->user()->name }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md px-3 py-1.5 font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900">Log out</button>
                    </form>
                </div>
            </nav>
        </header>
    @endauth

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        @if (session('status'))
            <div class="mb-5 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-gray-200 py-4 text-center text-xs text-gray-500">
        {{ config('app.name') }} &middot; Chamber management
    </footer>

    @stack('scripts')
</body>
</html>
