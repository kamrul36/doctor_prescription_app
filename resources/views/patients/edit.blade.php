@extends('layouts.app')

@section('title', 'Edit patient')

@section('content')
    <h1 class="mb-1 text-2xl font-semibold">Edit patient</h1>
    <p class="mb-4 text-sm text-gray-500">Code {{ $patient->code }}</p>

    <form method="POST" action="{{ route('patients.update', $patient) }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('patients._form')
    </form>
@endsection
