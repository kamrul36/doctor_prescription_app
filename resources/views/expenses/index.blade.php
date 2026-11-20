@extends('layouts.app')

@section('title', __('Expenses'))

@section('content')
    <div class="mb-4 flex flex-wrap items-end gap-4">
        <h1 class="text-2xl font-semibold">{{ __('Expenses') }}</h1>
        <form method="GET" class="ml-auto flex flex-wrap items-end gap-3 text-sm">
            <div>
                <label for="month" class="block text-sm text-gray-600">{{ __('Month') }}</label>
                <input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="mt-1 rounded px-3 py-1.5 ring-1 ring-gray-300">
            </div>
            <div>
                <label for="filter_category" class="block text-sm text-gray-600">{{ __('Category') }}</label>
                <select id="filter_category" name="category_id" class="mt-1 rounded px-3 py-1.5 ring-1 ring-gray-300">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->localized('name') }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded bg-teal-700 px-3 py-1.5 text-white shadow-sm hover:bg-teal-800">{{ __('Filter') }}</button>
        </form>
        @can('manageCategories', \App\Domain\Finance\Models\Expense::class)
            <a href="{{ route('expenses.categories.index') }}" class="rounded px-3 py-1.5 text-sm ring-1 ring-gray-300 hover:bg-gray-50">{{ __('Categories') }}</a>
        @endcan
    </div>

    @can('create', \App\Domain\Finance\Models\Expense::class)
        <form method="POST" action="{{ route('expenses.store') }}" x-data="validatedForm(@js($errors->toArray()))" class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            @csrf
            <h2 class="mb-3 text-lg font-semibold">{{ __('Record an expense') }}</h2>
            <x-form-errors />
            @include('expenses._form')
            <button type="submit" class="mt-4 rounded bg-teal-700 px-4 py-2 text-white shadow-sm hover:bg-teal-800">{{ __('Save') }}</button>
        </form>
    @endcan

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-4 py-2">{{ __('Date') }}</th>
                    <th class="px-4 py-2">{{ __('Category') }}</th>
                    <th class="px-4 py-2">{{ __('Note') }}</th>
                    <th class="px-4 py-2">{{ __('Payment method') }}</th>
                    <th class="px-4 py-2">{{ __('Recorded by') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Amount') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $row)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-2">{{ $row->spent_on->translatedFormat('j M Y') }}</td>
                        <td class="px-4 py-2">{{ $row->category->localized('name') }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $row->note }}</td>
                        <td class="px-4 py-2">{{ $row->method->label() }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $row->recorder?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right font-medium">{{ $row->amount->format() }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $row)
                                <a href="{{ route('expenses.edit', $row) }}" class="hover:underline">{{ __('Edit') }}</a>
                            @endcan
                            @can('delete', $row)
                                <form method="POST" action="{{ route('expenses.destroy', $row) }}" class="ml-3 inline" onsubmit="return confirm(@js(__('Delete this entry?')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-700 hover:underline">{{ __('Delete') }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('Nothing found.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="border-t bg-gray-50">
                <tr>
                    <td colspan="5" class="px-4 py-2 font-semibold">{{ __('Total') }}</td>
                    <td class="px-4 py-2 text-right font-semibold">{{ $total->format() }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
@endsection
