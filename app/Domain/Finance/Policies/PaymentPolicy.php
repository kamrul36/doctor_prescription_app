<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Access\Permission;
use App\Domain\Finance\Models\Payment;
use App\Models\User;

/** Receipts: anyone who reads invoices or takes payments. Voiding: `finance.void`. */
class PaymentPolicy
{
    public function view(User $actor, Payment $payment): bool
    {
        return $actor->can(Permission::FinanceRead->value) || $actor->can(Permission::PaymentsRecord->value);
    }

    public function void(User $actor, Payment $payment): bool
    {
        return $actor->can(Permission::FinanceVoid->value) && $payment->isActive();
    }
}
