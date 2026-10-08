@extends('layouts.app')

@section('title', $item->exists ? 'Edit advice template' : 'New advice template')

@section('content')
    @include('catalog._tabs')

    <h1 class="mb-4 text-2xl font-semibold">{{ $item->exists ? 'Edit advice template' : 'New advice template' }}</h1>

    <form method="POST" action="{{ $item->exists ? route('catalog.advice-templates.update', $item) : route('catalog.advice-templates.store') }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @if ($item->exists)
            @method('PUT')
        @endif
        @include('admin._errors')
        @php($input = 'mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300')

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="title" class="block text-sm font-medium">Title</label>
                <input id="title" name="title" value="{{ old('title', $item->title) }}" required maxlength="255" class="{{ $input }}">
            </div>
            <div>
                <label for="specialty_code" class="block text-sm font-medium">Specialty</label>
                <select id="specialty_code" name="specialty_code" class="{{ $input }}">
                    @foreach ($specialties as $code => $label)
                        <option value="{{ $code }}" @selected(old('specialty_code', $item->specialty_code ?? 'general') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="is_default_for_template_id" class="block text-sm font-medium">Default advice for template</label>
                <select id="is_default_for_template_id" name="is_default_for_template_id" class="{{ $input }}">
                    <option value="">None</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}" @selected((int) old('is_default_for_template_id', $item->is_default_for_template_id) === $template->id)>{{ $template->name }} ({{ $specialties[$template->specialty_code] ?? $template->specialty_code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="text_en" class="block text-sm font-medium">Text (English)</label>
                <textarea id="text_en" name="text_en" rows="5" required maxlength="5000" class="{{ $input }}">{{ old('text_en', $item->text_en) }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <label for="text_bn" class="block text-sm font-medium">Text (Bangla)</label>
                <textarea id="text_bn" name="text_bn" rows="5" maxlength="5000" class="{{ $input }}">{{ old('text_bn', $item->text_bn) }}</textarea>
            </div>
        </div>

        <label class="mt-4 flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true))>
            Active (inactive entries are hidden from search)
        </label>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded bg-teal-700 px-4 py-2 text-white shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none">Save</button>
            <a href="{{ route('catalog.advice-templates.index') }}" class="px-4 py-2 text-gray-700">Cancel</a>
        </div>
    </form>
@endsection

