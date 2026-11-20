{{--
    Title, period form, CSV download and Print for a report page.
    $fields: list of [name, type, label, value] for the period inputs.
--}}
<div class="mb-4 flex flex-wrap items-end gap-4 print:mb-2">
    <div>
        <h1 class="text-2xl font-semibold">{{ $report->title() }}</h1>
        <p class="text-sm text-gray-600">{{ $period }}</p>
    </div>
    <form method="GET" class="ml-auto flex flex-wrap items-end gap-3 text-sm print:hidden">
        @foreach ($fields as [$name, $type, $label, $value])
            <div>
                <label for="{{ $name }}" class="block text-sm text-gray-600">{{ $label }}</label>
                <input id="{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" class="mt-1 rounded px-3 py-1.5 ring-1 ring-gray-300">
            </div>
        @endforeach
        <button type="submit" class="rounded bg-teal-700 px-3 py-1.5 text-white shadow-sm hover:bg-teal-800">{{ __('Show') }}</button>
        <a href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}" class="rounded px-3 py-1.5 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('Download CSV') }}</a>
        <button type="button" onclick="window.print()" class="rounded px-3 py-1.5 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('Print / Save as PDF') }}</button>
    </form>
</div>
@error('date')<p class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
@error('to')<p class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
