<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Finance\Actions\ApplyDiscountAction;
use App\Domain\Finance\Actions\RecordPaymentAction;
use App\Domain\Finance\Actions\UpdateInvoiceAction;
use App\Domain\Finance\Actions\UpdateInvoiceLineAction;
use App\Domain\Finance\Actions\VoidInvoiceAction;
use App\Domain\Finance\InvoiceListQuery;
use App\Domain\Finance\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ApplyDiscountRequest;
use App\Http\Requests\Finance\RecordPaymentRequest;
use App\Http\Requests\Finance\UpdateInvoiceLineRequest;
use App\Http\Requests\Finance\UpdateInvoiceRequest;
use App\Http\Requests\Finance\VoidRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Invoices (spec §8). One per visit, opened when the visit is first saved;
 * procedure lines are added at finalize. Money is a decimal string.
 */
class InvoiceController extends Controller
{
    /** Filters: `from`, `to` (issue date), `status`, `patient_id`, `unpaid=1`, `q` (invoice no., patient name, code or phone). */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Invoice::class);

        $invoices = InvoiceListQuery::build($request->only(['from', 'to', 'status', 'patient_id', 'unpaid', 'q']))
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return InvoiceResource::collection($invoices);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        Gate::authorize('view', $invoice);

        return new InvoiceResource($invoice->load(['patient', 'caseHistory', 'lines', 'payments']));
    }

    /** Ref Dr. (free text), Under Dr. (doctor id) and a note. */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice, UpdateInvoiceAction $update): InvoiceResource
    {
        return new InvoiceResource($update->handle($this->actor($request), $invoice, $request->validated())->load(['patient', 'lines', 'payments']));
    }

    /**
     * Record a payment.
     *
     * Send an `Idempotency-Key` header (any unique string, e.g. a UUID). Repeating the
     * request with the same key returns the first payment (200) instead of a new one (201).
     */
    public function storePayment(RecordPaymentRequest $request, Invoice $invoice, RecordPaymentAction $record): JsonResponse
    {
        $payment = $record->handle($this->actor($request), $invoice, $request->validated());

        return (new PaymentResource($payment))->response()->setStatusCode($payment->wasRecentlyCreated ? 201 : 200);
    }

    /** Set the discount (`finance.discount`); `0` removes it. */
    public function discount(ApplyDiscountRequest $request, Invoice $invoice, ApplyDiscountAction $discount): InvoiceResource
    {
        return new InvoiceResource($discount->handle($this->actor($request), $invoice, $request->validated())->load(['patient', 'lines', 'payments']));
    }

    /** Change a line's price (`finance.discount`), only while nothing is paid. */
    public function updateLine(UpdateInvoiceLineRequest $request, Invoice $invoice, int $line, UpdateInvoiceLineAction $update): InvoiceResource
    {
        return new InvoiceResource($update->handle($this->actor($request), $invoice, $line, $request->validated())->load(['patient', 'lines', 'payments']));
    }

    /** Void the invoice (`finance.void`), only with no active payment. */
    public function void(VoidRequest $request, Invoice $invoice, VoidInvoiceAction $void): InvoiceResource
    {
        Gate::authorize('void', $invoice);

        return new InvoiceResource($void->handle($this->actor($request), $invoice, $request->validated()['reason'])->load(['patient', 'lines', 'payments']));
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
