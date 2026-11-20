@extends('layouts.app')

@section('title', $report->title())

@section('content')
    @php($day = \Illuminate\Support\Carbon::parse($data['date']))
    @include('reports._header', [
        'period' => $day->translatedFormat('l, j F Y'),
        'fields' => [['date', 'date', __('Date'), $data['date']]],
    ])

    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        @include('reports._tile', ['label' => __('Collected'), 'value' => $data['collected']->format(), 'tone' => 'text-emerald-700'])
        @include('reports._tile', ['label' => __('Invoiced'), 'value' => $data['invoiced']->format()])
        @include('reports._tile', ['label' => __('Invoices'), 'value' => $data['invoice_count']])
        @include('reports._tile', ['label' => __('Patients'), 'value' => $data['patient_count']])
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold">{{ __('By payment method') }}</h2>
            @forelse ($data['by_method'] as $row)
                <div class="flex justify-between border-b py-1.5 text-sm last:border-0">
                    <span>{{ $row['method']->label() }} <span class="text-gray-500">({{ $row['count'] }})</span></span>
                    <span class="font-medium">{{ $row['amount']->format() }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('No payments yet.') }}</p>
            @endforelse
        </section>

        <section class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-2">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="px-4 py-2">{{ __('Time') }}</th>
                        <th class="px-4 py-2">{{ __('Receipt no.') }}</th>
                        <th class="px-4 py-2">{{ __('Patient') }}</th>
                        <th class="px-4 py-2">{{ __('Payment method') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['payments'] as $p)
                        <tr class="border-b last:border-0">
                            <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::parse($p['received_at'])->format('H:i') }}</td>
                            <td class="px-4 py-2 font-mono"><a href="{{ route('invoices.show', $p['invoice_id']) }}" class="text-teal-800 hover:underline">{{ $p['receipt_no'] }}</a></td>
                            <td class="px-4 py-2">{{ $p['patient'] }} <span class="font-mono text-gray-500">{{ $p['patient_code'] }}</span></td>
                            <td class="px-4 py-2">{{ $p['method']->label() }}</td>
                            <td class="px-4 py-2 text-right">{{ $p['amount']->format() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('No payments yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection
