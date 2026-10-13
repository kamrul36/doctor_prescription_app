@extends('layouts.app')

@section('title', $item->exists ? __('Edit procedure') : __('New procedure'))

@section('content')
    @include('catalog._tabs')

    <h1 class="mb-4 text-2xl font-semibold">{{ $item->exists ? __('Edit procedure') : __('New procedure') }}</h1>

    <form method="POST" action="{{ $item->exists ? route('catalog.procedures.update', $item) : route('catalog.procedures.store') }}"
          x-data="validatedForm(@js($errors->toArray()))" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        <x-form-errors />
        @if ($item->exists)
            @method('PUT')
        @endif
        @php($input = 'mt-2 w-full rounded px-3 py-2 ring-1 ring-gray-300')

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="code" class="block text-base font-medium">{{ __('Code') }} <x-required-mark /></label>
                <input id="code" name="code" value="{{ old('code', $item->code) }}" required maxlength="32" class="{{ $input }} uppercase"
                       data-pattern="\s*[A-Za-z0-9_\-]{2,32}\s*" data-pattern-message="{{ __('Use 2 to 32 letters, digits, dashes or underscores.') }}">
                <x-field-error for="code" />
                <p class="mt-1 text-sm text-gray-500">{{ __('Letters, digits, dashes or underscores. Must be unique, even against deleted procedures.') }}</p>
            </div>
            <div>
                <label for="default_fee" class="block text-base font-medium">{{ __('Default fee (৳)') }}</label>
                <input id="default_fee" name="default_fee" inputmode="decimal" value="{{ old('default_fee', $item->default_fee?->toDecimal()) }}" data-rule="money" placeholder="{{ __('Leave empty if it varies') }}" class="{{ $input }}">
                <x-field-error for="default_fee" />
            </div>
            <div>
                <label for="name_en" class="block text-base font-medium">{{ __('Name (English)') }} <x-required-mark /></label>
                <input id="name_en" name="name_en" value="{{ old('name_en', $item->name_en) }}" required maxlength="255" class="{{ $input }}">
                <x-field-error for="name_en" />
            </div>
            <div>
                <label for="name_bn" class="block text-base font-medium">{{ __('Name (Bangla)') }}</label>
                <input id="name_bn" name="name_bn" value="{{ old('name_bn', $item->name_bn) }}" maxlength="255" class="{{ $input }}">
                <x-field-error for="name_bn" />
            </div>
            @include('catalog._specialty_field')
        </div>
        <label class="mt-4 flex items-center gap-2 text-sm">
            <input type="hidden" name="is_billable" value="0">
            <input type="checkbox" name="is_billable" value="1" @checked(old('is_billable', $item->is_billable ?? true))>
            {{ __('Billable (added to the invoice when it has a fee)') }}
        </label>

        <label class="mt-4 flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true))>
            {{ __('Active (inactive entries are hidden from search)') }}
        </label>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded bg-teal-700 px-4 py-2 text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">{{ __('Save') }}</button>
            <a href="{{ route('catalog.procedures.index') }}" class="px-4 py-2 text-gray-700">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection

