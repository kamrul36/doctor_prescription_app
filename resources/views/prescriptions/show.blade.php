@extends('layouts.app')

@section('title', $case->prescription_no ?? __('Prescription'))

@use('App\Domain\Clinical\Vitals')
@use('App\Domain\Document\Support\PrintLocale')
@use('App\Domain\Patient\Gender')
@use('App\Domain\Patient\Models\Patient')
@use('App\Support\Bilingual')

@section('content')
    @php
        $patient = $case->patient_snapshot ?? [
            'code' => $case->patient->code, 'name' => $case->patient->name, 'name_bn' => $case->patient->name_bn, 'age_text' => $case->patient->age_text,
            'gender' => $case->patient->gender->value, 'phone' => $case->patient->phone, 'address' => $case->patient->address,
        ];
        $h2 = 'mb-1 text-xs font-semibold uppercase tracking-wide text-teal-800';
    @endphp

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">{{ __('Prescription') }}</h1>
        @if ($case->prescription_no)
            <span class="rounded bg-emerald-50 px-2 py-0.5 font-mono text-sm text-emerald-800">{{ $case->prescription_no }}</span>
        @endif
        <span class="text-sm text-gray-600">{{ $case->status->label() }} · {{ $case->visit_date->translatedFormat('j M Y') }} · #{{ $case->visit_no_today }} · {{ $case->visit_type->label() }} · {{ $case->chamber->localized('name') }}</span>
        <div class="ml-auto flex gap-2">
            @can('update', $case)
                <a href="{{ route('prescriptions.edit', $case) }}" class="rounded bg-teal-700 px-3 py-1.5 text-sm text-white shadow-sm hover:bg-teal-800">{{ __('Continue editing') }}</a>
            @endcan
            <a href="{{ route('patients.show', $case->patient_id) }}" class="rounded px-3 py-1.5 text-sm ring-1 ring-gray-300 hover:bg-gray-50">{{ __('Patient history') }}</a>
            @can('print', $case)
                {{-- Print in the chosen language (spec §6); opens the A4 view, which can still switch. --}}
                <div class="flex overflow-hidden rounded text-sm ring-1 ring-teal-700" role="group" aria-label="{{ __('Print') }}">
                    <span class="bg-teal-700 px-3 py-1.5 font-medium text-white">{{ $case->isDraft() ? __('Preview') : __('Print') }}</span>
                    @foreach (PrintLocale::cases() as $printLocale)
                        <a href="{{ route('prescriptions.print', [$case, 'lang' => $printLocale->value]) }}" target="_blank" rel="noopener"
                           @class(['px-3 py-1.5 text-teal-800 hover:bg-teal-50', 'font-semibold' => $printLocale === PrintLocale::forUi()])>{{ $printLocale->label() }}</a>
                    @endforeach
                </div>
            @endcan
        </div>
    </div>

    @if ($case->cancelled_at)
        <p class="mb-4 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ __('Cancelled :date: :reason', ['date' => $case->cancelled_at->translatedFormat('j M Y H:i'), 'reason' => $case->cancel_reason]) }}</p>
    @endif

    <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <header class="flex flex-wrap items-baseline gap-x-6 gap-y-1 border-b pb-3 text-sm">
            <span class="text-base font-semibold">{{ Bilingual::pick($patient['name'], $patient['name_bn'] ?? null) }}</span>
            <span class="font-mono text-teal-800">{{ $patient['code'] }}</span>
            <span>{{ Patient::ageLabel($patient['age_text']) }}</span>
            <span>{{ Gender::tryFrom($patient['gender'])?->label() }}</span>
            <span>{{ $patient['phone'] }}</span>
            <span class="text-gray-600">{{ $patient['address'] }}</span>
        </header>

        <div class="mt-4 grid gap-6 md:grid-cols-5">
            <div class="space-y-4 text-sm md:col-span-2 md:border-r md:pr-6">
                @if ($case->complaints->isNotEmpty())
                    <section>
                        <h2 class="{{ $h2 }}">{{ __('Complaints') }}</h2>
                        <ul class="list-inside list-disc">
                            @foreach ($case->complaints as $complaint)
                                <li>{{ $complaint->text }}@if ($complaint->duration_value) — {{ $complaint->duration_value }} {{ $complaint->duration_unit?->label($complaint->duration_value) }}@endif</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($case->findings->isNotEmpty())
                    <section>
                        <h2 class="{{ $h2 }}">{{ __('On examination') }}</h2>
                        <ul>
                            @foreach ($case->findings as $finding)
                                <li>@if ($finding->label)<span class="text-gray-600">{{ $finding->label }}:</span> @endif{{ $finding->value }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($case->vitals)
                    <section>
                        <h2 class="{{ $h2 }}">{{ __('Vitals') }}</h2>
                        <p>{{ Vitals::summary($case->vitals) }}</p>
                    </section>
                @endif
            </div>

            <div class="space-y-4 text-sm md:col-span-3">
                @if ($case->diagnosis)
                    <section>
                        <h2 class="{{ $h2 }}">{{ __('Diagnosis') }}</h2>
                        <p class="whitespace-pre-line">{{ $case->diagnosis }}</p>
                    </section>
                @endif

                @if ($case->planItems->isNotEmpty())
                    <section>
                        <h2 class="{{ $h2 }}">{{ __('Treatment plan') }}</h2>
                        <ul>
                            @foreach ($case->planItems as $item)
                                <li>{{ $item->description }}@if ($item->tooth_no) ({{ __('tooth :no', ['no' => $item->tooth_no]) }})@endif <span class="text-gray-500">· {{ __(ucfirst($item->status)) }}@if ($item->fee) · {{ $item->fee->format() }}@endif</span></li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($case->tests->isNotEmpty())
                    <section>
                        <h2 class="{{ $h2 }}">{{ __('Investigations') }}</h2>
                        <ul>
                            @foreach ($case->tests as $test)
                                <li>
                                    {{ $test->name_snapshot }}@if ($test->timing_note) <span class="text-gray-500">({{ $test->timing_note }})</span>@endif
                                    @if ($test->kind->value === 'reviewed') <span class="text-gray-500">· {{ __('report seen') }}{{ $test->result ? ': '.$test->result : '' }}</span>@endif
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
                                    <span class="block pl-5 text-gray-700">{{ $medicine->summary(app()->getLocale(), banglaDigits: false) }}</span>
                                    @if ($instruction = Bilingual::pick($medicine->instruction_en, $medicine->instruction_bn))<span class="block pl-5 text-gray-500">{{ $instruction }}</span>@endif
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                @if ($case->advice->isNotEmpty())
                    <section>
                        <h2 class="{{ $h2 }}">{{ __('Advice') }}</h2>
                        @foreach ($case->advice as $advice)
                            <p class="whitespace-pre-line">{{ Bilingual::pick($advice->text_en, $advice->text_bn) }}</p>
                        @endforeach
                    </section>
                @endif

                @if ($case->follow_up_value || $case->follow_up_date)
                    <section>
                        <h2 class="{{ $h2 }}">{{ __('Follow-up') }}</h2>
                        <p>
                            @if ($case->follow_up_value){{ __('After :n :unit', ['n' => $case->follow_up_value, 'unit' => $case->follow_up_unit?->label($case->follow_up_value)]) }}@endif
                            @if ($case->follow_up_date) {{ $case->follow_up_value ? '·' : '' }} {{ __('On :date', ['date' => $case->follow_up_date->translatedFormat('j M Y')]) }}@endif
                        </p>
                    </section>
                @endif
            </div>
        </div>

        @if ($case->notes_private)
            @can('viewPrivateNotes', $case)
                <section class="mt-6 rounded border border-dashed border-gray-300 p-3 text-sm">
                    <h2 class="{{ $h2 }}">{{ __('Private notes (not printed)') }}</h2>
                    <p class="whitespace-pre-line">{{ $case->notes_private }}</p>
                </section>
            @endcan
        @endif

        <footer class="mt-6 border-t pt-3 text-xs text-gray-500">
            {{ Bilingual::pick($case->doctor_snapshot['name_en'] ?? $case->doctor->name_en, $case->doctor_snapshot['name_bn'] ?? $case->doctor->name_bn) }}
            @if ($case->finalized_at) · {{ __('finalized :date', ['date' => $case->finalized_at->translatedFormat('j M Y H:i')]) }}@endif
        </footer>
    </article>
@endsection
