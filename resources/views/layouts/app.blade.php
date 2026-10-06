<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    @auth
        <header class="border-b bg-white">
            <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
                <a href="{{ route('home') }}" class="font-semibold">{{ config('app.name') }}</a>

                @can('users.manage')
                    <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-700 hover:underline">Users</a>
                @endcan
                @can('roles.manage')
                    <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-700 hover:underline">Roles</a>
                @endcan

                <div class="ml-auto flex items-center gap-4 text-sm">
                    <span class="text-gray-600">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-gray-700 hover:underline">Log out</button>
                    </form>
                </div>
            </nav>
        </header>
    @endauth

    <main class="mx-auto max-w-6xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
