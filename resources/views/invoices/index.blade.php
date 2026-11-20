@extends('layouts.app')

@section('title', __('Invoices'))

@section('content')
    <div class="mb-4 flex flex-wrap items-end gap-4">
        <h1 class="text-2xl font-semibold">{{ __('Invoices') }}</h1>
        <form method="GET" class="ml-auto flex flex-wrap items-end gap-3 text-sm">
            <div>
                <label for="q" class="block text-sm text-gray-600">{{ __('Search') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Invoice no., patient, ID or phone') }}" class="mt-1 rounded px-3 py-1.5 ring-1 ring-gray-300">
            </div>
            <div>
                <label for="from" class="block text-sm text-gray-600">{{ __('From') }}</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 rounded px-3 py-1.5 ring-1 ring-gray-300">
            </div>
            <div>
                <label for="to" class="block text-sm text-gray-600">{{ __('To') }}</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 rounded px-3 py-1.5 ring-1 ring-gray-300">
            </div>
            <div>
                <label for="status" class="block text-sm text-gray-600">{{ __('Status') }}</label>
                <select id="status" name="status" class="mt-1 rounded px-3 py-1.5 ring-1 ring-gray-300">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-1 pb-2 text-gray-600">
                <input type="checkbox" name="unpaid" value="1" @checked($filters['unpaid'] ?? false)> {{ __('Unpaid only') }}
            </label>
            <button type="submit" class="rounded bg-teal-700 px-3 py-1.5 text-white shadow-sm hover:bg-teal-800">{{ __('Filter') }}</button>
        </form>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">{{ __('Invoice no.') }}</th>
                    <th class="px-4 py-2">{{ __('Date') }}</th>
                    <th class="px-4 py-2">{{ __('Patient') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Total') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Paid') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Due') }}</th>
                    <th class="px-4 py-2">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2 font-mono"><a href="{{ route('invoices.show', $invoice) }}" class="text-teal-800 hover:underline">{{ $invoice->invoice_no }}</a></td>
                        <td class="px-4 py-2">{{ $invoice->issued_at->translatedFormat('j M Y') }}</td>
                        <td class="px-4 py-2">{{ $invoice->patient->localizedName() }} <span class="font-mono text-gray-500">{{ $invoice->patient->code }}</span></td>
                        <td class="px-4 py-2 text-right">{{ $invoice->total->format() }}</td>
                        <td class="px-4 py-2 text-right">{{ $invoice->paid->format() }}</td>
                        <td class="px-4 py-2 text-right font-medium">{{ $invoice->due->format() }}</td>
                        <td class="px-4 py-2">@include('invoices._status', ['status' => $invoice->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('Nothing found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
@endsection
