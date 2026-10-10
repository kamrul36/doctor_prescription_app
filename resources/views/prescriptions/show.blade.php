@extends('layouts.app')

@section('title', $case->prescription_no ?? 'Prescription')

@use('App\Domain\Clinical\Specialty\SpecialtySchemas')
@use('App\Domain\Clinical\Vitals')
@use('App\Domain\Practice\SpecialtyBlock')

@section('content')
    @php
        $patient = $case->patient_snapshot ?? [
            'code' => $case->patient->code, 'name' => $case->patient->name, 'age_text' => $case->patient->age_text,
            'gender' => $case->patient->gender->value, 'phone' => $case->patient->phone, 'address' => $case->patient->address,
        ];
        $h2 = 'mb-1 text-xs font-semibold uppercase tracking-wide text-teal-800';
    @endphp

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">Prescription</h1>
        @if ($case->prescription_no)
            <span class="rounded bg-emerald-50 px-2 py-0.5 font-mono text-sm text-emerald-800">{{ $case->prescription_no }}</span>
        @endif
        <span class="text-sm text-gray-600">{{ $case->status->label() }} · {{ $case->visit_date->format('j M Y') }} · #{{ $case->visit_no_today }} · {{ $case->visit_type->label() }} · {{ $case->chamber->name_en }}</span>
        <div class="ml-auto flex gap-2">
            @can('update', $case)
                <a href="{{ route('prescriptions.edit', $case) }}" class="rounded bg-teal-700 px-3 py-1.5 text-sm text-white shadow-sm hover:bg-teal-800">Continue editing</a>
            @endcan
            <a href="{{ route('patients.show', $case->patient_id) }}" class="rounded px-3 py-1.5 text-sm ring-1 ring-gray-300 hover:bg-gray-50">Patient history</a>
        </div>
    </div>

    @if ($case->cancelled_at)
        <p class="mb-4 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">Cancelled {{ $case->cancelled_at->format('j M Y H:i') }}: {{ $case->cancel_reason }}</p>
    @endif

    <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <header class="flex flex-wrap items-baseline gap-x-6 gap-y-1 border-b pb-3 text-sm">
            <span class="text-base font-semibold">{{ $patient['name'] }}</span>
            <span class="font-mono text-teal-800">{{ $patient['code'] }}</span>
            <span>{{ $patient['age_text'] }}</span>
            <span class="capitalize">{{ $patient['gender'] }}</span>
            <span>{{ $patient['phone'] }}</span>
            <span class="text-gray-600">{{ $patient['address'] }}</span>
        </header>

        <div class="mt-4 grid gap-6 md:grid-cols-5">
            <div class="space-y-4 text-sm md:col-span-2 md:border-r md:pr-6">
                @if ($case->complaints->isNotEmpty())
                    <section>
                        <h2 class="{{ $h2 }}">Complaints</h2>
                        <ul class="list-inside list-disc">
                            @foreach ($case->complaints as $complaint)
                                <li>{{ $complaint->text }}@if ($complaint->duration_value) — {{ $complaint->duration_value }} {{ $complaint->duration_unit?->label($complaint->duration_value) }}@endif</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @foreach ($case->specialty_data ?? [] as $block => $values)
                    @php
                        $enum = SpecialtyBlock::tryFrom($block);
                        $fields = $enum ? SpecialtySchemas::for($enum)->fields() : [];
                    @endphp
                    @if ($enum)
                        <section>
                            <h2 class="{{ $h2 }}">{{ $enum->label() }}</h2>
                            <dl class="grid grid-cols-[auto_1fr] gap-x-3">
                                @foreach ($values as $key => $value)
                                    <dt class="text-gray-600">{{ $fields[$key]['label'] ?? $key }}</dt>
                                    <dd>{{ $fields[$key]['options'][$value] ?? $value }}</dd>
                                @endforeach
                            </dl>
                        </section>
                    @endif
                @endforeach

                @if ($case->findings->isNotEmpty())
                    <section>
                        <h2 class="{{ $h2 }}">On examination</h2>
                        <ul>
                            @foreach ($case->findings as $finding)
                                <li>@if ($finding->label)<span class="text-gray-600">{{ $finding->label }}:</span> @endif{{ $finding->value }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($case->vitals)
                    <section>
                        <h2 class="{{ $h2 }}">Vitals</h2>
                        <p>{{ Vitals::summary($case->vitals) }}</p>
                    </section>
                @endif
            </div>

            <div class="space-y-4 text-sm md:col-span-3">
                @if ($case->diagnosis)
                    <section>
                        <h2 class="{{ $h2 }}">Diagnosis</h2>
                        <p class="whitespace-pre-line">{{ $case->diagnosis }}</p>
                    </section>
                @endif

                @if ($case->planItems->isNotEmpty())
                    <section>
                        <h2 class="{{ $h2 }}">Treatment plan</h2>
                        <ul>
                            @foreach ($case->planItems as $item)
                                <li>{{ $item->description }}@if ($item->tooth_no) (tooth {{ $item->tooth_no }})@endif <span class="text-gray-500">· {{ ucfirst($item->status) }}@if ($item->fee) · {{ $item->fee->format() }}@endif</span></li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($case->tests->isNotEmpty())
                    <section>
                        <h2 class="{{ $h2 }}">Investigations</h2>
                        <ul>
                            @foreach ($case->tests as $test)
                                <li>
                                    {{ $test->name_snapshot }}@if ($test->timing_note) <span class="text-gray-500">({{ $test->timing_note }})</span>@endif
                                    @if ($test->kind->value === 'reviewed') <span class="text-gray-500">· report seen{{ $test->result ? ': '.$test->result : '' }}</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($case->medicines->isNotEmpty())
                    <section>
                        <h2 class="font-serif text-xl text-teal-800">℞</h2>
                        <ol class="list-inside list-decimal space-y-1">
                            @foreach ($case->medicines as $medicine)
                                <li>
                                    <span class="font-medium">{{ $medicine->display_name }}</span>
                                    <span class="block pl-5 text-gray-700">{{ $medicine->summary() }}</span>
                                    @if ($medicine->instruction_en)<span class="block pl-5 text-gray-500">{{ $medicine->instruction_en }}</span>@endif
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                @if ($case->advice->isNotEmpty())
                    <section>
                        <h2 class="{{ $h2 }}">Advice</h2>
                        @foreach ($case->advice as $advice)
                            <p class="whitespace-pre-line">{{ $advice->text_en }}</p>
                        @endforeach
                    </section>
                @endif

                @if ($case->follow_up_value || $case->follow_up_date)
                    <section>
                        <h2 class="{{ $h2 }}">Follow-up</h2>
                        <p>
                            @if ($case->follow_up_value)After {{ $case->follow_up_value }} {{ $case->follow_up_unit?->label($case->follow_up_value) }}@endif
                            @if ($case->follow_up_date) {{ $case->follow_up_value ? '·' : '' }} on {{ $case->follow_up_date->format('j M Y') }}@endif
                        </p>
                    </section>
                @endif
            </div>
        </div>

        @if ($case->notes_private)
            @can('viewPrivateNotes', $case)
                <section class="mt-6 rounded border border-dashed border-gray-300 p-3 text-sm">
                    <h2 class="{{ $h2 }}">Private notes (not printed)</h2>
                    <p class="whitespace-pre-line">{{ $case->notes_private }}</p>
                </section>
            @endcan
        @endif

        <footer class="mt-6 border-t pt-3 text-xs text-gray-500">
            {{ $case->doctor_snapshot['name_en'] ?? $case->doctor->name_en }}
            @if ($case->finalized_at) · finalized {{ $case->finalized_at->format('j M Y H:i') }}@endif
            · Printing (PDF, Bangla/English) arrives with P1.6.
        </footer>
    </article>
@endsection
