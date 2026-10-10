<?php

namespace App\Domain\Clinical\Policies;

use App\Domain\Access\Permission;
use App\Domain\Clinical\Models\CaseHistory;
use App\Models\User;

/**
 * Reading needs `cases.read` (assistants read, no private notes); writing a
 * prescription needs `cases.write`, finalizing `cases.finalize`, and both
 * only by the visit's own doctor while it is a draft. The "own doctor" and
 * "still a draft" rules are enforced again in the Actions, so they hold even
 * for a super admin (who passes every Gate check).
 */
class CaseHistoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::CasesRead->value);
    }

    public function view(User $actor, CaseHistory $case): bool
    {
        return $actor->can(Permission::CasesRead->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::CasesWrite->value);
    }

    public function update(User $actor, CaseHistory $case): bool
    {
        return $actor->can(Permission::CasesWrite->value) && $case->isDraft() && $this->owns($actor, $case);
    }

    public function finalize(User $actor, CaseHistory $case): bool
    {
        return $actor->can(Permission::CasesFinalize->value) && $case->isDraft() && $this->owns($actor, $case);
    }

    public function cancel(User $actor, CaseHistory $case): bool
    {
        return $this->update($actor, $case);
    }

    public function viewPrivateNotes(User $actor, CaseHistory $case): bool
    {
        return $actor->can(Permission::CasesReadPrivate->value) || $this->owns($actor, $case);
    }

    private function owns(User $actor, CaseHistory $case): bool
    {
        return $case->doctor()->where('user_id', $actor->id)->exists();
    }
}
