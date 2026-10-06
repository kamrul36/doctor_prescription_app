@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <div class="mx-auto mt-16 max-w-sm rounded-lg border bg-white p-6 shadow-sm">
        <h1 class="mb-6 text-xl font-semibold">{{ config('app.name') }}</h1>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="mt-1 w-full rounded border-gray-300 px-3 py-2 ring-1 ring-gray-300">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="mt-1 w-full rounded border-gray-300 px-3 py-2 ring-1 ring-gray-300">
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                Remember me
            </label>

            <button type="submit" class="w-full rounded bg-gray-900 px-4 py-2 text-white hover:bg-gray-700">Log in</button>
        </form>
    </div>
@endsection
