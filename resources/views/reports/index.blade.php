@extends('layouts.app')

@section('title', __('Reports'))

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">{{ __('Reports') }}</h1>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($reports as $report)
            <a href="{{ route('reports.show', $report) }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-teal-300 hover:bg-teal-50">
                <h2 class="text-lg font-semibold text-teal-800">{{ $report->title() }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $report->summary() }}</p>
            </a>
        @endforeach
    </div>
@endsection
