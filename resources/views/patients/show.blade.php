@extends('layouts.app')

@section('title', $patient->name)

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">{{ $patient->name }}</h1>
        <span class="rounded bg-teal-50 px-2 py-0.5 font-mono text-sm text-teal-800">{{ $patient->code }}</span>
        <div class="ml-auto flex items-center gap-3">
            @can('create', \App\Domain\Clinical\Models\CaseHistory::class)
                <a href="{{ route('prescriptions.create', ['patient' => $patient->id]) }}" class="rounded bg-teal-700 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-teal-800">Write prescription</a>
            @endcan
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

    @can('viewAny', \App\Domain\Clinical\Models\CaseHistory::class)
        @php
            $visits = $patient->visits()->with(['doctor', 'medicines'])->limit(50)->get();
        @endphp
        <h2 class="mt-8 mb-3 text-sm font-semibold tracking-wide text-gray-500 uppercase">Visit history</h2>
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            @forelse ($visits as $visit)
                <a href="{{ route('prescriptions.show', $visit) }}" class="block border-b px-4 py-3 text-sm last:border-0 hover:bg-gray-50">
                    <div class="flex flex-wrap items-center gap-x-3">
                        <span class="font-medium">{{ $visit->visit_date->format('j M Y') }}</span>
                        <span class="text-gray-600">{{ $visit->visit_type->label() }} · {{ $visit->doctor->name_en }}</span>
                        <span class="ml-auto font-mono text-xs">{{ $visit->prescription_no ?? $visit->status->label() }}</span>
                    </div>
                    @if ($visit->diagnosis)
                        <p class="mt-1 text-gray-700">{{ \Illuminate\Support\Str::limit($visit->diagnosis, 140) }}</p>
                    @endif
                    @if ($visit->medicines->isNotEmpty())
                        <p class="mt-1 text-xs text-gray-500">℞ {{ $visit->medicines->map(fn ($m) => trim($m->display_name.' '.($m->dose()?->format() ?? '')))->join(' · ') }}</p>
                    @endif
                </a>
            @empty
                <p class="px-4 py-6 text-center text-sm text-gray-500">No visits yet.</p>
            @endforelse
        </div>
    @endcan
@endsection
