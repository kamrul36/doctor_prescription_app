@extends('layouts.app')

@section('title', 'Edit template')

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">Edit template</h1>

    <form method="POST" action="{{ route('settings.templates.update', $template) }}" class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
        @method('PUT')
        @include('settings.templates._form')
    </form>
@endsection
