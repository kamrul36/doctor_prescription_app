@extends('layouts.app')

@section('title', $case ? 'Prescription draft' : 'New prescription')

@use('App\Domain\Clinical\DoseTiming')
@use('App\Domain\Clinical\DoseUnit')
@use('App\Domain\Clinical\DurationUnit')
@use('App\Domain\Clinical\PeriodUnit')
@use('App\Domain\Clinical\Vitals')
@use('App\Domain\Patient\Gender')
@use('App\Domain\Practice\VisitType')
@use('Illuminate\Support\Arr')

@php
    $rows = fn (string $key) => array_values(old($key, $initial[$key]));
    $selected = $patient ? [
        'id' => $patient->id, 'code' => $patient->code, 'name' => $patient->name, 'age_text' => $patient->age_text,
        'gender' => $patient->gender->value, 'phone' => $patient->phone, 'address' => $patient->address,
    ] : null;
    // A patient picked before a failed save comes back through old input.
    if (! $case && ! $selected && old('patient_id')) {
        $p = \App\Domain\Patient\Models\Patient::query()->find(old('patient_id'));
        $selected = $p ? ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'age_text' => $p->age_text, 'gender' => $p->gender->value, 'phone' => $p->phone, 'address' => $p->address] : null;
    }
    $visitType = old('visit_type', $case?->visit_type->value ?? $suggestedVisitType?->value ?? VisitType::New->value);
    $vitals = old('vitals', $case?->vitals ?? []);
    $specialty = old('specialty_data', $case?->specialty_data ?? []);
    $field = 'w-full rounded px-2 py-1.5 text-sm ring-1 ring-gray-300';
    $label = 'mb-1 block text-sm font-medium text-gray-600';
    $card = 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm';
    $h2 = 'mb-2 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-teal-800';
    $addBtn = 'ml-auto rounded px-2 py-0.5 text-xs font-medium normal-case tracking-normal text-teal-800 ring-1 ring-teal-700 hover:bg-teal-50';
    $removeBtn = 'inline-flex cursor-pointer h-7 w-7 shrink-0 items-center justify-center rounded-full text-gray-400 transition hover:bg-red-50 hover:text-red-700 focus:ring-2 focus:ring-red-300 focus:outline-none';
    $error = 'text-sm text-red-700';
    // Field rules mirror SaveVisitRequest; rules kept in PHP (vitals, specialty blocks) are mapped by ClientRules.
    $rulesOf = fn (array $rules) => \App\Support\ClientRules::attributes($rules);
@endphp

@section('content')
    <form method="POST" action="{{ $case ? route('prescriptions.update', $case) : route('prescriptions.store') }}"
          x-data="validatedForm(@js(Arr::except($errors->toArray(), 'reason')))"
          class="space-y-4">
        @csrf
        @if ($case)
            @method('PUT')
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold">{{ $case ? 'Prescription draft' : 'New prescription' }}</h1>
            @if ($case)
                <span class="rounded bg-amber-50 px-2 py-0.5 text-sm text-amber-800">Draft · #{{ $case->visit_no_today }} · {{ $case->visit_date->format('j M Y') }}</span>
            @endif
            <div class="ml-auto flex flex-wrap items-center gap-3 text-sm">
                <label class="flex items-center gap-2">
                    <span class="text-gray-600">Chamber <x-required-mark /></span>
                    <select name="chamber_id" class="rounded px-2 py-1.5 ring-1 ring-gray-300" required>
                        @foreach ($chambers as $chamber)
                            <option value="{{ $chamber->id }}" @selected((string) old('chamber_id', $case?->chamber_id ?? $chambers->first()?->id) === (string) $chamber->id)>{{ $chamber->name_en }}</option>
                        @endforeach
                    </select>
                    <span class="{{ $error }}" x-text="err('chamber_id')"></span>
                </label>
                <label class="flex items-center gap-2">
                    <span class="text-gray-600">Visit</span>
                    <select name="visit_type" class="rounded px-2 py-1.5 ring-1 ring-gray-300">
                        @foreach (VisitType::cases() as $type)
                            <option value="{{ $type->value }}" @selected($visitType === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>

        {{-- Errors without a field on the pad (e.g. "status"); the rest show under their field or row. --}}
        <x-form-errors class="mb-0" />

        {{-- Patient strip --}}
        <section class="{{ $card }}"
                 x-data="patientPicker({ url: @js(route('lookup.patients')), selected: @js($selected), fields: @js(array_filter((array) old('patient', []), fn ($v) => $v !== null)) })">
            <h2 class="{{ $h2 }}">Patient</h2>

            @if ($case)
                <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1 text-sm">
                    <span class="text-base font-semibold">{{ $patient->name }}</span>
                    <span class="font-mono text-teal-800">{{ $patient->code }}</span>
                    <span>{{ $patient->age_text }}</span>
                    <span>{{ $patient->gender->label() }}</span>
                    <span>{{ $patient->phone }}</span>
                    <span class="text-gray-600">{{ $patient->address }}</span>
                </div>
            @else
                <input type="hidden" name="patient_id" :value="selectedId">

                <template x-if="patient">
                    <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1 text-sm">
                        <span class="text-base font-semibold" x-text="patient.name"></span>
                        <span class="font-mono text-teal-800" x-text="patient.code"></span>
                        <span x-text="patient.age_text"></span>
                        <span class="capitalize" x-text="patient.gender"></span>
                        <span x-text="patient.phone"></span>
                        <span class="text-gray-600" x-text="patient.address"></span>
                        <button type="button" @click="change()" class="ml-auto text-xs text-teal-800 hover:underline">Change patient</button>
                    </div>
                </template>

                <template x-if="!patient">
                    <div>
                        <p class="mb-2 text-xs text-gray-500">New to the chamber? Fill in the details; the patient is registered when you save. Already registered? Start typing the phone or name and pick them.</p>
                        <div class="grid gap-3 sm:grid-cols-6">
                            <div class="sm:col-span-2">
                                <label class="{{ $label }}">Name <x-required-mark /></label>
                                <input name="patient[name]" x-model="form.name" @input="lookup()" required maxlength="255" class="{{ $field }}" autocomplete="off">
                                <p class="{{ $error }}" x-text="err('patient.name')"></p>
                            </div>
                            <div>
                                <label class="{{ $label }}">Age (years) <x-required-mark /></label>
                                <input name="patient[age_years]" x-model="form.age_years" inputmode="numeric" required data-rule="integer" data-min="0" data-max="130" class="{{ $field }}">
                                <p class="{{ $error }}" x-text="err('patient.age_years')"></p>
                            </div>
                            <div>
                                <label class="{{ $label }}">Sex <x-required-mark /></label>
                                <select name="patient[gender]" x-model="form.gender" required class="{{ $field }}">
                                    <option value="">—</option>
                                    @foreach (Gender::cases() as $gender)
                                        <option value="{{ $gender->value }}">{{ $gender->label() }}</option>
                                    @endforeach
                                </select>
                                <p class="{{ $error }}" x-text="err('patient.gender')"></p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="{{ $label }}">Phone <x-required-mark /></label>
                                <input name="patient[phone]" x-model="form.phone" @input="lookup()" inputmode="tel" required maxlength="20" data-rule="phone" placeholder="01XXXXXXXXX" class="{{ $field }}" autocomplete="off">
                                <p class="{{ $error }}" x-text="err('patient.phone')"></p>
                            </div>
                            <div class="sm:col-span-6">
                                <label class="{{ $label }}">Address</label>
                                <input name="patient[address]" x-model="form.address" maxlength="1000" class="{{ $field }}">
                                <p class="{{ $error }}" x-text="err('patient.address')"></p>
                            </div>
                        </div>

                        <div x-show="matches.length" x-cloak class="mt-3 rounded border border-teal-200 bg-teal-50 p-3 text-sm">
                            <p class="mb-1 font-medium text-teal-900">Already registered?</p>
                            <template x-for="match in matches" :key="match.id">
                                <div class="flex flex-wrap items-center gap-x-4 border-t border-teal-100 py-1 first:border-0">
                                    <span class="font-medium" x-text="match.name"></span>
                                    <span class="font-mono" x-text="match.code"></span>
                                    <span x-text="match.age_text"></span>
                                    <span x-text="match.phone"></span>
                                    <button type="button" @click="use(match)" class="ml-auto rounded bg-teal-700 px-2 py-0.5 text-xs text-white">Use this patient</button>
                                </div>
                            </template>
                        </div>

                        @if ($candidates->isNotEmpty())
                            <div class="mt-3 rounded border border-amber-300 bg-amber-50 p-3 text-sm">
                                <p class="font-medium text-amber-900">This patient may already be registered. Choose one, then save again:</p>
                                @foreach ($candidates as $candidate)
                                    <label class="mt-1 flex items-center gap-2">
                                        <input type="radio" name="patient_choice" value="{{ $candidate->id }}" @checked(old('patient_choice') == $candidate->id)>
                                        <span class="font-medium">{{ $candidate->name }}</span>
                                        <span class="font-mono">{{ $candidate->code }}</span>
                                        <span>{{ $candidate->age_text }}</span>
                                        <span>{{ $candidate->phone }}</span>
                                    </label>
                                @endforeach
                                <label class="mt-1 flex items-center gap-2">
                                    <input type="radio" name="patient_choice" value="new" @checked(old('patient_choice') === 'new')>
                                    <span>None of these: register a new patient</span>
                                </label>
                            </div>
                        @endif
                    </div>
                </template>
            @endif
        </section>

        <div class="grid gap-4 lg:grid-cols-5">
            {{-- Left: history and examination --}}
            <div class="space-y-4 lg:col-span-2">
                <section class="{{ $card }}" x-data="rowList(@js($rows('complaints')), { text: '', duration_value: '', duration_unit: 'day' }, { min: 1 })">
                    <h2 class="{{ $h2 }}">Complaints <button type="button" @click="add()" class="{{ $addBtn }}">+ Add</button></h2>
                    <template x-for="(row, i) in rows" :key="row._key">
                        <div data-row class="mb-2 grid grid-cols-[1fr_4rem_5.5rem_auto] items-center gap-2">
                            <input :name="`complaints[${i}][text]`" x-model="row.text" maxlength="500" placeholder="e.g. Lower abdominal pain" class="{{ $field }}">
                            <input :name="`complaints[${i}][duration_value]`" x-model="row.duration_value" inputmode="numeric" data-rule="integer" data-min="1" data-max="999" placeholder="for" class="{{ $field }}">
                            <select :name="`complaints[${i}][duration_unit]`" x-model="row.duration_unit" class="{{ $field }}">
                                @foreach (PeriodUnit::cases() as $unit)
                                    <option value="{{ $unit->value }}">{{ $unit->label() }}</option>
                                @endforeach
                            </select>
                            <button type="button" @click="remove(i)" class="{{ $removeBtn }}" aria-label="Remove"><x-icon-close /></button>
                            <p x-show="rowErr(`complaints.${i}`)" class="{{ $error }} col-span-full" x-text="rowErr(`complaints.${i}`)"></p>
                        </div>
                    </template>
                </section>

                @foreach ($schemas as $schema)
                    @php
                        $block = $schema->block()->value;
                    @endphp
                    <section class="{{ $card }}">
                        <h2 class="{{ $h2 }}">{{ $schema->block()->label() }}</h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($schema->fields() as $key => $def)
                                @php
                                    $value = $specialty[$block][$key] ?? '';
                                @endphp
                                <div @class(['sm:col-span-2' => $def['type'] === 'textarea'])>
                                    <label class="{{ $label }}" for="sp_{{ $block }}_{{ $key }}">{{ $def['label'] }}</label>
                                    @if ($def['type'] === 'textarea')
                                        <textarea id="sp_{{ $block }}_{{ $key }}" name="specialty_data[{{ $block }}][{{ $key }}]" rows="2" placeholder="{{ $def['hint'] ?? '' }}" {{ $rulesOf($def['rules']) }} class="{{ $field }}">{{ $value }}</textarea>
                                    @elseif ($def['type'] === 'select')
                                        <select id="sp_{{ $block }}_{{ $key }}" name="specialty_data[{{ $block }}][{{ $key }}]" class="{{ $field }}">
                                            <option value="">—</option>
                                            @foreach ($def['options'] as $optValue => $optLabel)
                                                <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input id="sp_{{ $block }}_{{ $key }}" type="{{ $def['type'] === 'number' ? 'text' : $def['type'] }}" @if ($def['type'] === 'number') inputmode="numeric" @endif
                                               name="specialty_data[{{ $block }}][{{ $key }}]" value="{{ $value }}" placeholder="{{ $def['hint'] ?? '' }}" {{ $rulesOf($def['rules']) }} class="{{ $field }}">
                                    @endif
                                    <p class="{{ $error }}" x-text="err(@js("specialty_data.{$block}.{$key}"))"></p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <section class="{{ $card }}" x-data="rowList(@js($rows('findings')), { label: '', value: '' })">
                    <h2 class="{{ $h2 }}">On examination <button type="button" @click="add()" class="{{ $addBtn }}">+ Add</button></h2>
                    <template x-for="(row, i) in rows" :key="row._key">
                        <div data-row class="mb-2 grid grid-cols-[7rem_1fr_auto] items-center gap-2">
                            <input :name="`findings[${i}][label]`" x-model="row.label" maxlength="255" placeholder="e.g. Anaemia" class="{{ $field }}">
                            <input :name="`findings[${i}][value]`" x-model="row.value" maxlength="500" placeholder="finding" class="{{ $field }}">
                            <button type="button" @click="remove(i)" class="{{ $removeBtn }}" aria-label="Remove"><x-icon-close /></button>
                            <p x-show="rowErr(`findings.${i}`)" class="{{ $error }} col-span-full" x-text="rowErr(`findings.${i}`)"></p>
                        </div>
                    </template>
                    <p x-show="rows.length === 0" class="text-xs text-gray-500">No findings yet.</p>
                </section>

                <section class="{{ $card }}">
                    <h2 class="{{ $h2 }}">Vitals</h2>
                    <div class="grid grid-cols-2 items-end gap-x-3 gap-y-2 sm:grid-cols-4">
                        @foreach (Vitals::FIELDS as $key => $def)
                            <div>
                                <label class="{{ $label }} flex! items-baseline justify-between gap-1 whitespace-nowrap" for="v_{{ $key }}"><span>{{ $def['label'] }}</span> <span class="text-xs font-normal text-gray-400">{{ $def['unit'] }}</span></label>
                                <input id="v_{{ $key }}" name="vitals[{{ $key }}]" value="{{ $vitals[$key] ?? '' }}" inputmode="decimal" {{ $rulesOf($def['rules']) }} class="{{ $field }}">
                                <p class="{{ $error }}" x-text="err(@js("vitals.{$key}"))"></p>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            {{-- Right: diagnosis, plan, tests, Rx, advice --}}
            <div class="space-y-4 lg:col-span-3">
                <section class="{{ $card }}">
                    <h2 class="{{ $h2 }}"><label for="diagnosis">Diagnosis</label></h2>
                    <textarea id="diagnosis" name="diagnosis" rows="2" maxlength="2000" class="{{ $field }}">{{ old('diagnosis', $case?->diagnosis) }}</textarea>
                    <p id="diagnosis-error" class="{{ $error }}" x-text="err('diagnosis')"></p>
                </section>

                <section class="{{ $card }}" x-data="rowList(@js($rows('plan_items')), { procedure_id: '', description: '', tooth_no: '', status: 'planned', fee: '' })">
                    <h2 class="{{ $h2 }}">Treatment plan <button type="button" @click="add()" class="{{ $addBtn }}">+ Add</button></h2>
                    <template x-for="(row, i) in rows" :key="row._key">
                        <div data-row class="mb-2 grid items-center gap-2 {{ $hasDental ? 'grid-cols-[1fr_4.5rem_6rem_5.5rem_auto]' : 'grid-cols-[1fr_6rem_5.5rem_auto]' }}">
                            <input type="hidden" :name="`plan_items[${i}][procedure_id]`" :value="row.procedure_id ?? ''">
                            @include('prescriptions._typeahead', [
                                'url' => route('lookup.procedures'), 'label' => 'name_en', 'model' => 'row.description',
                                'name' => '`plan_items[${i}][description]`', 'placeholder' => 'Procedure', 'maxlength' => 500,
                                'picked' => "row.procedure_id = item ? item.id : ''; if (item && item.default_fee && !row.fee) row.fee = item.default_fee;",
                            ])
                            @if ($hasDental)
                                <input :name="`plan_items[${i}][tooth_no]`" x-model="row.tooth_no" maxlength="32" placeholder="Tooth" class="{{ $field }}">
                            @endif
                            <input :name="`plan_items[${i}][fee]`" x-model="row.fee" inputmode="decimal" data-rule="money" placeholder="Fee ৳" class="{{ $field }}">
                            <select :name="`plan_items[${i}][status]`" x-model="row.status" class="{{ $field }}">
                                <option value="planned">Planned</option>
                                <option value="done">Done</option>
                            </select>
                            <button type="button" @click="remove(i)" class="{{ $removeBtn }}" aria-label="Remove"><x-icon-close /></button>
                            <p x-show="rowErr(`plan_items.${i}`)" class="{{ $error }} col-span-full" x-text="rowErr(`plan_items.${i}`)"></p>
                        </div>
                    </template>
                    <p x-show="rows.length === 0" class="text-xs text-gray-500">No procedures.</p>
                </section>

                <section class="{{ $card }}" x-data="rowList(@js($rows('tests')), { lab_test_id: '', name: '', timing_note: '', kind: 'advised', result: '' })">
                    <h2 class="{{ $h2 }}">Investigations <button type="button" @click="add()" class="{{ $addBtn }}">+ Add</button></h2>
                    <template x-for="(row, i) in rows" :key="row._key">
                        <div data-row class="mb-2 grid grid-cols-[1fr_5rem_6.5rem_auto] items-center gap-2">
                            <input type="hidden" :name="`tests[${i}][lab_test_id]`" :value="row.lab_test_id ?? ''">
                            @include('prescriptions._typeahead', [
                                'url' => route('lookup.lab-tests'), 'label' => 'name', 'model' => 'row.name',
                                'name' => '`tests[${i}][name]`', 'placeholder' => 'Test', 'maxlength' => 255,
                                'picked' => "row.lab_test_id = item ? item.id : ''; if (item && item.default_timing_note && !row.timing_note) row.timing_note = item.default_timing_note;",
                            ])
                            <input :name="`tests[${i}][timing_note]`" x-model="row.timing_note" maxlength="100" placeholder="D2, fasting" class="{{ $field }}">
                            <select :name="`tests[${i}][kind]`" x-model="row.kind" class="{{ $field }}">
                                <option value="advised">Advised</option>
                                <option value="reviewed">Report seen</option>
                            </select>
                            <button type="button" @click="remove(i)" class="{{ $removeBtn }}" aria-label="Remove"><x-icon-close /></button>
                            <input x-show="row.kind === 'reviewed'" :name="`tests[${i}][result]`" x-model="row.result" maxlength="1000" placeholder="Result" class="{{ $field }} col-span-3">
                            <p x-show="rowErr(`tests.${i}`)" class="{{ $error }} col-span-full" x-text="rowErr(`tests.${i}`)"></p>
                        </div>
                    </template>
                    <p x-show="rows.length === 0" class="text-xs text-gray-500">No investigations.</p>
                </section>

                <section class="{{ $card }}" x-data="rowList(@js($rows('medicines')), { name: '', dose_morning: '', dose_noon: '', dose_night: '', dose_unit: 'tablet', timing: 'after_meal', duration_value: '', duration_unit: 'day', instruction: '' }, { min: 1 })">
                    <h2 class="{{ $h2 }}"><span class="font-serif text-lg normal-case">℞</span> Medicines <button type="button" @click="add()" class="{{ $addBtn }}">+ Add medicine</button></h2>
                    <p class="mb-2 text-xs text-gray-500">Type the shortcut, e.g. <code>2+0+2 7d</code>, <code>1+0+0 6m</code>, <code>0+0+1 cont</code>, <code>1+0+1 sos</code>, and press Enter.</p>
                    <template x-for="(row, i) in rows" :key="row._key">
                        <div data-row class="mb-3 rounded-lg border border-gray-200 p-3">
                            <div class="flex items-start gap-2">
                                <span class="pt-1.5 text-sm font-semibold text-gray-500" x-text="`${i + 1}.`"></span>
                                <div class="grid flex-1 gap-2 sm:grid-cols-[1fr_9rem]">
                                    <input :name="`medicines[${i}][name]`" x-model="row.name" maxlength="255" placeholder="e.g. Tab. Napa 500 mg" class="{{ $field }} font-medium">
                                    <div x-data="doseInput(row)">
                                        <input x-model="shortcut" @keydown.enter.prevent="apply()" @blur="apply()" placeholder="2+0+2 7d" aria-label="Dose shortcut" class="{{ $field }}">
                                        <p class="text-sm text-red-700" x-text="error"></p>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <button type="button" @click="move(i, -1)" class="text-xs text-gray-500 hover:text-gray-800" aria-label="Move up">▲</button>
                                    <button type="button" @click="move(i, 1)" class="text-xs text-gray-500 hover:text-gray-800" aria-label="Move down">▼</button>
                                </div>
                                <button type="button" @click="remove(i)" class="{{ $removeBtn }} mt-0.5" aria-label="Remove"><x-icon-close /></button>
                            </div>
                            <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-[3rem_3rem_3rem_6.5rem_7rem_4rem_6.5rem]">
                                <input :name="`medicines[${i}][dose_morning]`" x-model="row.dose_morning" maxlength="8" data-rule="dose-slot" title="Morning" placeholder="M" class="{{ $field }} text-center">
                                <input :name="`medicines[${i}][dose_noon]`" x-model="row.dose_noon" maxlength="8" data-rule="dose-slot" title="Noon" placeholder="N" class="{{ $field }} text-center">
                                <input :name="`medicines[${i}][dose_night]`" x-model="row.dose_night" maxlength="8" data-rule="dose-slot" title="Night" placeholder="E" class="{{ $field }} text-center">
                                <select :name="`medicines[${i}][dose_unit]`" x-model="row.dose_unit" class="{{ $field }}">
                                    @foreach (DoseUnit::cases() as $unit)
                                        <option value="{{ $unit->value }}">{{ $unit->label() }}</option>
                                    @endforeach
                                </select>
                                <select :name="`medicines[${i}][timing]`" x-model="row.timing" class="{{ $field }}">
                                    <option value="">Timing</option>
                                    @foreach (DoseTiming::cases() as $timing)
                                        <option value="{{ $timing->value }}">{{ $timing->label() }}</option>
                                    @endforeach
                                </select>
                                <input :name="`medicines[${i}][duration_value]`" x-model="row.duration_value" x-bind:disabled="['continuous', 'as_needed'].includes(row.duration_unit)" inputmode="numeric" data-rule="integer" data-min="1" data-max="{{ \App\Domain\Clinical\ValueObjects\Duration::MAX_VALUE }}" placeholder="for" class="{{ $field }}">
                                <select :name="`medicines[${i}][duration_unit]`" x-model="row.duration_unit" class="{{ $field }}">
                                    @foreach (DurationUnit::cases() as $unit)
                                        <option value="{{ $unit->value }}">{{ $unit->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <input :name="`medicines[${i}][instruction]`" x-model="row.instruction" maxlength="500" placeholder="Instruction (optional), e.g. gargle after brushing" class="{{ $field }} mt-2">
                            <p class="mt-1 {{ $error }}" x-text="rowErr(`medicines.${i}`)"></p>
                        </div>
                    </template>
                </section>

                <section class="{{ $card }}" x-data="rowList(@js($rows('advice')), { text: '', text_bn: '', source_template_id: '' })">
                    <h2 class="{{ $h2 }}">Advice <button type="button" @click="add()" class="{{ $addBtn }}">+ Write</button></h2>
                    <div class="mb-2" x-data="typeahead({ url: @js(route('lookup.advice-templates')), label: 'title' })"
                         @picked="if ($event.detail) { add({ text: $event.detail.text_en, text_bn: $event.detail.text_bn ?? '', source_template_id: $event.detail.id }); text = ''; }">
                        <div class="relative">
                            <input x-model="text" @input="onInput()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter="onEnter($event)" @blur="close()"
                                   autocomplete="off" placeholder="Add from an advice template…" class="{{ $field }}">
                            <ul x-show="open" x-cloak class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded border border-gray-200 bg-white text-sm shadow-lg">
                                <template x-for="(item, k) in results" :key="item.id">
                                    <li @mousedown.prevent="choose(k)" @mouseenter="active = k" :class="k === active ? 'bg-teal-50' : ''" class="flex cursor-pointer justify-between gap-2 px-3 py-1.5">
                                        <span x-text="item.title"></span><span class="text-xs text-gray-500" x-text="item.specialty ?? ''"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    <template x-for="(row, i) in rows" :key="row._key">
                        <div data-row class="mb-2 flex items-start gap-2">
                            <input type="hidden" :name="`advice[${i}][source_template_id]`" :value="row.source_template_id ?? ''">
                            <input type="hidden" :name="`advice[${i}][text_bn]`" :value="row.text_bn ?? ''">
                            <div class="flex-1">
                                <textarea :name="`advice[${i}][text]`" x-model="row.text" rows="3" maxlength="5000" class="{{ $field }}"></textarea>
                                <p class="{{ $error }}" x-text="rowErr(`advice.${i}`)"></p>
                            </div>
                            <button type="button" @click="remove(i)" class="{{ $removeBtn }}" aria-label="Remove"><x-icon-close /></button>
                        </div>
                    </template>
                </section>

                <section class="{{ $card }}">
                    <h2 class="{{ $h2 }}">Follow-up</h2>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span>Come back after</span>
                        <input name="follow_up_value" value="{{ old('follow_up_value', $case?->follow_up_value) }}" inputmode="numeric" data-rule="integer" data-min="1" data-max="365" class="w-16 rounded px-2 py-1.5 ring-1 ring-gray-300" aria-label="Follow-up after">
                        <select name="follow_up_unit" class="rounded px-2 py-1.5 ring-1 ring-gray-300" aria-label="Follow-up unit">
                            @foreach (PeriodUnit::cases() as $unit)
                                <option value="{{ $unit->value }}" @selected(old('follow_up_unit', $case?->follow_up_unit?->value ?? 'week') === $unit->value)>{{ $unit->label() }}</option>
                            @endforeach
                        </select>
                        <span>or on</span>
                        <input type="date" name="follow_up_date" value="{{ old('follow_up_date', $case?->follow_up_date?->toDateString()) }}" min="{{ now()->toDateString() }}" data-rule="future-date" class="rounded px-2 py-1.5 ring-1 ring-gray-300" aria-label="Follow-up date">
                    </div>
                    <p class="{{ $error }}" x-text="err('follow_up_value') || err('follow_up_unit') || err('follow_up_date')"></p>
                </section>

                <section class="{{ $card }}">
                    <h2 class="{{ $h2 }}"><label for="notes_private">Private notes</label> <span class="text-xs font-normal normal-case text-gray-500">not printed</span></h2>
                    <textarea id="notes_private" name="notes_private" rows="2" maxlength="5000" class="{{ $field }}">{{ old('notes_private', $case?->notes_private) }}</textarea>
                    <p id="notes_private-error" class="{{ $error }}" x-text="err('notes_private')"></p>
                </section>
            </div>
        </div>

        <div class="sticky bottom-0 -mx-4 flex flex-wrap items-center gap-3 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur">
            <button type="submit" name="action" value="draft" class="rounded px-4 py-2 font-medium text-teal-800 ring-1 ring-teal-700 hover:bg-teal-50">Save draft</button>
            @can('cases.finalize')
                <button type="submit" name="action" value="finalize" class="rounded bg-teal-700 px-4 py-2 font-medium text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">Save &amp; finalize</button>
            @endcan
            <a href="{{ route('prescriptions.index') }}" class="px-2 py-2 text-sm text-gray-700">Back to list</a>
        </div>
    </form>

    @if ($case)
        <form method="POST" action="{{ route('prescriptions.cancel', $case) }}" class="mt-4 text-sm" x-data="validatedForm(@js(array_filter(['reason' => $errors->get('reason')])))">
            @csrf
            <div class="flex flex-wrap items-center gap-2" x-data="{ open: @js($errors->has('reason')) }">
                <button type="button" x-show="!open" @click="open = true" class="text-red-700 hover:underline">Cancel this draft…</button>
                <template x-if="open">
                    <div class="flex flex-wrap items-center gap-2">
                        <input name="reason" value="{{ old('reason') }}" required minlength="3" maxlength="500" placeholder="Reason" class="rounded px-2 py-1.5 ring-1 ring-gray-300">
                        <button type="submit" class="rounded bg-red-700 px-3 py-1.5 text-white">Cancel draft</button>
                        <button type="button" @click="open = false" class="text-gray-600">Keep it</button>
                    </div>
                </template>
            </div>
            <p class="{{ $error }}" x-text="err('reason')"></p>
        </form>
    @endif
@endsection
