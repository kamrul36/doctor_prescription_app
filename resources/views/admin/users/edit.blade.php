@extends('layouts.app')

@section('title', __('Edit user'))

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">{{ __('Edit :name', ['name' => $user->name]) }}</h1>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" x-data="validatedForm(@js($errors->toArray()))" class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
        @method('PUT')
        @include('admin.users._form')
    </form>
@endsection
