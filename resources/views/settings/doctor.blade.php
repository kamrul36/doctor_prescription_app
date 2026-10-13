@extends('layouts.app')

@section('title', __('My doctor profile'))

@section('content')
    @php
        $credentials = old('credentials', $doctor->credentials->map(fn ($c) => ['text_en' => $c->text_en, 'text_bn' => $c->text_bn])->all());
        $credentials = array_pad(array_values($credentials), count($credentials) + 3, []);
        $input = 'mt-2 w-full rounded px-3 py-2 ring-1 ring-gray-300';
        $visitTypes = \App\Domain\Practice\VisitType::cases();
    @endphp

    <h1 class="mb-4 text-2xl font-semibold">{{ __('My doctor profile') }}</h1>

    <form method="POST" action="{{ route('settings.doctor.update') }}" x-data="validatedForm(@js($errors->toArray()))" class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
        @csrf
        <x-form-errors />
        @method('PUT')

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name_en" class="block text-base font-medium">{{ __('Name (English)') }} <x-required-mark /></label>
                <input id="name_en" name="name_en" value="{{ old('name_en', $doctor->name_en) }}" required maxlength="255" class="{{ $input }}">
                <x-field-error for="name_en" />
            </div>
            <div>
                <label for="name_bn" class="block text-base font-medium">{{ __('Name (Bangla)') }}</label>
                <input id="name_bn" name="name_bn" value="{{ old('name_bn', $doctor->name_bn) }}" maxlength="255" class="{{ $input }}">
                <x-field-error for="name_bn" />
            </div>
            <div>
                <label for="designation_en" class="block text-base font-medium">{{ __('Designation (English)') }}</label>
                <input id="designation_en" name="designation_en" value="{{ old('designation_en', $doctor->designation_en) }}" maxlength="255" class="{{ $input }}">
                <x-field-error for="designation_en" />
            </div>
            <div>
                <label for="designation_bn" class="block text-base font-medium">{{ __('Designation (Bangla)') }}</label>
                <input id="designation_bn" name="designation_bn" value="{{ old('designation_bn', $doctor->designation_bn) }}" maxlength="255" class="{{ $input }}">
                <x-field-error for="designation_bn" />
            </div>
            <div>
                <label for="reg_label" class="block text-base font-medium">{{ __('Registration body') }} <x-required-mark /></label>
                <input id="reg_label" name="reg_label" value="{{ old('reg_label', $doctor->reg_label) }}" required maxlength="32" placeholder="{{ __('e.g. BMDC') }}" class="{{ $input }}">
                <x-field-error for="reg_label" />
            </div>
            <div>
                <label for="reg_no" class="block text-base font-medium">{{ __('Registration no.') }}</label>
                <input id="reg_no" name="reg_no" value="{{ old('reg_no', $doctor->reg_no) }}" maxlength="64" class="{{ $input }}">
                <x-field-error for="reg_no" />
            </div>
            <fieldset class="sm:col-span-2">
                <legend class="block text-base font-medium">{{ __('Specialties') }}</legend>
                <p class="text-xs text-gray-500">{{ __('General practice is always included. Tick your specialties from the list, or type one that is missing (it is added to the list for everyone).') }}</p>
                @php
                    // Old input is the validated list (the request turns the checkbox map into ids).
                    $oldIds = old('specialty_ids');
                    $tickedIds = is_array($oldIds)
                        ? (array_is_list($oldIds) ? array_map('intval', $oldIds) : array_map('intval', array_keys(array_filter($oldIds, fn ($v) => $v === '1'))))
                        : ($doctor->exists ? $doctor->specialtyIds() : []);
                    $oldNew = old('new_specialties');
                    $newText = is_array($oldNew) ? implode(', ', $oldNew) : (string) $oldNew;
                @endphp
                <div class="mt-2 flex flex-wrap gap-x-6 gap-y-2">
                    @forelse ($specialties as $specialty)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="specialty_ids[{{ $specialty->id }}]" value="0">
                            <input type="checkbox" name="specialty_ids[{{ $specialty->id }}]" value="1" @checked(in_array($specialty->id, $tickedIds, true))>
                            {{ $specialty->name }}
                        </label>
                    @empty
                        <span class="text-sm text-gray-500">{{ __('The list is empty; type your specialty below.') }}</span>
                    @endforelse
                </div>
                <div class="mt-3" x-data="{ text: @js($newText) }">
                    <label for="new_specialties" class="block text-sm">{{ __('Not in the list? Type it') }}</label>
                    <input id="new_specialties" name="new_specialties" x-model="text" maxlength="500" data-rule="list" data-max-items="5" data-item-min="2" data-item-max="100" placeholder="{{ __('e.g. Cardiology, Diabetology') }}" class="{{ $input }}">
                    <p id="new_specialties-error" class="mt-1 text-sm text-red-700" x-text="err('new_specialties') || rowErr('new_specialties')"></p>
                    <p id="specialty_ids-error" class="mt-1 text-sm text-red-700" x-text="err('specialty_ids') || rowErr('specialty_ids')"></p>
                    <p class="mt-1 text-sm text-gray-500" x-show="text.trim() !== ''">
                        {{ __('Will be added:') }} <span class="font-medium" x-text="text.split(',').map(s => s.trim()).filter(Boolean).join(' · ')"></span>
                    </p>
                </div>
            </fieldset>
            <div>
                <label for="default_template_id" class="block text-base font-medium">{{ __('Default template') }}</label>
                <select id="default_template_id" name="default_template_id" class="{{ $input }}">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}" @selected((string) old('default_template_id', $doctor->default_template_id) === (string) $template->id)>{{ $template->name }}</option>
                    @endforeach
                </select>
                <x-field-error for="default_template_id" />
            </div>
        </div>

        <h2 class="mt-8 text-lg font-semibold">{{ __('Credentials') }}</h2>
        <p class="text-xs text-gray-500">{{ __('One degree or title per row, printed in this order. Leave a row empty to remove it.') }}</p>
        <div class="mt-2 space-y-2">
            @foreach ($credentials as $i => $credential)
                <div class="grid gap-2 sm:grid-cols-2">
                    <input name="credentials[{{ $i }}][text_en]" value="{{ $credential['text_en'] ?? '' }}" maxlength="255" placeholder="{{ __('English, e.g. MBBS, FCPS') }}" class="rounded px-3 py-2 ring-1 ring-gray-300">
                    <input name="credentials[{{ $i }}][text_bn]" value="{{ $credential['text_bn'] ?? '' }}" maxlength="255" placeholder="{{ __('Bangla') }}" class="rounded px-3 py-2 ring-1 ring-gray-300">
                    <p x-show="rowErr('credentials.{{ $i }}')" x-cloak class="col-span-full text-sm text-red-700" x-text="rowErr('credentials.{{ $i }}')"></p>
                </div>
            @endforeach
        </div>

        <h2 class="mt-8 text-lg font-semibold">{{ __('My chambers: visiting hours and fees') }}</h2>
        <p class="text-xs text-gray-500">{{ __('The admin assigns the chambers you work at; you set your hours and fees for each.') }}</p>
        @if ($chambers->isEmpty())
            <p class="mt-2 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ __('No chamber assigned yet. Ask the admin to assign your chambers; you need one to write prescriptions.') }}</p>
        @endif
        @foreach ($chambers as $i => $chamber)
            @php
                $fees = $doctor->fees->where('chamber_id', $chamber->id)->mapWithKeys(fn ($f) => [$f->visit_type->value => $f->amount->toDecimal()]);
                $attached = $chamber;
            @endphp
            <fieldset class="mt-3 rounded border p-4">
                <legend class="px-1 text-sm font-medium">
                    {{ $chamber->name_en }}
                    @unless ($chamber->is_active)
                        <span class="text-xs text-gray-500">{{ __('(closed by the admin)') }}</span>
                    @endunless
                </legend>
                <input type="hidden" name="chambers[{{ $i }}][chamber_id]" value="{{ $chamber->id }}">
                <x-field-error for="chambers.{{ $i }}.chamber_id" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm">{{ __('Visiting hours (English)') }}</label>
                        <textarea name="chambers[{{ $i }}][visiting_hours_en]" rows="2" maxlength="1000" class="{{ $input }}">{{ old("chambers.$i.visiting_hours_en", $attached?->pivot->visiting_hours_en) }}</textarea>
                        <x-field-error for="chambers.{{ $i }}.visiting_hours_en" />
                    </div>
                    <div>
                        <label class="block text-sm">{{ __('Visiting hours (Bangla)') }}</label>
                        <textarea name="chambers[{{ $i }}][visiting_hours_bn]" rows="2" maxlength="1000" class="{{ $input }}">{{ old("chambers.$i.visiting_hours_bn", $attached?->pivot->visiting_hours_bn) }}</textarea>
                        <x-field-error for="chambers.{{ $i }}.visiting_hours_bn" />
                    </div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-4">
                    @foreach ($visitTypes as $type)
                        <div>
                            <label class="block text-sm">{{ __(':type fee (৳)', ['type' => $type->label()]) }}</label>
                            <input name="chambers[{{ $i }}][fees][{{ $type->value }}]" inputmode="decimal" data-rule="money"
                                   value="{{ old("chambers.$i.fees.{$type->value}", $fees[$type->value] ?? '') }}" class="{{ $input }}">
                            <x-field-error for="chambers.{{ $i }}.fees.{{ $type->value }}" />
                        </div>
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <div class="mt-6">
            <button type="submit" class="rounded bg-teal-700 shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none px-4 py-2 text-white">{{ __('Save') }}</button>
        </div>
    </form>
@endsection
