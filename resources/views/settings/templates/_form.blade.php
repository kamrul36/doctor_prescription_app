@php
    $input = 'mt-2 w-full rounded px-3 py-2 ring-1 ring-gray-300';
    $margins = old('margins', $template->margins ?? []);
    $rows = old('sections', $sections);
@endphp

@csrf
<x-form-errors />

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <div>
        <label for="name" class="block text-base font-medium">Name <x-required-mark /></label>
        <input id="name" name="name" value="{{ old('name', $template->name) }}" required maxlength="255" class="{{ $input }}">
        <x-field-error for="name" />
    </div>
    <div>
        <label for="code" class="block text-base font-medium">Code <x-required-mark /></label>
        <input id="code" name="code" value="{{ old('code', $template->code) }}" required maxlength="64" data-pattern="[a-z][a-z0-9_]*"
               data-pattern-message="Use lowercase letters, digits and underscores, starting with a letter." class="{{ $input }}">
        <x-field-error for="code" />
        <p class="mt-1 text-sm text-gray-500">Lowercase letters, digits and underscores, e.g. <code>general_a5</code>.</p>
    </div>
    <div>
        <label for="paper_size" class="block text-base font-medium">Paper size <x-required-mark /></label>
        <select id="paper_size" name="paper_size" class="{{ $input }}">
            @foreach (['A4', 'A5'] as $size)
                <option @selected(old('paper_size', $template->paper_size) === $size)>{{ $size }}</option>
            @endforeach
        </select>
        <x-field-error for="paper_size" />
    </div>
    <div>
        <label for="layout" class="block text-base font-medium">Layout <x-required-mark /></label>
        <select id="layout" name="layout" class="{{ $input }}">
            @foreach (\App\Domain\Practice\TemplateLayout::cases() as $layout)
                <option value="{{ $layout->value }}" @selected(old('layout', $template->layout?->value) === $layout->value)>{{ $layout->label() }}</option>
            @endforeach
        </select>
        <x-field-error for="layout" />
    </div>
    <div>
        <label for="default_print_mode" class="block text-base font-medium">Default print mode <x-required-mark /></label>
        <select id="default_print_mode" name="default_print_mode" class="{{ $input }}">
            @foreach (\App\Domain\Practice\PrintMode::cases() as $mode)
                <option value="{{ $mode->value }}" @selected(old('default_print_mode', $template->default_print_mode?->value) === $mode->value)>{{ $mode->label() }}</option>
            @endforeach
        </select>
        <x-field-error for="default_print_mode" />
    </div>
</div>

<h2 class="mt-8 text-lg font-semibold">Margins (mm)</h2>
<p class="text-xs text-gray-500">Used in pad-only mode to keep the text off pre-printed areas.</p>
<div class="mt-2 grid max-w-md grid-cols-4 gap-2">
    @foreach (['top', 'right', 'bottom', 'left'] as $side)
        <div>
            <label class="block text-xs capitalize">{{ $side }}</label>
            <input id="margins.{{ $side }}" name="margins[{{ $side }}]" value="{{ $margins[$side] ?? '' }}" inputmode="numeric" data-rule="integer" data-min="0" data-max="100" class="w-full rounded px-2 py-1.5 ring-1 ring-gray-300">
            <x-field-error for="margins.{{ $side }}" />
        </div>
    @endforeach
</div>

<h2 class="mt-8 text-lg font-semibold">Options</h2>
<div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ([
        'show_barcode' => 'Show barcode', 'show_branch_footer' => 'Show branches in footer',
        'show_visiting_hours' => 'Show visiting hours', 'show_signature' => 'Show signature',
        'is_default' => 'Default template', 'is_active' => 'Active',
    ] as $field => $label)
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="{{ $field }}" value="0">
            <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $template->{$field}))>
            {{ $label }}
        </label>
    @endforeach
    <div class="text-sm">
        <label for="barcode_source">Barcode shows <x-required-mark /></label>
        <select id="barcode_source" name="barcode_source" class="ml-2 rounded px-2 py-1 ring-1 ring-gray-300">
            <option value="prescription_no" @selected(old('barcode_source', $template->barcode_source) === 'prescription_no')>Prescription no.</option>
            <option value="patient_code" @selected(old('barcode_source', $template->barcode_source) === 'patient_code')>Patient ID</option>
        </select>
        <x-field-error for="barcode_source" />
    </div>
</div>

<h2 class="mt-8 text-lg font-semibold">Sections</h2>
<p class="text-xs text-gray-500">Choose which blocks print, in which zone, and under which label. Lower order prints first within a zone. The <code>specialty</code> section prints the blocks of the doctor's specialties and stays empty for a general physician.</p>
<x-field-error for="sections" />
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
                        <input name="sections[{{ $i }}][order]" value="{{ $row['order'] ?? $i + 1 }}" inputmode="numeric" data-rule="integer" data-min="0" data-max="999" class="w-16 rounded px-2 py-1 ring-1 ring-gray-300">
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
                    <td class="py-1 pr-3"><input name="sections[{{ $i }}][label_en]" value="{{ $row['label_en'] ?? '' }}" maxlength="255" class="w-full rounded px-2 py-1 ring-1 ring-gray-300"></td>
                    <td class="py-1"><input name="sections[{{ $i }}][label_bn]" value="{{ $row['label_bn'] ?? '' }}" maxlength="255" class="w-full rounded px-2 py-1 ring-1 ring-gray-300"></td>
                </tr>
                <tr x-show="rowErr('sections.{{ $i }}')" x-cloak>
                    <td colspan="6" class="pb-2 text-sm text-red-700" x-text="rowErr('sections.{{ $i }}')"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="rounded bg-teal-700 shadow-sm transition hover:bg-teal-800 focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 focus:outline-none px-4 py-2 text-white">Save</button>
    <a href="{{ route('settings.templates.index') }}" class="px-4 py-2 text-gray-700">Cancel</a>
</div>
