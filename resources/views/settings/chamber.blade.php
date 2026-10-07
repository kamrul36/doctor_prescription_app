@extends('layouts.app')

@section('title', 'Chamber')

@section('content')
    @php
        $branches = old('branches', $chamber->branches->map(fn ($b) => [
            'name_en' => $b->name_en, 'name_bn' => $b->name_bn, 'phones' => implode(', ', $b->phones ?? []),
        ])->all());
        $branches = array_pad(array_values($branches), count($branches) + 2, []);
        $input = 'mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300';
    @endphp

    <h1 class="mb-4 text-2xl font-semibold">Chamber</h1>

    <form method="POST" action="{{ route('settings.chamber.update') }}" class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
        @csrf
        @method('PUT')
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

        <h2 class="mt-8 text-lg font-semibold">Branches</h2>
        <p class="text-xs text-gray-500">Printed in the footer when the template shows branches. Leave a row empty to remove it; separate phone numbers with commas.</p>
        <div class="mt-2 space-y-2">
            @foreach ($branches as $i => $branch)
                <div class="grid gap-2 sm:grid-cols-3">
                    <input name="branches[{{ $i }}][name_en]" value="{{ $branch['name_en'] ?? '' }}" placeholder="Name (English)" class="rounded px-3 py-2 ring-1 ring-gray-300">
                    <input name="branches[{{ $i }}][name_bn]" value="{{ $branch['name_bn'] ?? '' }}" placeholder="Name (Bangla)" class="rounded px-3 py-2 ring-1 ring-gray-300">
                    <input name="branches[{{ $i }}][phones]" value="{{ is_array($branch['phones'] ?? null) ? implode(', ', $branch['phones']) : ($branch['phones'] ?? '') }}" placeholder="Phones" class="rounded px-3 py-2 ring-1 ring-gray-300">
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            <button type="submit" class="rounded bg-teal-700 shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none px-4 py-2 text-white">Save</button>
        </div>
    </form>
@endsection
