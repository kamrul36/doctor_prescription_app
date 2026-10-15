@props(['model'])
{{-- Who created and last updated a record (RecordsAuthors). Unknown authors (older rows) show "—". --}}
<p {{ $attributes->merge(['class' => 'text-sm text-gray-500']) }}>
    {{ __('Created by :name on :date', ['name' => $model->creator?->name ?? '—', 'date' => $model->created_at?->translatedFormat('j M Y, g:i A') ?? '—']) }}
    <span aria-hidden="true">·</span>
    {{ __('Last updated by :name on :date', ['name' => $model->updater?->name ?? '—', 'date' => $model->updated_at?->translatedFormat('j M Y, g:i A') ?? '—']) }}
</p>
