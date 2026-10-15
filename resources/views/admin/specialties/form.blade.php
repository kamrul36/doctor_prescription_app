@extends('layouts.app')

@section('title', $specialty->exists ? __('Edit specialty') : __('New specialty'))

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">{{ $specialty->exists ? __('Edit :name', ['name' => $specialty->name]) : __('New specialty') }}</h1>

    <form method="POST" action="{{ $specialty->exists ? route('admin.specialties.update', $specialty) : route('admin.specialties.store') }}"
          x-data="validatedForm(@js($errors->toArray()))" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        <x-form-errors />
        @if ($specialty->exists)
            @method('PUT')
        @endif
        @php($input = 'mt-2 w-full rounded px-3 py-2 ring-1 ring-gray-300')

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="block text-base font-medium">{{ __('Name') }} <x-required-mark /></label>
                <input id="name" name="name" value="{{ old('name', $specialty->name) }}" required minlength="2" maxlength="100" class="{{ $input }}">
                <x-field-error for="name" />
                @if ($specialty->exists)
                    <p class="mt-1 text-sm text-gray-500">{!! __('Code :code stays the same when you rename.', ['code' => '<code>'.e($specialty->code).'</code>']) !!}</p>
                @endif
            </div>
        </div>

        <label class="mt-4 flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $specialty->is_active ?? true))>
            {{ __('Active (inactive specialties are hidden from profiles and catalog forms; doctors who have one keep it)') }}
        </label>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded bg-teal-700 px-4 py-2 text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">{{ __('Save') }}</button>
            <a href="{{ route('admin.specialties.index') }}" class="px-4 py-2 text-gray-700">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
