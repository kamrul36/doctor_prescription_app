@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <h1 class="text-2xl font-semibold">{{ config('app.name') }}</h1>
    <p class="mt-2 text-gray-600">Welcome, {{ auth()->user()->name }}.</p>
@endsection
