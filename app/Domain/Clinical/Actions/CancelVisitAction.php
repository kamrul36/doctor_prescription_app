<?php

namespace App\Domain\Clinical\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Clinical\VisitStatus;
use App\Domain\Finance\Actions\VoidInvoiceAction;
use App\Domain\Finance\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a draft with a reason. It is kept (never deleted) and leaves the
 * day's list; cancelling finalized prescriptions arrives with amend. Its
 * invoice is voided with the same reason, which is refused while the invoice
 * has an active payment (spec §7.5).
 */
class CancelVisitAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly VoidInvoiceAction $voidInvoice,
    ) {}

    public function handle(User $actor, CaseHistory $case, string $reason): CaseHistory
    {
        if ($case->doctor_id !== SaveVisitAction::doctorFor($actor)->id) {
            throw new AuthorizationException(__('Only the doctor who wrote this prescription can cancel it.'));
        }

        return DB::transaction(function () use ($actor, $case, $reason) {
            $case = CaseHistory::query()->lockForUpdate()->findOrFail($case->id);

            if ($case->status !== VisitStatus::Draft) {
                throw ValidationException::withMessages(['status' => __('Only a draft can be cancelled.')]);
            }

            // Lock order: the visit, then its invoice.
            $invoice = Invoice::query()->where('case_history_id', $case->id)->lockForUpdate()->first();
            if ($invoice !== null && ! $invoice->isVoided()) {
                if ($invoice->payments()->whereNull('voided_at')->exists()) {
                    throw ValidationException::withMessages(['status' => __('This visit has payments. Void them on the invoice before cancelling.')]);
                }
                $this->voidInvoice->handle($actor, $invoice, $reason);
            }

            $case->status = VisitStatus::Cancelled;
            $case->cancelled_at = now();
            $case->cancelled_by = $actor->id;
            $case->cancel_reason = $reason;
            $case->row_version++;
            $case->save();

            $this->audit->log('case.cancelled', $case);

            return $case;
        });
    }
}
