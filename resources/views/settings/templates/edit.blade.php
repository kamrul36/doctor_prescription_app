@extends('layouts.app')

@section('title', __('Edit template'))

{{-- Full window width, so the live preview is large enough to read. --}}
@section('main_width', 'max-w-none')

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">{{ __('Edit template') }}</h1>

    <form method="POST" action="{{ route('settings.templates.update', $template) }}" x-data="validatedForm(@js($errors->toArray()))" class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
        @method('PUT')
        @include('settings.templates._form')
    </form>
@endsection
