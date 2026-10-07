@extends('layouts.app')

@section('title', $patient->name)

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">{{ $patient->name }}</h1>
        <span class="rounded bg-teal-50 px-2 py-0.5 font-mono text-sm text-teal-800">{{ $patient->code }}</span>
        <div class="ml-auto flex items-center gap-3">
            @can('update', $patient)
                <a href="{{ route('patients.edit', $patient) }}" class="rounded bg-teal-700 px-3 py-1.5 text-sm text-white shadow-sm transition hover:bg-teal-800">Edit</a>
            @endcan
            @can('delete', $patient)
                <form method="POST" action="{{ route('patients.destroy', $patient) }}" onsubmit="return confirm('Delete this patient?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-red-700 hover:underline">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    @php
        $rows = [
            'Name (Bangla)' => $patient->name_bn,
            'Age' => $patient->age_text,
            'Date of birth' => $patient->dob?->format('j M Y'),
            'Gender' => $patient->gender->label(),
            'Blood group' => $patient->blood_group,
            'Phone' => $patient->phone,
            'Alternate phone' => $patient->alt_phone,
            'Address' => $patient->address,
            'Occupation' => $patient->occupation,
            'Patient type' => $patient->patient_type->label(),
            'Allergies' => $patient->allergies,
            'Chronic conditions' => $patient->conditions,
            'Notes' => $patient->notes,
        ];
    @endphp

    <dl class="grid gap-x-6 gap-y-3 rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:grid-cols-2">
        @foreach ($rows as $label => $text)
            @if (filled($text))
                <div>
                    <dt class="text-xs font-semibold tracking-wide text-gray-500 uppercase">{{ $label }}</dt>
                    <dd class="whitespace-pre-line">{{ $text }}</dd>
                </div>
            @endif
        @endforeach
    </dl>
@endsection
