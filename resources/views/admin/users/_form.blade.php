@php
    // Rules mirror StoreUserRequest / UpdateUserRequest; the form sits inside x-data="validatedForm(...)".
    $selected = old('roles', $user->exists ? $user->roles->pluck('name')->all() : []);
    $input = 'mt-2 w-full rounded px-3 py-2 ring-1 ring-gray-300';
@endphp

@csrf
<x-form-errors />

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="name" class="block text-base font-medium">{{ __('Name') }} <x-required-mark /></label>
        <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" class="{{ $input }}">
        <x-field-error for="name" />
    </div>
    <div>
        <label for="email" class="block text-base font-medium">{{ __('Email') }} <x-required-mark /></label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" class="{{ $input }}">
        <x-field-error for="email" />
    </div>
    <div>
        <label for="phone" class="block text-base font-medium">{{ __('Phone') }}</label>
        <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" maxlength="20" data-rule="phone" placeholder="01XXXXXXXXX" class="{{ $input }}">
        <x-field-error for="phone" />
    </div>
    <div>
        <label for="password" class="block text-base font-medium">
            {{ __('Password') }} @unless ($user->exists)<x-required-mark />@endunless @if ($user->exists)<span class="font-normal text-gray-500">{{ __('(leave blank to keep)') }}</span>@endif
        </label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" @required(! $user->exists) class="{{ $input }}">
        <x-field-error for="password" />
        <p class="mt-1 text-sm text-gray-500">{{ __('At least 8 characters.') }}</p>
    </div>
</div>

<fieldset class="mt-6">
    <legend class="text-sm font-medium">{{ __('Roles') }}</legend>
    <div class="mt-2 flex flex-wrap gap-4">
        @foreach ($roles as $role)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="roles[]" value="{{ $role }}" @checked(in_array($role, $selected, true))>
                {{ $role }}
            </label>
        @endforeach
    </div>
    <p id="roles-error" class="mt-1 text-sm text-red-700" x-text="err('roles') || rowErr('roles')"></p>
</fieldset>

<label class="mt-6 flex items-center gap-2 text-sm">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
    {{ __('Active (inactive users cannot log in)') }}
</label>
<x-field-error for="is_active" />

<div class="mt-6 flex gap-3">
    <button type="submit" class="rounded bg-teal-700 shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none px-4 py-2 text-white">{{ __('Save') }}</button>
    <a href="{{ route('admin.users.index') }}" class="px-4 py-2 text-gray-700">{{ __('Cancel') }}</a>
</div>
