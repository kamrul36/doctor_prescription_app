@extends('layouts.app')

@section('title', $report->title())

@section('content')
    @php($fmt = fn (string $d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('j M Y'))
    @include('reports._header', [
        'period' => $fmt($data['from']).' – '.$fmt($data['to']),
        'fields' => [['from', 'date', __('From'), $data['from']], ['to', 'date', __('To'), $data['to']]],
    ])

    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        @include('reports._tile', ['label' => __('Opening balance'), 'value' => $data['opening']->format()])
        @include('reports._tile', ['label' => __('Money in'), 'value' => $data['money_in']->format(), 'tone' => 'text-emerald-700'])
        @include('reports._tile', ['label' => __('Money out'), 'value' => $data['money_out']->format(), 'tone' => 'text-red-700'])
        @include('reports._tile', ['label' => __('Closing balance'), 'value' => $data['closing']->format()])
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">{{ __('Date') }}</th>
                    <th class="px-4 py-2">{{ __('Reference') }}</th>
                    <th class="px-4 py-2">{{ __('Item') }}</th>
                    <th class="px-4 py-2">{{ __('Payment method') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Money in') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Money out') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b bg-gray-50 text-gray-600">
                    <td class="px-4 py-2" colspan="6">{{ __('Opening balance') }}</td>
                    <td class="px-4 py-2 text-right font-medium">{{ $data['opening']->format() }}</td>
                </tr>
                @forelse ($data['entries'] as $e)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2">{{ $fmt($e['date']) }}</td>
                        <td class="px-4 py-2 font-mono">
                            @if ($e['type'] === 'payment')
                                <a href="{{ route('invoices.show', $e['link_id']) }}" class="text-teal-800 hover:underline">{{ $e['reference'] }}</a>
                            @else
                                {{ $e['reference'] }}
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $e['description'] }}</td>
                        <td class="px-4 py-2">{{ $e['method']->label() }}</td>
                        <td class="px-4 py-2 text-right text-emerald-700">{{ $e['in']?->format() }}</td>
                        <td class="px-4 py-2 text-right text-red-700">{{ $e['out']?->format() }}</td>
                        <td class="px-4 py-2 text-right font-medium">{{ $e['balance']->format() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('Nothing found.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="border-t bg-gray-50 font-semibold">
                <tr>
                    <td class="px-4 py-2" colspan="4">{{ __('Closing balance') }}</td>
                    <td class="px-4 py-2 text-right">{{ $data['money_in']->format() }}</td>
                    <td class="px-4 py-2 text-right">{{ $data['money_out']->format() }}</td>
                    <td class="px-4 py-2 text-right">{{ $data['closing']->format() }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endsection
