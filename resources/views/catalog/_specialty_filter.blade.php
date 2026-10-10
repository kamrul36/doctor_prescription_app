{{-- Specialty filter for a catalog list; submits its GET form on change (Alpine). --}}
<select name="specialty" aria-label="Filter by specialty" x-data @change="$el.form.submit()" class="rounded px-2 py-1.5 text-sm ring-1 ring-gray-300">
    <option value="">All specialties</option>
    <option value="{{ \App\Domain\Catalog\CatalogSearch::GENERAL }}" @selected($specialty === \App\Domain\Catalog\CatalogSearch::GENERAL)>General</option>
    @foreach (\App\Domain\Practice\Models\Specialty::options() as $option)
        <option value="{{ $option->id }}" @selected($specialty === (string) $option->id)>{{ $option->name }}</option>
    @endforeach
</select>
