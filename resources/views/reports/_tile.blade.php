{{-- One stat tile: label above, value below. --}}
<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    <div class="text-sm text-gray-600">{{ $label }}</div>
    <div @class(['mt-1 text-2xl font-semibold', $tone ?? 'text-gray-900'])>{{ $value }}</div>
</div>
