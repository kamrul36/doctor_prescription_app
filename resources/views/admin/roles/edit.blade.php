@extends('layouts.app')

@section('title', 'Edit role')

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">Edit role {{ $role->name }}</h1>

    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
        @method('PUT')
        @include('admin.roles._form')
    </form>
@endsection
