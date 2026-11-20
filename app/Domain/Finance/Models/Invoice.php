<?php

namespace App\Domain\Finance\Models;

use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Finance\Casts\MoneyCast;
use App\Domain\Finance\InvoiceStatus;
use App\Domain\Finance\Money;
use App\Domain\Patient\Models\Patient;
use App\Domain\Practice\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One bill per visit (spec §4.6, §8.2). The aggregate root of its lines and
 * payments: Actions change them only through the invoice, with the invoice
 * row locked. The money columns are recomputed by InvoiceTotals.
 *
 * @property int $id
 * @property string $invoice_no
 * @property int $patient_id
 * @property int|null $case_history_id
 * @property InvoiceStatus $status
 * @property Money $subtotal
 * @property Money $discount
 * @property string|null $discount_reason
 * @property Money $total
 * @property Money $paid
 * @property Money $due
 * @property string|null $referred_by
 * @property int|null $under_doctor_id
 * @property string|null $note
 * @property Carbon $issued_at
 * @property int|null $issued_by
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Invoice extends Model
{
    protected $fillable = ['referred_by', 'under_doctor_id', 'note'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'issued', 'subtotal' => '0.00', 'discount' => '0.00', 'total' => '0.00', 'paid' => '0.00', 'due' => '0.00',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal' => MoneyCast::class,
            'discount' => MoneyCast::class,
            'total' => MoneyCast::class,
            'paid' => MoneyCast::class,
            'due' => MoneyCast::class,
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function isVoided(): bool
    {
        return $this->status === InvoiceStatus::Voided;
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /** @return BelongsTo<CaseHistory, $this> */
    public function caseHistory(): BelongsTo
    {
        return $this->belongsTo(CaseHistory::class);
    }

    /** @return BelongsTo<Doctor, $this> */
    public function underDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'under_doctor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('received_at')->orderBy('id');
    }
}
