<?php

namespace App\Domain\Clinical\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Clinical\VisitStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a draft with a reason. It is kept (never deleted) and leaves the
 * day's list; cancelling finalized prescriptions arrives with amend/invoices.
 */
class CancelVisitAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, CaseHistory $case, string $reason): CaseHistory
    {
        if ($case->doctor_id !== SaveVisitAction::doctorFor($actor)->id) {
            throw new AuthorizationException('Only the doctor who wrote this prescription can cancel it.');
        }

        return DB::transaction(function () use ($actor, $case, $reason) {
            $case = CaseHistory::query()->lockForUpdate()->findOrFail($case->id);

            if ($case->status !== VisitStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Only a draft can be cancelled.']);
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
