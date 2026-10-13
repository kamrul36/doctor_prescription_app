@extends('layouts.app')

@section('title', __('New patient'))

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">{{ __('New patient') }}</h1>

    <form method="POST" action="{{ route('patients.store') }}" x-data="validatedForm(@js($errors->toArray()))" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @include('patients._form')
    </form>
@endsection
