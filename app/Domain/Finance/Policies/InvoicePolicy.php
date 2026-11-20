<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Access\Permission;
use App\Domain\Finance\Models\Invoice;
use App\Models\User;

/**
 * By permission only (rule A1; spec §8.5): reading needs `finance.read`,
 * taking money and the desk details `payments.record`, discounts and line
 * prices `finance.discount`, voiding `finance.void`. The money rules
 * (overpayment, void with payments, discount cap) live in the Actions.
 */
class InvoicePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::FinanceRead->value) || $actor->can(Permission::PaymentsRecord->value);
    }

    public function view(User $actor, Invoice $invoice): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, Invoice $invoice): bool
    {
        return $actor->can(Permission::PaymentsRecord->value) && ! $invoice->isVoided();
    }

    public function recordPayment(User $actor, Invoice $invoice): bool
    {
        return $actor->can(Permission::PaymentsRecord->value) && ! $invoice->isVoided();
    }

    public function discount(User $actor, Invoice $invoice): bool
    {
        return $actor->can(Permission::FinanceDiscount->value) && ! $invoice->isVoided();
    }

    public function void(User $actor, Invoice $invoice): bool
    {
        return $actor->can(Permission::FinanceVoid->value) && ! $invoice->isVoided();
    }
}
