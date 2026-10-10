@extends('layouts.app')

@section('title', 'Assign chambers')

@section('content')
    @php
        $oldIds = old('chamber_ids');
        $ticked = is_array($oldIds)
            ? (array_is_list($oldIds) ? array_map('intval', $oldIds) : array_map('intval', array_keys(array_filter($oldIds, fn ($v) => $v === '1'))))
            : $assigned;
    @endphp

    <h1 class="mb-1 text-2xl font-semibold">Chambers for {{ $user->doctor?->name_en ?? $user->name }}</h1>
    <p class="mb-4 text-sm text-gray-600">Unticking a chamber also removes this doctor's fees for it.</p>

    <form method="POST" action="{{ route('admin.doctors.update', $user) }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('admin._errors')

        @if ($chambers->isEmpty())
            <p class="text-sm text-gray-600">No chambers yet. <a href="{{ route('admin.chambers.create') }}" class="underline">Create one</a> first.</p>
        @endif

        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($chambers as $chamber)
                <label class="flex items-start gap-2 rounded border border-gray-200 p-3 text-sm">
                    <input type="hidden" name="chamber_ids[{{ $chamber->id }}]" value="0">
                    <input type="checkbox" name="chamber_ids[{{ $chamber->id }}]" value="1" class="mt-0.5" @checked(in_array($chamber->id, $ticked, true))>
                    <span>
                        <span class="font-medium">{{ $chamber->name_en }}</span>
                        @unless ($chamber->is_active)
                            <span class="text-xs text-gray-500">(inactive)</span>
                        @endunless
                        <span class="block text-xs text-gray-500">{{ $chamber->address_en }}</span>
                    </span>
                </label>
            @endforeach
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded bg-teal-700 px-4 py-2 text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">Save</button>
            <a href="{{ route('admin.doctors.index') }}" class="px-4 py-2 text-gray-700">Cancel</a>
        </div>
    </form>
@endsection
