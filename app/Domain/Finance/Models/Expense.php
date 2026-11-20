<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Casts\MoneyCast;
use App\Domain\Finance\Money;
use App\Domain\Finance\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Money spent by the chamber (spec §4.6). Soft-deleted, never removed; a
 * deleted expense no longer counts in the reports.
 *
 * @property int $id
 * @property int $category_id
 * @property Money $amount
 * @property Carbon $spent_on
 * @property PaymentMethod $method
 * @property string|null $note
 * @property int|null $recorded_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = ['category_id', 'amount', 'spent_on', 'method', 'note'];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'spent_on' => 'date:Y-m-d',
            'method' => PaymentMethod::class,
        ];
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
