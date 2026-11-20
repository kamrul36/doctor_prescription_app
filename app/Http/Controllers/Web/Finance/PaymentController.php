<?php

namespace App\Http\Controllers\Web\Finance;

use App\Domain\Document\ExportService;
use App\Domain\Document\Support\PrintOptions;
use App\Domain\Finance\Actions\VoidPaymentAction;
use App\Domain\Finance\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Document\PrintPrescriptionRequest;
use App\Http\Requests\Finance\VoidRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function void(VoidRequest $request, Payment $payment, VoidPaymentAction $void): RedirectResponse
    {
        Gate::authorize('void', $payment);

        /** @var User $actor */
        $actor = $request->user();
        $void->handle($actor, $payment, $request->validated()['reason']);

        return redirect()->route('invoices.show', $payment->invoice_id)->with('success', __('Payment :no voided.', ['no' => $payment->receipt_no]));
    }

    /** The A5 receipt with the English / বাংলা / bilingual switch. */
    public function receipt(PrintPrescriptionRequest $request, Payment $payment, ExportService $export): Response
    {
        Gate::authorize('view', $payment);

        return response($export->receiptHtml($payment, PrintOptions::fromArray($request->validated()), toolbar: true));
    }
}
