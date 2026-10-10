@extends('layouts.app')

@section('title', $chamber->exists ? 'Edit chamber' : 'New chamber')

@section('content')
    @php
        $branches = old('branches', $chamber->exists ? $chamber->branches->map(fn ($b) => [
            'name_en' => $b->name_en, 'name_bn' => $b->name_bn, 'phones' => implode(', ', $b->phones ?? []),
        ])->all() : []);
        $branches = array_map(fn ($b) => [
            'name_en' => $b['name_en'] ?? '', 'name_bn' => $b['name_bn'] ?? '',
            'phones' => is_array($b['phones'] ?? null) ? implode(', ', $b['phones']) : ($b['phones'] ?? ''),
        ], array_values($branches));
        $input = 'mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300';
    @endphp

    <h1 class="mb-4 text-2xl font-semibold">{{ $chamber->exists ? 'Edit '.$chamber->name_en : 'New chamber' }}</h1>

    <form method="POST" action="{{ $chamber->exists ? route('admin.chambers.update', $chamber) : route('admin.chambers.store') }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @if ($chamber->exists)
            @method('PUT')
        @endif
        @include('admin._errors')

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name_en" class="block text-sm font-medium">Name (English)</label>
                <input id="name_en" name="name_en" value="{{ old('name_en', $chamber->name_en) }}" required class="{{ $input }}">
            </div>
            <div>
                <label for="name_bn" class="block text-sm font-medium">Name (Bangla)</label>
                <input id="name_bn" name="name_bn" value="{{ old('name_bn', $chamber->name_bn) }}" class="{{ $input }}">
            </div>
            <div>
                <label for="address_en" class="block text-sm font-medium">Address (English)</label>
                <textarea id="address_en" name="address_en" rows="2" class="{{ $input }}">{{ old('address_en', $chamber->address_en) }}</textarea>
            </div>
            <div>
                <label for="address_bn" class="block text-sm font-medium">Address (Bangla)</label>
                <textarea id="address_bn" name="address_bn" rows="2" class="{{ $input }}">{{ old('address_bn', $chamber->address_bn) }}</textarea>
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium">Phone</label>
                <input id="phone" name="phone" value="{{ old('phone', $chamber->phone) }}" class="{{ $input }}">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $chamber->email) }}" class="{{ $input }}">
            </div>
        </div>

        <label class="mt-4 flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $chamber->is_active ?? true))>
            Active (an inactive chamber can't be assigned or chosen on the prescription pad)
        </label>

        <div x-data="rowList(@js($branches), { name_en: '', name_bn: '', phones: '' })">
            <div class="mt-8 flex items-center gap-3">
                <h2 class="text-lg font-semibold">Branches</h2>
                <button type="button" @click="add()" class="rounded px-2 py-1 text-sm text-teal-800 ring-1 ring-teal-700 hover:bg-teal-50">+ Add branch</button>
            </div>
            <p class="text-xs text-gray-500">Printed in the footer when the template shows branches. Separate phone numbers with commas.</p>
            {{-- Always send the key, so removing every row clears the list. --}}
            <input type="hidden" name="branches" value="" x-bind:disabled="rows.length > 0">
            <div class="mt-2 space-y-2">
                <template x-for="(row, i) in rows" :key="row._key">
                    <div class="grid gap-2 sm:grid-cols-[1fr_1fr_1fr_auto]">
                        <input :name="`branches[${i}][name_en]`" x-model="row.name_en" placeholder="Name (English)" class="rounded px-3 py-2 ring-1 ring-gray-300">
                        <input :name="`branches[${i}][name_bn]`" x-model="row.name_bn" placeholder="Name (Bangla)" class="rounded px-3 py-2 ring-1 ring-gray-300">
                        <input :name="`branches[${i}][phones]`" x-model="row.phones" placeholder="Phones" class="rounded px-3 py-2 ring-1 ring-gray-300">
                        <button type="button" @click="remove(i)" class="px-2 text-sm text-red-700 hover:underline" aria-label="Remove branch">Remove</button>
                    </div>
                </template>
                <p x-show="rows.length === 0" class="text-sm text-gray-500">No branches.</p>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded bg-teal-700 px-4 py-2 text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">Save</button>
            <a href="{{ route('admin.chambers.index') }}" class="px-4 py-2 text-gray-700">Cancel</a>
        </div>
    </form>
@endsection
