@php
    $selected = old('roles', $user->exists ? $user->roles->pluck('name')->all() : []);
@endphp

@include('admin._errors')

@csrf

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="name" class="block text-sm font-medium">Name</label>
        <input id="name" name="name" value="{{ old('name', $user->name) }}" required
               class="mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300">
    </div>
    <div>
        <label for="email" class="block text-sm font-medium">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
               class="mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300">
    </div>
    <div>
        <label for="phone" class="block text-sm font-medium">Phone</label>
        <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}"
               class="mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300">
    </div>
    <div>
        <label for="password" class="block text-sm font-medium">
            Password @if ($user->exists)<span class="font-normal text-gray-500">(leave blank to keep)</span>@endif
        </label>
        <input id="password" name="password" type="password" autocomplete="new-password" @required(! $user->exists)
               class="mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300">
    </div>
</div>

<fieldset class="mt-6">
    <legend class="text-sm font-medium">Roles</legend>
    <div class="mt-2 flex flex-wrap gap-4">
        @foreach ($roles as $role)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="roles[]" value="{{ $role }}" @checked(in_array($role, $selected, true))>
                {{ $role }}
            </label>
        @endforeach
    </div>
</fieldset>

<label class="mt-6 flex items-center gap-2 text-sm">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
    Active (inactive users cannot log in)
</label>

<div class="mt-6 flex gap-3">
    <button type="submit" class="rounded bg-teal-700 shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none px-4 py-2 text-white">Save</button>
    <a href="{{ route('admin.users.index') }}" class="px-4 py-2 text-gray-700">Cancel</a>
</div>
