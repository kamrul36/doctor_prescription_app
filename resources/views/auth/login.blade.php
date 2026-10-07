@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <div class="mx-auto mt-6 grid max-w-4xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg md:mt-12 md:grid-cols-2">
        <div class="hidden flex-col justify-between bg-gradient-to-br from-teal-700 to-teal-900 p-10 text-white md:flex">
            @include('partials.logo', ['class' => 'h-12 w-12'])
            <div>
                <h1 class="text-4xl font-semibold text-white!">{{ config('app.name') }}</h1>
                <p class="mt-3 text-lg text-teal-50">Prescriptions, patient history and chamber accounts, all in one place.</p>
            </div>
            <p class="text-xs text-teal-200">For authorised chamber staff only.</p>
        </div>

        <div class="p-8 sm:p-10">
            <div class="mb-6 flex items-center gap-2 md:hidden">
                @include('partials.logo', ['class' => 'h-9 w-9'])
                <span class="text-xl font-semibold text-teal-800">{{ config('app.name') }}</span>
            </div>
            <h2 class="text-2xl font-semibold text-gray-900">Sign in</h2>
            <p class="mt-1 mb-6 text-sm text-gray-600">Enter your account details to continue.</p>

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           class="mt-1 w-full px-3 py-2 ring-1 ring-gray-300">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="mt-1 w-full px-3 py-2 ring-1 ring-gray-300">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    Remember me
                </label>

                <button type="submit" class="w-full rounded-md bg-teal-700 px-4 py-2.5 font-medium text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">Log in</button>
            </form>
        </div>
    </div>
@endsection
