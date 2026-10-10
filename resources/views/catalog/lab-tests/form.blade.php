@extends('layouts.app')

@section('title', $item->exists ? 'Edit lab test' : 'New lab test')

@section('content')
    @include('catalog._tabs')

    <h1 class="mb-4 text-2xl font-semibold">{{ $item->exists ? 'Edit lab test' : 'New lab test' }}</h1>

    <form method="POST" action="{{ $item->exists ? route('catalog.lab-tests.update', $item) : route('catalog.lab-tests.store') }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @if ($item->exists)
            @method('PUT')
        @endif
        @include('admin._errors')
        @php($input = 'mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300')

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="block text-sm font-medium">Name</label>
                <input id="name" name="name" value="{{ old('name', $item->name) }}" required maxlength="255" class="{{ $input }}">
            </div>
            <div>
                <label for="category" class="block text-sm font-medium">Category</label>
                <input id="category" name="category" value="{{ old('category', $item->category) }}" maxlength="100" class="{{ $input }}">
            </div>
            <div>
                <label for="default_timing_note" class="block text-sm font-medium">Usual timing note</label>
                <input id="default_timing_note" name="default_timing_note" value="{{ old('default_timing_note', $item->default_timing_note) }}" maxlength="100" placeholder="e.g. fasting, D2" class="{{ $input }}">
            </div>
            @include('catalog._specialty_field')
        </div>

        <label class="mt-4 flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true))>
            Active (inactive entries are hidden from search)
        </label>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded bg-teal-700 px-4 py-2 text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">Save</button>
            <a href="{{ route('catalog.lab-tests.index') }}" class="px-4 py-2 text-gray-700">Cancel</a>
        </div>
    </form>
@endsection

