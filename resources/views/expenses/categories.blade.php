@extends('layouts.app')

@section('title', __('Expense categories'))

@section('content')
    @php($input = 'mt-2 w-full rounded px-3 py-2 ring-1 ring-gray-300')
    <h1 class="mb-4 text-2xl font-semibold">{{ __('Expense categories') }}</h1>

    <form method="POST" action="{{ route('expenses.categories.store') }}" x-data="validatedForm(@js($errors->toArray()))" class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        <x-form-errors />
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="name_en" class="block text-base font-medium">{{ __('Name (English)') }} <x-required-mark /></label>
                <input id="name_en" name="name_en" value="{{ old('name_en') }}" required minlength="2" maxlength="100" class="{{ $input }}">
                <x-field-error for="name_en" />
            </div>
            <div>
                <label for="name_bn" class="block text-base font-medium">{{ __('Name (Bangla)') }}</label>
                <input id="name_bn" name="name_bn" value="{{ old('name_bn') }}" maxlength="100" class="{{ $input }}">
                <x-field-error for="name_bn" />
            </div>
            <div class="flex items-end">
                <button type="submit" class="rounded bg-teal-700 px-4 py-2 text-white shadow-sm hover:bg-teal-800">{{ __('Add category') }}</button>
            </div>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">{{ __('Name (English)') }}</th>
                    <th class="px-4 py-2">{{ __('Name (Bangla)') }}</th>
                    <th class="px-4 py-2">{{ __('Expenses') }}</th>
                    <th class="px-4 py-2">{{ __('Status') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2" colspan="5">
                            <form method="POST" action="{{ route('expenses.categories.update', $category) }}" class="grid items-center gap-3 sm:grid-cols-5">
                                @csrf
                                @method('PUT')
                                <label class="sr-only" for="cat-en-{{ $category->id }}">{{ __('Name (English)') }}</label>
                                <input id="cat-en-{{ $category->id }}" name="name_en" value="{{ $category->name_en }}" required minlength="2" maxlength="100" class="rounded px-2 py-1 ring-1 ring-gray-300">
                                <label class="sr-only" for="cat-bn-{{ $category->id }}">{{ __('Name (Bangla)') }}</label>
                                <input id="cat-bn-{{ $category->id }}" name="name_bn" value="{{ $category->name_bn }}" maxlength="100" class="rounded px-2 py-1 ring-1 ring-gray-300">
                                <span class="text-gray-600">{{ $category->expenses_count }}</span>
                                <label class="flex items-center gap-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked($category->is_active)> {{ __('Active') }}
                                </label>
                                <button type="submit" class="justify-self-end rounded px-3 py-1 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('Save') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('Nothing found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
