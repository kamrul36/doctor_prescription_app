@extends('layouts.app')

@section('title', 'New user')

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">New user</h1>

    <form method="POST" action="{{ route('admin.users.store') }}" class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
        @include('admin.users._form')
    </form>
@endsection
