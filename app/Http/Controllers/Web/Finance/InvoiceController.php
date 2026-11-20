<?php

namespace App\Http\Controllers\Web\Finance;

use App\Domain\Finance\Actions\ApplyDiscountAction;
use App\Domain\Finance\Actions\RecordPaymentAction;
use App\Domain\Finance\Actions\UpdateInvoiceAction;
use App\Domain\Finance\Actions\UpdateInvoiceLineAction;
use App\Domain\Finance\Actions\VoidInvoiceAction;
use App\Domain\Finance\InvoiceListQuery;
use App\Domain\Finance\InvoiceStatus;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Practice\Models\Doctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ApplyDiscountRequest;
use App\Http\Requests\Finance\RecordPaymentRequest;
use App\Http\Requests\Finance\UpdateInvoiceLineRequest;
use App\Http\Requests\Finance\UpdateInvoiceRequest;
use App\Http\Requests\Finance\VoidRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Invoices and payments at the desk (web, session auth). Same Actions and FormRequests as the API. */
class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Invoice::class);

        $filters = $request->only(['from', 'to', 'status', 'unpaid', 'q']);
        $filters['from'] ??= $request->hasAny(['q', 'status', 'unpaid', 'to']) ? null : now()->toDateString();

        return view('invoices.index', [
            'invoices' => InvoiceListQuery::build($filters)->paginate(25)->withQueryString(),
            'filters' => $filters,
            'statuses' => InvoiceStatus::cases(),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['patient', 'caseHistory.doctor', 'lines', 'payments.receiver', 'underDoctor']);

        return view('invoices.show', [
            'invoice' => $invoice,
            'doctors' => Doctor::query()->orderBy('name_en')->get(['id', 'name_en', 'name_bn']),
            // A fresh key per page; after a failed submit the old one comes back (old()), so a retry stays one payment.
            'paymentKey' => (string) Str::uuid(),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, UpdateInvoiceAction $update): RedirectResponse
    {
        $update->handle($this->actor($request), $invoice, $request->validated());

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice details saved.'));
    }

    public function storePayment(RecordPaymentRequest $request, Invoice $invoice, RecordPaymentAction $record): RedirectResponse
    {
        $payment = $record->handle($this->actor($request), $invoice, $request->validated());

        return redirect()->route('invoices.show', $invoice)
            ->with('success', $payment->wasRecentlyCreated
                ? __('Payment :no recorded.', ['no' => $payment->receipt_no])
                : __('This payment was already recorded as :no.', ['no' => $payment->receipt_no]))
            ->with('receipt_id', $payment->id);
    }

    public function discount(ApplyDiscountRequest $request, Invoice $invoice, ApplyDiscountAction $discount): RedirectResponse
    {
        $discount->handle($this->actor($request), $invoice, $request->validated());

        return redirect()->route('invoices.show', $invoice)->with('success', __('Discount saved.'));
    }

    public function updateLine(UpdateInvoiceLineRequest $request, Invoice $invoice, int $line, UpdateInvoiceLineAction $update): RedirectResponse
    {
        $update->handle($this->actor($request), $invoice, $line, $request->validated());

        return redirect()->route('invoices.show', $invoice)->with('success', __('Price saved.'));
    }

    public function void(VoidRequest $request, Invoice $invoice, VoidInvoiceAction $void): RedirectResponse
    {
        Gate::authorize('void', $invoice);

        $void->handle($this->actor($request), $invoice, $request->validated()['reason']);

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice voided.'));
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
