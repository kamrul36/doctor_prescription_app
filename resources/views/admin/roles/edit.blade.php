@extends('layouts.app')

@section('title', __('Edit role'))

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">{{ __('Edit role :name', ['name' => $role->name]) }}</h1>

    <form method="POST" action="{{ route('admin.roles.update', $role) }}" x-data="validatedForm(@js($errors->toArray()))" class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
        @method('PUT')
        @include('admin.roles._form')
    </form>
@endsection
