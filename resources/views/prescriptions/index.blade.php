@extends('layouts.app')

@section('title', 'Prescriptions')

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">Prescriptions</h1>
        <form method="GET" class="flex flex-wrap items-center gap-2 text-sm">
            <input type="date" name="date" value="{{ $date->toDateString() }}" x-data @change="$el.form.submit()" class="rounded px-2 py-1.5 ring-1 ring-gray-300" aria-label="Date">
            @if ($isDoctor)
                <label class="flex items-center gap-1 text-gray-600">
                    <input type="checkbox" name="all" value="1" @checked(! $mine) x-data @change="$el.form.submit()"> All doctors
                </label>
            @endif
        </form>
        @can('create', \App\Domain\Clinical\Models\CaseHistory::class)
            <a href="{{ route('prescriptions.create') }}" class="ml-auto rounded bg-teal-700 px-4 py-2 font-medium text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">+ New prescription</a>
        @endcan
    </div>

    <p class="mb-3 text-sm text-gray-600">{{ $date->isToday() ? 'Today' : $date->format('l, j M Y') }} · {{ $cases->count() }} {{ \Illuminate\Support\Str::plural('prescription', $cases->count()) }}</p>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">#</th>
                    <th class="px-4 py-2">Patient</th>
                    <th class="px-4 py-2">Visit</th>
                    <th class="px-4 py-2">Chamber</th>
                    @unless ($mine)
                        <th class="px-4 py-2">Doctor</th>
                    @endunless
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cases as $case)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2">{{ $case->visit_no_today }}</td>
                        <td class="px-4 py-2">
                            <span class="font-medium">{{ $case->patient->name }}</span>
                            <span class="ml-1 font-mono text-xs text-gray-500">{{ $case->patient->code }}</span>
                        </td>
                        <td class="px-4 py-2">{{ $case->visit_type->label() }}</td>
                        <td class="px-4 py-2">{{ $case->chamber->name_en }}</td>
                        @unless ($mine)
                            <td class="px-4 py-2">{{ $case->doctor->name_en }}</td>
                        @endunless
                        <td class="px-4 py-2">
                            <span @class([
                                'rounded px-2 py-0.5 text-xs',
                                'bg-amber-50 text-amber-800' => $case->isDraft(),
                                'bg-emerald-50 text-emerald-800' => $case->isFinalized(),
                                'bg-gray-100 text-gray-500' => ! $case->isDraft() && ! $case->isFinalized(),
                            ])>{{ $case->status->label() }}</span>
                            @if ($case->prescription_no)
                                <span class="ml-1 font-mono text-xs">{{ $case->prescription_no }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $case)
                                <a href="{{ route('prescriptions.edit', $case) }}" class="font-medium text-teal-800 hover:underline">Continue</a>
                            @else
                                <a href="{{ route('prescriptions.show', $case) }}" class="hover:underline">Open</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No prescriptions on this day.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
