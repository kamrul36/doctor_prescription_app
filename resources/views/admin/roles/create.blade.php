@extends('layouts.app')

@section('title', __('New role'))

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">{{ __('New role') }}</h1>

    <form method="POST" action="{{ route('admin.roles.store') }}" x-data="validatedForm(@js($errors->toArray()))" class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
        @include('admin.roles._form')
    </form>
@endsection
