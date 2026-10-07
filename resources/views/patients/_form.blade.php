@php
    $input = 'mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300';
    $value = fn (string $field) => old($field, $patient->{$field});
    $genderValue = old('gender', $patient->gender?->value);
    $typeValue = old('patient_type', $patient->patient_type?->value ?? 'general');
@endphp

@include('admin._errors')

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="name" class="block text-sm font-medium">Name</label>
        <input id="name" name="name" value="{{ $value('name') }}" required class="{{ $input }}">
    </div>
    <div>
        <label for="name_bn" class="block text-sm font-medium">Name (Bangla)</label>
        <input id="name_bn" name="name_bn" value="{{ $value('name_bn') }}" class="{{ $input }}">
    </div>
    <div>
        <label for="dob" class="block text-sm font-medium">Date of birth</label>
        <input id="dob" type="date" name="dob" max="{{ now()->toDateString() }}" value="{{ old('dob', $patient->dob?->toDateString()) }}" class="{{ $input }}">
        <p class="mt-1 text-xs text-gray-500">If unknown, enter the age instead.</p>
    </div>
    <div>
        <label for="age_years" class="block text-sm font-medium">Age (years)</label>
        <input id="age_years" type="number" min="0" max="130" name="age_years" value="{{ old('age_years', $patient->dob ? null : $patient->ageYears()) }}" class="{{ $input }}">
    </div>
    <div>
        <label for="gender" class="block text-sm font-medium">Gender</label>
        <select id="gender" name="gender" required class="{{ $input }}">
            <option value="">Select</option>
            @foreach (\App\Domain\Patient\Gender::cases() as $gender)
                <option value="{{ $gender->value }}" @selected($genderValue === $gender->value)>{{ $gender->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="blood_group" class="block text-sm font-medium">Blood group</label>
        <select id="blood_group" name="blood_group" class="{{ $input }}">
            <option value="">Unknown</option>
            @foreach (\App\Domain\Patient\BloodGroup::values() as $group)
                <option value="{{ $group }}" @selected($value('blood_group') === $group)>{{ $group }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="phone" class="block text-sm font-medium">Phone</label>
        <input id="phone" type="tel" name="phone" value="{{ $value('phone') }}" required class="{{ $input }}">
    </div>
    <div>
        <label for="alt_phone" class="block text-sm font-medium">Alternate phone</label>
        <input id="alt_phone" type="tel" name="alt_phone" value="{{ $value('alt_phone') }}" class="{{ $input }}">
    </div>
    <div class="sm:col-span-2">
        <label for="address" class="block text-sm font-medium">Address</label>
        <textarea id="address" name="address" rows="2" required class="{{ $input }}">{{ $value('address') }}</textarea>
    </div>
    <div>
        <label for="occupation" class="block text-sm font-medium">Occupation</label>
        <input id="occupation" name="occupation" value="{{ $value('occupation') }}" class="{{ $input }}">
    </div>
    <div>
        <label for="patient_type" class="block text-sm font-medium">Patient type</label>
        <select id="patient_type" name="patient_type" class="{{ $input }}">
            @foreach (\App\Domain\Patient\PatientType::cases() as $type)
                <option value="{{ $type->value }}" @selected($typeValue === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="allergies" class="block text-sm font-medium">Allergies</label>
        <textarea id="allergies" name="allergies" rows="2" placeholder="e.g. Penicillin - rash" class="{{ $input }}">{{ $value('allergies') }}</textarea>
    </div>
    <div>
        <label for="conditions" class="block text-sm font-medium">Chronic conditions</label>
        <textarea id="conditions" name="conditions" rows="2" placeholder="e.g. Diabetes, Hypertension" class="{{ $input }}">{{ $value('conditions') }}</textarea>
    </div>
    <div class="sm:col-span-2">
        <label for="notes" class="block text-sm font-medium">Notes</label>
        <textarea id="notes" name="notes" rows="2" class="{{ $input }}">{{ $value('notes') }}</textarea>
    </div>
</div>

<div class="mt-6">
    <button type="submit" class="rounded bg-teal-700 shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none px-4 py-2 text-white">Save</button>
    <a href="{{ $patient->exists ? route('patients.show', $patient) : route('patients.index') }}" class="ml-3 text-sm text-gray-600 hover:underline">Cancel</a>
</div>
