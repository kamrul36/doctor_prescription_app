@extends('layouts.app')

@section('title', $invoice->invoice_no)

@use('App\Domain\Finance\Actions\SyncVisitInvoiceAction')
@use('App\Domain\Finance\InvoiceLineKind')
@use('Illuminate\Support\Arr')
@use('App\Domain\Finance\PaymentMethod')
@use('App\Domain\Document\Support\PrintLocale')

@section('content')
    @php
        $input = 'mt-2 w-full rounded px-3 py-2 ring-1 ring-gray-300';
        $hasPayments = $invoice->payments->contains(fn ($p) => $p->isActive());
        $editPrices = auth()->user()->can('discount', $invoice) && ! $hasPayments;
        $case = $invoice->caseHistory;
    @endphp

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">{{ __('Invoice') }}</h1>
        <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-sm">{{ $invoice->invoice_no }}</span>
        @include('invoices._status', ['status' => $invoice->status])
        <span class="text-sm text-gray-600">{{ $invoice->issued_at->translatedFormat('j M Y H:i') }}</span>
        <div class="ml-auto flex gap-2 text-sm">
            <a href="{{ route('patients.show', $invoice->patient_id) }}" class="rounded px-3 py-1.5 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('Patient history') }}</a>
            @if ($case)
                <a href="{{ route('prescriptions.show', $case) }}" class="rounded px-3 py-1.5 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('Prescription') }}{{ $case->prescription_no ? ' '.$case->prescription_no : '' }}</a>
            @endif
        </div>
    </div>

    @if ($invoice->isVoided())
        <p class="mb-4 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ __('Voided :date: :reason', ['date' => $invoice->voided_at->translatedFormat('j M Y H:i'), 'reason' => $invoice->void_reason]) }}</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-3 flex flex-wrap items-baseline gap-x-6 gap-y-1 border-b pb-3 text-sm">
                    <span class="text-base font-semibold">{{ $invoice->patient->localizedName() }}</span>
                    <span class="font-mono text-teal-800">{{ $invoice->patient->code }}</span>
                    <span>{{ $invoice->patient->phone }}</span>
                </div>

                <table class="w-full text-left text-sm">
                    <thead class="border-b text-gray-600">
                        <tr>
                            <th class="py-2 pr-2">{{ __('Code') }}</th>
                            <th class="py-2 pr-2">{{ __('Item') }}</th>
                            <th class="py-2 text-right">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->lines as $line)
                            <tr class="border-b align-top last:border-0">
                                <td class="py-2 pr-2 font-mono text-xs text-gray-500">{{ $line->item_code }}</td>
                                <td class="py-2 pr-2">
                                    {{ $line->localized('description') }}
                                    @if ($line->kind === InvoiceLineKind::Consultation && $line->unit_price->isZero() && $case && $case->visit_type->value !== 'free' && SyncVisitInvoiceAction::feeFor($case) === null && ! $invoice->isVoided())
                                        <span class="mt-1 block text-xs text-amber-700">{{ __('No fee is set for this visit type. Set it in My profile, or enter the price here.') }}</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right">
                                    @if ($editPrices)
                                        <form method="POST" action="{{ route('invoices.lines.update', [$invoice, $line->id]) }}" x-data="validatedForm({})" class="flex items-start justify-end gap-2">
                                            @csrf
                                            @method('PUT')
                                            <label for="price-{{ $line->id }}" class="sr-only">{{ __('Price') }}</label>
                                            <div>
                                                <input id="price-{{ $line->id }}" name="unit_price" value="{{ $line->unit_price->toDecimal() }}" inputmode="decimal" required data-rule="money" class="w-28 rounded px-2 py-1 text-right ring-1 ring-gray-300">
                                                <p class="mt-1 text-xs text-red-700" x-text="err('unit_price')"></p>
                                            </div>
                                            <button type="submit" class="rounded px-2 py-1 text-xs ring-1 ring-gray-300 hover:bg-gray-50">{{ __('Save') }}</button>
                                        </form>
                                    @else
                                        {{ $line->line_total->format() }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @error('unit_price')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror

                <dl class="ml-auto mt-4 grid max-w-xs grid-cols-2 gap-y-1 text-sm">
                    <dt class="text-gray-600">{{ __('Subtotal') }}</dt><dd class="text-right">{{ $invoice->subtotal->format() }}</dd>
                    @unless ($invoice->discount->isZero())
                        <dt class="text-gray-600">{{ __('Discount') }}</dt><dd class="text-right">-{{ $invoice->discount->format() }}</dd>
                        <dd class="col-span-2 text-right text-xs text-gray-500">{{ $invoice->discount_reason }}</dd>
                    @endunless
                    <dt class="font-semibold">{{ __('Total') }}</dt><dd class="text-right font-semibold">{{ $invoice->total->format() }}</dd>
                    <dt class="text-gray-600">{{ __('Paid') }}</dt><dd class="text-right">{{ $invoice->paid->format() }}</dd>
                    <dt class="font-semibold">{{ __('Due') }}</dt><dd class="text-right font-semibold">{{ $invoice->due->format() }}</dd>
                </dl>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-lg font-semibold">{{ __('Payments') }}</h2>
                @if ($invoice->payments->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('No payments yet.') }}</p>
                @else
                    <table class="w-full text-left text-sm">
                        <thead class="border-b text-gray-600">
                            <tr>
                                <th class="py-2 pr-2">{{ __('Receipt no.') }}</th>
                                <th class="py-2 pr-2">{{ __('Date') }}</th>
                                <th class="py-2 pr-2">{{ __('Payment method') }}</th>
                                <th class="py-2 pr-2">{{ __('Received by') }}</th>
                                <th class="py-2 pr-2 text-right">{{ __('Amount') }}</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->payments as $payment)
                                <tr @class(['border-b align-top last:border-0', 'text-gray-400' => ! $payment->isActive()])>
                                    <td class="py-2 pr-2 font-mono">{{ $payment->receipt_no }}</td>
                                    <td class="py-2 pr-2">{{ $payment->received_at->translatedFormat('j M Y H:i') }}</td>
                                    <td class="py-2 pr-2">{{ $payment->method->label() }}{{ $payment->reference ? ' · '.$payment->reference : '' }}</td>
                                    <td class="py-2 pr-2">{{ $payment->receiver?->name }}</td>
                                    <td @class(['py-2 pr-2 text-right', 'line-through' => ! $payment->isActive()])>{{ $payment->amount->format() }}</td>
                                    <td class="py-2 text-right">
                                        <div class="flex flex-wrap justify-end gap-x-3 gap-y-1">
                                            @foreach (PrintLocale::cases() as $printLocale)
                                                <a href="{{ route('payments.receipt', [$payment, 'lang' => $printLocale->value]) }}" target="_blank" rel="noopener" class="text-teal-800 hover:underline">{{ __('Receipt') }} ({{ $printLocale->label() }})</a>
                                            @endforeach
                                        </div>
                                        @if (! $payment->isActive())
                                            <span class="block text-xs">{{ __('Voided: :reason', ['reason' => $payment->void_reason]) }}</span>
                                        @else
                                            @can('void', $payment)
                                                <form method="POST" action="{{ route('payments.void', $payment) }}" class="mt-1 flex justify-end gap-2" x-data
                                                      @submit="const r = prompt(@js(__('Why is this payment voided?'))); if (! r) { $event.preventDefault(); return; } $el.querySelector('[name=reason]').value = r">
                                                    @csrf
                                                    <input type="hidden" name="reason">
                                                    <button type="submit" class="text-xs text-red-700 hover:underline">{{ __('Void payment') }}</button>
                                                </form>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            @can('recordPayment', $invoice)
                @if ($invoice->due->greaterThan(\App\Domain\Finance\Money::zero()))
                    {{-- Each card gets only its own errors; a double click is safe because of the idempotency key. --}}
                    <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" x-data="validatedForm(@js(Arr::only($errors->toArray(), ['amount', 'method', 'reference', 'idempotency_key'])))"
                          class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        @csrf
                        <h2 class="mb-3 text-lg font-semibold">{{ __('Take payment') }}</h2>
                        <x-form-errors />
                        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $paymentKey) }}">
                        <x-field-error for="idempotency_key" />
                        <div>
                            <label for="amount" class="block text-base font-medium">{{ __('Amount (৳)') }} <x-required-mark /></label>
                            <input id="amount" name="amount" inputmode="decimal" value="{{ old('amount', $invoice->due->toDecimal()) }}" required data-rule="money" class="{{ $input }}">
                            <x-field-error for="amount" />
                        </div>
                        <div class="mt-3">
                            <label for="method" class="block text-base font-medium">{{ __('Payment method') }} <x-required-mark /></label>
                            <select id="method" name="method" required class="{{ $input }}">
                                @foreach (PaymentMethod::cases() as $method)
                                    <option value="{{ $method->value }}" @selected(old('method', 'cash') === $method->value)>{{ $method->label() }}</option>
                                @endforeach
                            </select>
                            <x-field-error for="method" />
                        </div>
                        <div class="mt-3">
                            <label for="reference" class="block text-base font-medium">{{ __('Reference') }}</label>
                            <input id="reference" name="reference" value="{{ old('reference') }}" maxlength="100" placeholder="{{ __('Transaction ID for bKash, Nagad, card or bank') }}" class="{{ $input }}">
                            <x-field-error for="reference" />
                        </div>
                        <button type="submit" class="mt-4 w-full rounded bg-teal-700 px-4 py-2 text-white shadow-sm hover:bg-teal-800 disabled:opacity-60">{{ __('Record payment') }}</button>
                    </form>
                @endif
            @endcan

            @can('update', $invoice)
                <form method="POST" action="{{ route('invoices.update', $invoice) }}" x-data="validatedForm(@js(Arr::only($errors->toArray(), ['referred_by', 'under_doctor_id', 'note'])))" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    @csrf
                    @method('PATCH')
                    <h2 class="mb-3 text-lg font-semibold">{{ __('Receipt details') }}</h2>
                    <div>
                        <label for="referred_by" class="block text-base font-medium">{{ __('Ref Dr.') }}</label>
                        <input id="referred_by" name="referred_by" value="{{ old('referred_by', $invoice->referred_by) }}" maxlength="255" class="{{ $input }}">
                        <x-field-error for="referred_by" />
                    </div>
                    <div class="mt-3">
                        <label for="under_doctor_id" class="block text-base font-medium">{{ __('Under Dr.') }}</label>
                        <select id="under_doctor_id" name="under_doctor_id" class="{{ $input }}">
                            <option value="">—</option>
                            @foreach ($doctors as $doctor)
                                <option value="{{ $doctor->id }}" @selected((int) old('under_doctor_id', $invoice->under_doctor_id) === $doctor->id)>{{ $doctor->localized('name') }}</option>
                            @endforeach
                        </select>
                        <x-field-error for="under_doctor_id" />
                    </div>
                    <div class="mt-3">
                        <label for="note" class="block text-base font-medium">{{ __('Note') }}</label>
                        <input id="note" name="note" value="{{ old('note', $invoice->note) }}" maxlength="255" class="{{ $input }}">
                        <x-field-error for="note" />
                    </div>
                    <button type="submit" class="mt-4 rounded px-4 py-2 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('Save') }}</button>
                </form>
            @endcan

            @can('discount', $invoice)
                <form method="POST" action="{{ route('invoices.discount', $invoice) }}" x-data="validatedForm(@js(Arr::only($errors->toArray(), ['discount', 'discount_reason'])))" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    @csrf
                    <h2 class="mb-3 text-lg font-semibold">{{ __('Discount') }}</h2>
                    <div>
                        <label for="discount" class="block text-base font-medium">{{ __('Discount (৳)') }} <x-required-mark /></label>
                        <input id="discount" name="discount" inputmode="decimal" value="{{ old('discount', $invoice->discount->toDecimal()) }}" required data-rule="money" class="{{ $input }}">
                        <x-field-error for="discount" />
                    </div>
                    <div class="mt-3">
                        <label for="discount_reason" class="block text-base font-medium">{{ __('Reason') }}</label>
                        <input id="discount_reason" name="discount_reason" value="{{ old('discount_reason', $invoice->discount_reason) }}" maxlength="255" class="{{ $input }}">
                        <p class="mt-1 text-sm text-gray-500">{{ __('Required when there is a discount.') }}</p>
                        <x-field-error for="discount_reason" />
                    </div>
                    <button type="submit" class="mt-4 rounded px-4 py-2 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('Save discount') }}</button>
                </form>
            @endcan

            @can('void', $invoice)
                @unless ($hasPayments)
                    <form method="POST" action="{{ route('invoices.void', $invoice) }}" x-data="validatedForm(@js(Arr::only($errors->toArray(), ['reason', 'status'])))" class="rounded-xl border border-red-200 bg-white p-6 shadow-sm"
                          onsubmit="return confirm(@js(__('Void this invoice? It cannot take payments afterwards.')))">
                        @csrf
                        <h2 class="mb-3 text-lg font-semibold text-red-800">{{ __('Void invoice') }}</h2>
                        <x-form-errors />
                        <label for="void_reason" class="block text-base font-medium">{{ __('Reason') }} <x-required-mark /></label>
                        <input id="void_reason" name="reason" required minlength="3" maxlength="500" class="{{ $input }}">
                        <x-field-error for="reason" />
                        <button type="submit" class="mt-4 rounded bg-red-700 px-4 py-2 text-white shadow-sm hover:bg-red-800">{{ __('Void invoice') }}</button>
                    </form>
                @endunless
            @endcan
        </div>
    </div>
@endsection
