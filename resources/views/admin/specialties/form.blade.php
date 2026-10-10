@extends('layouts.app')

@section('title', $specialty->exists ? 'Edit specialty' : 'New specialty')

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">{{ $specialty->exists ? 'Edit '.$specialty->name : 'New specialty' }}</h1>

    <form method="POST" action="{{ $specialty->exists ? route('admin.specialties.update', $specialty) : route('admin.specialties.store') }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @if ($specialty->exists)
            @method('PUT')
        @endif
        @include('admin._errors')
        @php($input = 'mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300')

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="block text-sm font-medium">Name</label>
                <input id="name" name="name" value="{{ old('name', $specialty->name) }}" required maxlength="100" class="{{ $input }}">
                @if ($specialty->exists)
                    <p class="mt-1 text-xs text-gray-500">Code <code>{{ $specialty->code }}</code> stays the same when you rename.</p>
                @endif
            </div>
            <div>
                <label for="block" class="block text-sm font-medium">Prescription block</label>
                <select id="block" name="block" class="{{ $input }}">
                    <option value="">None (only catalog suggestions)</option>
                    @foreach (\App\Domain\Practice\SpecialtyBlock::cases() as $block)
                        <option value="{{ $block->value }}" @selected(old('block', $specialty->block?->value) === $block->value)>{{ $block->label() }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Extra fields shown on the prescription pad for doctors with this specialty.</p>
            </div>
        </div>

        <label class="mt-4 flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $specialty->is_active ?? true))>
            Active (inactive specialties are hidden from profiles and catalog forms; doctors who have one keep it)
        </label>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded bg-teal-700 px-4 py-2 text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">Save</button>
            <a href="{{ route('admin.specialties.index') }}" class="px-4 py-2 text-gray-700">Cancel</a>
        </div>
    </form>
@endsection
