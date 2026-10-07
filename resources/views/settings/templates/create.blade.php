@extends('layouts.app')

@section('title', 'New template')

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">New template</h1>

    <form method="POST" action="{{ route('settings.templates.store') }}" class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
        
        @include('settings.templates._form')
    </form>
@endsection
