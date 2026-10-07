@php
    $checked = old('permissions', $granted);
@endphp

@include('admin._errors')

@csrf

<div class="max-w-sm">
    <label for="name" class="block text-sm font-medium">Name</label>
    <input id="name" name="name" value="{{ old('name', $role->name) }}" required pattern="[a-z][a-z0-9_]*"
           class="mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300">
    <p class="mt-1 text-xs text-gray-500">Lowercase letters, digits and underscores, e.g. <code>receptionist</code>.</p>
</div>

<div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($groups as $group => $permissions)
        <fieldset>
            <legend class="text-sm font-semibold">{{ $group }}</legend>
            <div class="mt-2 space-y-1">
                @foreach ($permissions as $permission)
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->value }}" class="mt-1"
                               @checked(in_array($permission->value, $checked, true))>
                        <span>
                            {{ $permission->label() }}
                            <span class="block text-xs text-gray-500">{{ $permission->value }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="rounded bg-teal-700 shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none px-4 py-2 text-white">Save</button>
    <a href="{{ route('admin.roles.index') }}" class="px-4 py-2 text-gray-700">Cancel</a>
</div>
