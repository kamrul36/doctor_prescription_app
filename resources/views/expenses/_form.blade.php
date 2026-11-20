{{-- The expense fields, shared by the add form (expenses.index) and the edit page. --}}
@use('App\Domain\Finance\PaymentMethod')
@php($input = 'mt-2 w-full rounded px-3 py-2 ring-1 ring-gray-300')
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
    <div>
        <label for="spent_on" class="block text-base font-medium">{{ __('Date') }} <x-required-mark /></label>
        <input id="spent_on" type="date" name="spent_on" value="{{ old('spent_on', $expense->spent_on?->toDateString()) }}" max="{{ now()->toDateString() }}" required data-rule="past-date" class="{{ $input }}">
        <x-field-error for="spent_on" />
    </div>
    <div>
        <label for="category_id" class="block text-base font-medium">{{ __('Category') }} <x-required-mark /></label>
        <select id="category_id" name="category_id" required class="{{ $input }}">
            <option value="">—</option>
            @foreach ($categories as $category)
                @if ($category->is_active || $category->id === $expense->category_id)
                    <option value="{{ $category->id }}" @selected((int) old('category_id', $expense->category_id) === $category->id)>{{ $category->localized('name') }}</option>
                @endif
            @endforeach
        </select>
        <x-field-error for="category_id" />
    </div>
    <div>
        <label for="amount" class="block text-base font-medium">{{ __('Amount (৳)') }} <x-required-mark /></label>
        <input id="amount" name="amount" inputmode="decimal" value="{{ old('amount', $expense->amount?->toDecimal()) }}" required data-rule="money" class="{{ $input }}">
        <x-field-error for="amount" />
    </div>
    <div>
        <label for="method" class="block text-base font-medium">{{ __('Payment method') }} <x-required-mark /></label>
        <select id="method" name="method" required class="{{ $input }}">
            @foreach (PaymentMethod::cases() as $method)
                <option value="{{ $method->value }}" @selected(old('method', $expense->method?->value ?? 'cash') === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </select>
        <x-field-error for="method" />
    </div>
    <div>
        <label for="note" class="block text-base font-medium">{{ __('Note') }}</label>
        <input id="note" name="note" value="{{ old('note', $expense->note) }}" maxlength="500" class="{{ $input }}">
        <x-field-error for="note" />
    </div>
</div>
