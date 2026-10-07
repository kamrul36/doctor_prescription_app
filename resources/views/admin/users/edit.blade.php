@extends('layouts.app')

@section('title', 'Edit user')

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">Edit {{ $user->name }}</h1>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
        @method('PUT')
        @include('admin.users._form')
    </form>
@endsection
