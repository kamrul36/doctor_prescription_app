@extends('layouts.app')

@section('title', 'New role')

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">New role</h1>

    <form method="POST" action="{{ route('admin.roles.store') }}" class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
        @include('admin.roles._form')
    </form>
@endsection
