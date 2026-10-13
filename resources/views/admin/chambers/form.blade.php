@extends('layouts.app')

@section('title', $chamber->exists ? __('Edit chamber') : __('New chamber'))

@section('content')
    @php
        $branches = old('branches', $chamber->exists ? $chamber->branches->map(fn ($b) => [
            'name_en' => $b->name_en, 'name_bn' => $b->name_bn, 'phones' => implode(', ', $b->phones ?? []),
        ])->all() : []);
        $branches = array_map(fn ($b) => [
            'name_en' => $b['name_en'] ?? '', 'name_bn' => $b['name_bn'] ?? '',
            'phones' => is_array($b['phones'] ?? null) ? implode(', ', $b['phones']) : ($b['phones'] ?? ''),
        ], array_values($branches));
        $input = 'mt-2 w-full rounded px-3 py-2 ring-1 ring-gray-300';
    @endphp

    <h1 class="mb-4 text-2xl font-semibold">{{ $chamber->exists ? __('Edit :name', ['name' => $chamber->localized('name')]) : __('New chamber') }}</h1>

    {{-- Rules mirror SaveChamberRequest. --}}
    <form method="POST" action="{{ $chamber->exists ? route('admin.chambers.update', $chamber) : route('admin.chambers.store') }}"
          x-data="validatedForm(@js($errors->toArray()))" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        <x-form-errors />
        @if ($chamber->exists)
            @method('PUT')
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name_en" class="block text-base font-medium">{{ __('Name (English)') }} <x-required-mark /></label>
                <input id="name_en" name="name_en" value="{{ old('name_en', $chamber->name_en) }}" required maxlength="255" class="{{ $input }}">
                <x-field-error for="name_en" />
            </div>
            <div>
                <label for="name_bn" class="block text-base font-medium">{{ __('Name (Bangla)') }}</label>
                <input id="name_bn" name="name_bn" value="{{ old('name_bn', $chamber->name_bn) }}" maxlength="255" class="{{ $input }}">
                <x-field-error for="name_bn" />
            </div>
            <div>
                <label for="address_en" class="block text-base font-medium">{{ __('Address (English)') }}</label>
                <textarea id="address_en" name="address_en" rows="2" maxlength="1000" class="{{ $input }}">{{ old('address_en', $chamber->address_en) }}</textarea>
                <x-field-error for="address_en" />
            </div>
            <div>
                <label for="address_bn" class="block text-base font-medium">{{ __('Address (Bangla)') }}</label>
                <textarea id="address_bn" name="address_bn" rows="2" maxlength="1000" class="{{ $input }}">{{ old('address_bn', $chamber->address_bn) }}</textarea>
                <x-field-error for="address_bn" />
            </div>
            <div>
                <label for="phone" class="block text-base font-medium">{{ __('Phone') }}</label>
                <input id="phone" name="phone" value="{{ old('phone', $chamber->phone) }}" maxlength="64" data-rule="phone-list" data-max-items="4" placeholder="e.g. 01711-000000, 10666" class="{{ $input }}">
                <x-field-error for="phone" />
            </div>
            <div>
                <label for="email" class="block text-base font-medium">{{ __('Email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email', $chamber->email) }}" maxlength="255" class="{{ $input }}">
                <x-field-error for="email" />
            </div>
        </div>

        <label class="mt-4 flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $chamber->is_active ?? true))>
            {{ __('Active (an inactive chamber can\'t be assigned or chosen on the prescription pad)') }}
        </label>

        <div x-data="rowList(@js($branches), { name_en: '', name_bn: '', phones: '' })">
            <div class="mt-8 flex items-center gap-3">
                <h2 class="text-lg font-semibold">{{ __('Branches') }}</h2>
                <button type="button" @click="add()" class="rounded px-2 py-1 text-sm text-teal-800 ring-1 ring-teal-700 hover:bg-teal-50">{{ __('+ Add branch') }}</button>
            </div>
            <p class="text-xs text-gray-500">{{ __('Printed in the footer when the template shows branches. Separate phone numbers with commas.') }}</p>
            {{-- Always send the key, so removing every row clears the list. --}}
            <input type="hidden" name="branches" value="" x-bind:disabled="rows.length > 0">
            <x-field-error for="branches" />
            <div class="mt-2 space-y-2">
                <template x-for="(row, i) in rows" :key="row._key">
                    <div data-row class="grid gap-2 sm:grid-cols-[1fr_1fr_1fr_auto]">
                        <input :name="`branches[${i}][name_en]`" x-model="row.name_en" maxlength="255" placeholder="{{ __('Name (English)') }}" class="rounded px-3 py-2 ring-1 ring-gray-300">
                        <input :name="`branches[${i}][name_bn]`" x-model="row.name_bn" maxlength="255" placeholder="{{ __('Name (Bangla)') }}" class="rounded px-3 py-2 ring-1 ring-gray-300">
                        <input :name="`branches[${i}][phones]`" x-model="row.phones" data-rule="phone-list" placeholder="{{ __('Phones') }}" class="rounded px-3 py-2 ring-1 ring-gray-300">
                        <button type="button" @click="remove(i)" class="px-2 text-sm text-red-700 hover:underline" aria-label="{{ __('Remove branch') }}">{{ __('Remove') }}</button>
                        <p x-show="rowErr(`branches.${i}`)" class="col-span-full text-sm text-red-700" x-text="rowErr(`branches.${i}`)"></p>
                    </div>
                </template>
                <p x-show="rows.length === 0" class="text-sm text-gray-500">{{ __('No branches.') }}</p>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded bg-teal-700 px-4 py-2 text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">{{ __('Save') }}</button>
            <a href="{{ route('admin.chambers.index') }}" class="px-4 py-2 text-gray-700">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
