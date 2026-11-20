<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Casts\MoneyCast;
use App\Domain\Finance\Money;
use App\Domain\Finance\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money received against an invoice. Never deleted or changed: a mistake is
 * voided with a reason (spec §8.1).
 *
 * @property int $id
 * @property string $receipt_no
 * @property int $invoice_id
 * @property Money $amount
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property int $received_by
 * @property Carbon $received_at
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property string $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'method' => PaymentMethod::class,
            'received_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->voided_at === null;
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
