@php
    $input = 'mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300';
    $margins = old('margins', $template->margins ?? []);
    $rows = old('sections', $sections);
@endphp

@csrf
@include('admin._errors')

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <div>
        <label for="name" class="block text-sm font-medium">Name</label>
        <input id="name" name="name" value="{{ old('name', $template->name) }}" required class="{{ $input }}">
    </div>
    <div>
        <label for="code" class="block text-sm font-medium">Code</label>
        <input id="code" name="code" value="{{ old('code', $template->code) }}" required pattern="[a-z][a-z0-9_]*" class="{{ $input }}">
        <p class="mt-1 text-xs text-gray-500">Lowercase letters, digits and underscores, e.g. <code>dental_pad</code>.</p>
    </div>
    <div>
        <label for="specialty_code" class="block text-sm font-medium">Specialty</label>
        <select id="specialty_code" name="specialty_code" class="{{ $input }}">
            @foreach ($specialties as $code => $label)
                <option value="{{ $code }}" @selected(old('specialty_code', $template->specialty_code) === $code)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="paper_size" class="block text-sm font-medium">Paper size</label>
        <select id="paper_size" name="paper_size" class="{{ $input }}">
            @foreach (['A4', 'A5'] as $size)
                <option @selected(old('paper_size', $template->paper_size) === $size)>{{ $size }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="layout" class="block text-sm font-medium">Layout</label>
        <select id="layout" name="layout" class="{{ $input }}">
            @foreach (\App\Domain\Practice\TemplateLayout::cases() as $layout)
                <option value="{{ $layout->value }}" @selected(old('layout', $template->layout?->value) === $layout->value)>{{ $layout->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="default_print_mode" class="block text-sm font-medium">Default print mode</label>
        <select id="default_print_mode" name="default_print_mode" class="{{ $input }}">
            @foreach (\App\Domain\Practice\PrintMode::cases() as $mode)
                <option value="{{ $mode->value }}" @selected(old('default_print_mode', $template->default_print_mode?->value) === $mode->value)>{{ $mode->label() }}</option>
            @endforeach
        </select>
    </div>
</div>

<h2 class="mt-8 text-lg font-semibold">Margins (mm)</h2>
<p class="text-xs text-gray-500">Used in pad-only mode to keep the text off pre-printed areas.</p>
<div class="mt-2 grid max-w-md grid-cols-4 gap-2">
    @foreach (['top', 'right', 'bottom', 'left'] as $side)
        <div>
            <label class="block text-xs capitalize">{{ $side }}</label>
            <input type="number" min="0" max="100" name="margins[{{ $side }}]" value="{{ $margins[$side] ?? '' }}" class="w-full rounded px-2 py-1.5 ring-1 ring-gray-300">
        </div>
    @endforeach
</div>

<h2 class="mt-8 text-lg font-semibold">Options</h2>
<div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ([
        'show_barcode' => 'Show barcode', 'show_branch_footer' => 'Show branches in footer',
        'show_visiting_hours' => 'Show visiting hours', 'show_signature' => 'Show signature',
        'is_default' => 'Default for its specialty', 'is_active' => 'Active',
    ] as $field => $label)
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="{{ $field }}" value="0">
            <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $template->{$field}))>
            {{ $label }}
        </label>
    @endforeach
    <div class="text-sm">
        <label for="barcode_source">Barcode shows</label>
        <select id="barcode_source" name="barcode_source" class="ml-2 rounded px-2 py-1 ring-1 ring-gray-300">
            <option value="prescription_no" @selected(old('barcode_source', $template->barcode_source) === 'prescription_no')>Prescription no.</option>
            <option value="patient_code" @selected(old('barcode_source', $template->barcode_source) === 'patient_code')>Patient ID</option>
        </select>
    </div>
</div>

<h2 class="mt-8 text-lg font-semibold">Sections</h2>
<p class="text-xs text-gray-500">Choose which blocks print, in which zone, and under which label. Lower order prints first within a zone.</p>
<div class="mt-2 overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="border-b">
            <tr>
                <th class="py-1 pr-3">Order</th>
                <th class="py-1 pr-3">Section</th>
                <th class="py-1 pr-3">Show</th>
                <th class="py-1 pr-3">Zone</th>
                <th class="py-1 pr-3">Label (English)</th>
                <th class="py-1">Label (Bangla)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $row)
                <tr class="border-b last:border-0">
                    <td class="py-1 pr-3">
                        <input type="number" min="0" name="sections[{{ $i }}][order]" value="{{ $row['order'] ?? $i + 1 }}" class="w-16 rounded px-2 py-1 ring-1 ring-gray-300">
                    </td>
                    <td class="py-1 pr-3">
                        <input type="hidden" name="sections[{{ $i }}][section_key]" value="{{ $row['section_key'] }}">
                        <code>{{ $row['section_key'] }}</code>
                    </td>
                    <td class="py-1 pr-3">
                        <input type="hidden" name="sections[{{ $i }}][is_visible]" value="0">
                        <input type="checkbox" name="sections[{{ $i }}][is_visible]" value="1" @checked($row['is_visible'] ?? false)>
                    </td>
                    <td class="py-1 pr-3">
                        <select name="sections[{{ $i }}][zone]" class="rounded px-2 py-1 ring-1 ring-gray-300">
                            @foreach (\App\Domain\Practice\SectionZone::cases() as $zone)
                                <option value="{{ $zone->value }}" @selected(($row['zone'] ?? null) === $zone->value)>{{ ucfirst($zone->value) }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="py-1 pr-3"><input name="sections[{{ $i }}][label_en]" value="{{ $row['label_en'] ?? '' }}" class="w-full rounded px-2 py-1 ring-1 ring-gray-300"></td>
                    <td class="py-1"><input name="sections[{{ $i }}][label_bn]" value="{{ $row['label_bn'] ?? '' }}" class="w-full rounded px-2 py-1 ring-1 ring-gray-300"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="rounded bg-teal-700 shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none px-4 py-2 text-white">Save</button>
    <a href="{{ route('settings.templates.index') }}" class="px-4 py-2 text-gray-700">Cancel</a>
</div>
