@extends('layouts.app')

@section('title', $report->title())

@section('content')
    @include('reports._header', [
        'period' => __('As of :date', ['date' => \Illuminate\Support\Carbon::parse($data['as_of'])->translatedFormat('j F Y')]),
        'fields' => [['date', 'date', __('As of'), $data['as_of']]],
    ])

    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        @include('reports._tile', ['label' => __('Total due'), 'value' => $data['total']->format(), 'tone' => 'text-red-700'])
        @include('reports._tile', ['label' => __('0–7 days'), 'value' => $data['buckets']['0_7']->format()])
        @include('reports._tile', ['label' => __('8–30 days'), 'value' => $data['buckets']['8_30']->format()])
        @include('reports._tile', ['label' => __('Over 30 days'), 'value' => $data['buckets']['30_plus']->format(), 'tone' => 'text-red-700'])
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">{{ __('Patient') }}</th>
                    <th class="px-4 py-2">{{ __('Phone') }}</th>
                    <th class="px-4 py-2">{{ __('Invoices') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('0–7 days') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('8–30 days') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Over 30 days') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Due') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['patients'] as $p)
                    <tr class="border-b align-top last:border-0">
                        <td class="px-4 py-2"><a href="{{ route('patients.show', $p['patient_id']) }}" class="hover:underline">{{ $p['patient'] }}</a> <span class="font-mono text-gray-500">{{ $p['patient_code'] }}</span></td>
                        <td class="px-4 py-2">{{ $p['phone'] }}</td>
                        <td class="px-4 py-2">
                            @foreach ($p['invoices'] as $inv)
                                <a href="{{ route('invoices.show', $inv['id']) }}" class="block font-mono text-teal-800 hover:underline">{{ $inv['invoice_no'] }} <span class="font-sans text-gray-500">· {{ __(':n days', ['n' => $inv['age_days']]) }}</span></a>
                            @endforeach
                        </td>
                        <td class="px-4 py-2 text-right">{{ $p['buckets']['0_7']->isZero() ? '—' : $p['buckets']['0_7']->format() }}</td>
                        <td class="px-4 py-2 text-right">{{ $p['buckets']['8_30']->isZero() ? '—' : $p['buckets']['8_30']->format() }}</td>
                        <td class="px-4 py-2 text-right">{{ $p['buckets']['30_plus']->isZero() ? '—' : $p['buckets']['30_plus']->format() }}</td>
                        <td class="px-4 py-2 text-right font-semibold">{{ $p['due']->format() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('Nothing is due.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
