<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Document\ExportService;
use App\Domain\Document\Support\PrintOptions;
use App\Domain\Finance\Actions\VoidPaymentAction;
use App\Domain\Finance\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Document\PrintPrescriptionRequest;
use App\Http\Requests\Finance\VoidRequest;
use App\Http\Resources\PaymentResource;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    /** Void a payment (`finance.void`) with a reason. It stays on record and stops counting. */
    public function void(VoidRequest $request, Payment $payment, VoidPaymentAction $void): PaymentResource
    {
        Gate::authorize('void', $payment);

        /** @var User $actor */
        $actor = $request->user();

        return new PaymentResource($void->handle($actor, $payment, $request->validated()['reason']));
    }

    /**
     * The A5 money receipt as HTML (print it or save it as PDF in a browser).
     *
     * `lang`: `en`, `bn` or `bilingual` (default: the user's language); `bn_digits=0|1`.
     */
    public function receipt(PrintPrescriptionRequest $request, Payment $payment, ExportService $export): Response
    {
        Gate::authorize('view', $payment);

        return response($export->receiptHtml($payment, PrintOptions::fromArray($request->validated())))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
