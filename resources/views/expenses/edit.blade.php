@extends('layouts.app')

@section('title', __('Edit expense'))

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">{{ __('Edit expense') }}</h1>

    <form method="POST" action="{{ route('expenses.update', $expense) }}" x-data="validatedForm(@js($errors->toArray()))" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        <x-form-errors />
        @include('expenses._form')
        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded bg-teal-700 px-4 py-2 text-white shadow-sm hover:bg-teal-800">{{ __('Save') }}</button>
            <a href="{{ route('expenses.index', ['month' => $expense->spent_on->format('Y-m')]) }}" class="px-4 py-2 text-gray-700">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
