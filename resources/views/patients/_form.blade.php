@php
    // Rules mirror SavePatientRequest; the form sits inside x-data="validatedForm(...)".
    $input = 'mt-2 w-full rounded px-3 py-2 ring-1 ring-gray-300';
    $error = 'mt-1 text-sm text-red-700';
    $value = fn (string $field) => old($field, $patient->{$field});
    $genderValue = old('gender', $patient->gender?->value);
    $typeValue = old('patient_type', $patient->patient_type?->value ?? 'general');
@endphp

<x-form-errors />

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="name" class="block text-base font-medium">{{ __('Name') }} <x-required-mark /></label>
        <input id="name" name="name" value="{{ $value('name') }}" required maxlength="255" class="{{ $input }}">
        <p id="name-error" class="{{ $error }}" x-text="err('name')"></p>
    </div>
    <div>
        <label for="name_bn" class="block text-base font-medium">{{ __('Name (Bangla)') }}</label>
        <input id="name_bn" name="name_bn" value="{{ $value('name_bn') }}" maxlength="255" class="{{ $input }}">
        <p id="name_bn-error" class="{{ $error }}" x-text="err('name_bn')"></p>
    </div>
    <div>
        <label for="dob" class="block text-base font-medium">{{ __('Date of birth') }}</label>
        <input id="dob" type="date" name="dob" max="{{ now()->toDateString() }}" value="{{ old('dob', $patient->dob?->toDateString()) }}"
               data-rule="past-date" x-on:input="ageFromDob($event.target, 'age_years')" class="{{ $input }}">
        <p id="dob-error" class="{{ $error }}" x-text="err('dob')"></p>
        <p class="mt-1 text-sm text-gray-500">{{ __('Optional. The age is filled in from the date of birth.') }}</p>
    </div>
    <div>
        <label for="age_years" class="block text-base font-medium">{{ __('Age (years)') }} <x-required-mark /></label>
        <input id="age_years" name="age_years" inputmode="numeric" value="{{ old('age_years', $patient->ageYears()) }}" required
               data-rule="integer" data-min="0" data-max="130" @readonly(filled(old('dob', $patient->dob)))
               class="{{ $input }} read-only:bg-gray-100">
        <p id="age_years-error" class="{{ $error }}" x-text="err('age_years')"></p>
    </div>
    <div>
        <label for="gender" class="block text-base font-medium">{{ __('Gender') }} <x-required-mark /></label>
        <select id="gender" name="gender" required class="{{ $input }}">
            <option value="">{{ __('Select') }}</option>
            @foreach (\App\Domain\Patient\Gender::cases() as $gender)
                <option value="{{ $gender->value }}" @selected($genderValue === $gender->value)>{{ $gender->label() }}</option>
            @endforeach
        </select>
        <p id="gender-error" class="{{ $error }}" x-text="err('gender')"></p>
    </div>
    <div>
        <label for="blood_group" class="block text-base font-medium">{{ __('Blood group') }}</label>
        <select id="blood_group" name="blood_group" class="{{ $input }}">
            <option value="">{{ __('Unknown') }}</option>
            @foreach (\App\Domain\Patient\BloodGroup::values() as $group)
                <option value="{{ $group }}" @selected($value('blood_group') === $group)>{{ $group }}</option>
            @endforeach
        </select>
        <p id="blood_group-error" class="{{ $error }}" x-text="err('blood_group')"></p>
    </div>
    <div>
        <label for="phone" class="block text-base font-medium">{{ __('Phone') }} <x-required-mark /></label>
        <input id="phone" type="tel" name="phone" value="{{ $value('phone') }}" required maxlength="20" data-rule="phone" placeholder="01XXXXXXXXX" class="{{ $input }}">
        <p id="phone-error" class="{{ $error }}" x-text="err('phone')"></p>
    </div>
    <div>
        <label for="alt_phone" class="block text-base font-medium">{{ __('Alternate phone') }}</label>
        <input id="alt_phone" type="tel" name="alt_phone" value="{{ $value('alt_phone') }}" maxlength="20" data-rule="phone" class="{{ $input }}">
        <p id="alt_phone-error" class="{{ $error }}" x-text="err('alt_phone')"></p>
    </div>
    <div class="sm:col-span-2" x-data="{ personal: @js((bool) old('phone_is_personal', $patient->phone_is_personal ?? true)) }">
        <input type="hidden" name="phone_is_personal" value="0">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="phone_is_personal" value="1" x-model="personal">
            {{ __('This number belongs to the patient') }}
        </label>
        <div x-show="! personal" x-cloak class="mt-3">
            <label for="phone_relation" class="block text-base font-medium">{{ __('Relation with patient') }} <x-required-mark /></label>
            <input id="phone_relation" name="phone_relation" value="{{ $value('phone_relation') }}" maxlength="60" x-bind:required="! personal"
                   placeholder="{{ __('e.g. Father, Wife, Neighbour') }}" class="{{ $input }}">
            <p id="phone_relation-error" class="{{ $error }}" x-text="err('phone_relation')"></p>
        </div>
    </div>
    <div class="sm:col-span-2">
        <label for="address" class="block text-base font-medium">{{ __('Address') }}</label>
        <textarea id="address" name="address" rows="2" maxlength="1000" class="{{ $input }}">{{ $value('address') }}</textarea>
        <p id="address-error" class="{{ $error }}" x-text="err('address')"></p>
    </div>
    <div>
        <label for="occupation" class="block text-base font-medium">{{ __('Occupation') }}</label>
        <input id="occupation" name="occupation" value="{{ $value('occupation') }}" maxlength="255" class="{{ $input }}">
        <p id="occupation-error" class="{{ $error }}" x-text="err('occupation')"></p>
    </div>
    <div>
        <label for="patient_type" class="block text-base font-medium">{{ __('Patient type') }}</label>
        <select id="patient_type" name="patient_type" class="{{ $input }}">
            @foreach (\App\Domain\Patient\PatientType::cases() as $type)
                <option value="{{ $type->value }}" @selected($typeValue === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        <p id="patient_type-error" class="{{ $error }}" x-text="err('patient_type')"></p>
    </div>
    <div>
        <label for="allergies" class="block text-base font-medium">{{ __('Allergies') }}</label>
        <textarea id="allergies" name="allergies" rows="2" maxlength="2000" placeholder="{{ __('e.g. Penicillin - rash') }}" class="{{ $input }}">{{ $value('allergies') }}</textarea>
        <p id="allergies-error" class="{{ $error }}" x-text="err('allergies')"></p>
    </div>
    <div>
        <label for="conditions" class="block text-base font-medium">{{ __('Chronic conditions') }}</label>
        <textarea id="conditions" name="conditions" rows="2" maxlength="2000" placeholder="{{ __('e.g. Diabetes, Hypertension') }}" class="{{ $input }}">{{ $value('conditions') }}</textarea>
        <p id="conditions-error" class="{{ $error }}" x-text="err('conditions')"></p>
    </div>
    <div class="sm:col-span-2">
        <label for="notes" class="block text-base font-medium">{{ __('Notes') }}</label>
        <textarea id="notes" name="notes" rows="2" maxlength="5000" class="{{ $input }}">{{ $value('notes') }}</textarea>
        <p id="notes-error" class="{{ $error }}" x-text="err('notes')"></p>
    </div>
</div>

<div class="mt-6">
    <button type="submit" class="rounded bg-teal-700 shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none px-4 py-2 text-white">{{ __('Save') }}</button>
    <a href="{{ $patient->exists ? route('patients.show', $patient) : route('patients.index') }}" class="ml-3 text-sm text-gray-600 hover:underline">{{ __('Cancel') }}</a>
</div>
