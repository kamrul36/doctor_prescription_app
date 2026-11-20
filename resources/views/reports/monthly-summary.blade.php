@extends('layouts.app')

@section('title', $report->title())

@section('content')
    @php
        $month = \Illuminate\Support\Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        // The trend bars are scaled to the busiest day; widths are runtime values (Tailwind cannot express them).
        $peak = max(1, ...array_map(fn ($d) => max($d['income']->minor(), $d['expenses']->minor()), $data['daily']));
    @endphp
    @include('reports._header', [
        'period' => $month->translatedFormat('F Y'),
        'fields' => [['month', 'month', __('Month'), $data['month']]],
    ])

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        @include('reports._tile', ['label' => __('Income'), 'value' => $data['income']->format(), 'tone' => 'text-emerald-700'])
        @include('reports._tile', ['label' => __('Expenses'), 'value' => $data['expenses']->format(), 'tone' => 'text-red-700'])
        @include('reports._tile', ['label' => __('Net'), 'value' => $data['net']->format(), 'tone' => $data['net']->isNegative() ? 'text-red-700' : 'text-gray-900'])
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold">{{ __('Income by visit type') }}</h2>
            @forelse ($data['income_by_visit_type'] as $row)
                <div class="flex justify-between border-b py-1.5 text-sm last:border-0">
                    <span>{{ $row['visit_type']?->label() ?? __('Other') }}</span>
                    <span class="font-medium">{{ $row['amount']->format() }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('Nothing found.') }}</p>
            @endforelse
        </section>
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold">{{ __('Expenses by category') }}</h2>
            @forelse ($data['expenses_by_category'] as $row)
                <div class="flex justify-between border-b py-1.5 text-sm last:border-0">
                    <span>{{ $row['category'] }}</span>
                    <span class="font-medium">{{ $row['amount']->format() }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('Nothing found.') }}</p>
            @endforelse
        </section>
    </div>

    <section class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <h2 class="px-5 pt-4 text-lg font-semibold">{{ __('Daily trend') }}</h2>
        <table class="w-full text-left text-sm">
            <thead class="border-b">
                <tr>
                    <th class="px-4 py-2">{{ __('Date') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Income') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Expenses') }}</th>
                    <th class="w-1/2 px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['daily'] as $d)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-1.5">{{ \Illuminate\Support\Carbon::parse($d['date'])->translatedFormat('j M, D') }}</td>
                        <td class="px-4 py-1.5 text-right">{{ $d['income']->isZero() ? '—' : $d['income']->format() }}</td>
                        <td class="px-4 py-1.5 text-right">{{ $d['expenses']->isZero() ? '—' : $d['expenses']->format() }}</td>
                        <td class="px-4 py-1.5">
                            <div class="h-2 rounded bg-emerald-500" style="width: {{ round($d['income']->minor() / $peak * 100, 1) }}%"></div>
                            <div class="mt-0.5 h-2 rounded bg-red-400" style="width: {{ round($d['expenses']->minor() / $peak * 100, 1) }}%"></div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection
