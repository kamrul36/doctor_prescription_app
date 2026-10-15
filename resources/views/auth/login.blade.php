@extends('layouts.app')

@section('title', __('Log in'))

@section('content')
    <div class="mx-auto mt-6 grid max-w-4xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg md:mt-12 md:grid-cols-2">
        <div class="hidden flex-col justify-between bg-gradient-to-br from-teal-700 to-teal-900 p-10 text-white md:flex">
            @include('partials.logo', ['class' => 'h-12 w-12'])
            <div>
                <h1 class="text-4xl font-semibold text-white!">{{ config('app.name') }}</h1>
                <p class="mt-3 text-lg text-teal-50">{{ __('Prescriptions, patient history and chamber accounts, all in one place.') }}</p>
            </div>
            <p class="text-xs text-teal-200">{{ __('For authorised chamber staff only.') }}</p>
        </div>

        <div class="p-8 sm:p-10">
            <div class="mb-6 flex items-center gap-2 md:hidden">
                @include('partials.logo', ['class' => 'h-9 w-9'])
                <span class="text-xl font-semibold text-teal-800">{{ config('app.name') }}</span>
            </div>
            <h2 class="text-2xl font-semibold text-gray-900">{{ __('Sign in') }}</h2>
            <p class="mt-1 mb-6 text-sm text-gray-600">{{ __('Enter your account details to continue.') }}</p>

            <form method="POST" action="{{ route('login.store') }}" x-data="validatedForm(@js($errors->toArray()))" class="space-y-4">
                @csrf
                <x-form-errors />

                <div>
                    <label for="login" class="block text-base font-medium">{{ __('Email or phone') }} <x-required-mark /></label>
                    <input id="login" name="login" type="text" value="{{ old('login') }}" required maxlength="255" autofocus autocomplete="username"
                           class="mt-2 w-full px-3 py-2 ring-1 ring-gray-300">
                    <x-field-error for="login"/>
                </div>

                <div>
                    <label for="password" class="block text-base font-medium">{{ __('Password') }} <x-required-mark /></label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="mt-2 w-full px-3 py-2 ring-1 ring-gray-300">
                    <x-field-error for="password"/>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    {{ __('Remember me') }}
                </label>

                <button type="submit" class="w-full rounded-md bg-teal-700 px-4 py-2.5 font-medium text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">{{ __('Log in') }}</button>
            </form>
        </div>
    </div>
@endsection
