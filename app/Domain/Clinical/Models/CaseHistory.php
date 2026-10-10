<?php

namespace App\Domain\Clinical\Models;

use App\Domain\Clinical\PeriodUnit;
use App\Domain\Clinical\VisitStatus;
use App\Domain\Patient\Models\Patient;
use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\VisitType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One visit: the header of a prescription and the root of its blocks.
 * A draft can be edited by its doctor; a finalized visit is read-only and
 * carries a prescription number and patient/doctor snapshots.
 *
 * @property int $id
 * @property int $patient_id
 * @property int $doctor_id
 * @property int $chamber_id
 * @property int|null $template_id
 * @property int $visit_no_today
 * @property Carbon $visit_date
 * @property VisitType $visit_type
 * @property VisitStatus $status
 * @property string|null $prescription_no
 * @property array<string, mixed>|null $patient_snapshot
 * @property array<string, mixed>|null $doctor_snapshot
 * @property array<string, int|float>|null $vitals
 * @property string|null $diagnosis
 * @property array<string, array<string, mixed>>|null $specialty_data
 * @property int|null $follow_up_value
 * @property PeriodUnit|null $follow_up_unit
 * @property Carbon|null $follow_up_date
 * @property string|null $notes_private
 * @property Carbon|null $finalized_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 */
class CaseHistory extends Model
{
    protected $fillable = [
        'chamber_id', 'template_id', 'visit_type', 'vitals', 'diagnosis', 'specialty_data',
        'follow_up_value', 'follow_up_unit', 'follow_up_date', 'notes_private',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'version' => 1, 'row_version' => 1];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'visit_type' => VisitType::class,
            'status' => VisitStatus::class,
            'patient_snapshot' => 'array',
            'doctor_snapshot' => 'array',
            'vitals' => 'array',
            'specialty_data' => 'array',
            'follow_up_unit' => PeriodUnit::class,
            'follow_up_date' => 'date',
            'finalized_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === VisitStatus::Draft;
    }

    public function isFinalized(): bool
    {
        return $this->status === VisitStatus::Finalized;
    }

    /**
     * @param  Builder<CaseHistory>  $query
     * @return Builder<CaseHistory>
     */
    public function scopeWithBlocks(Builder $query): Builder
    {
        return $query->with(['complaints', 'findings', 'planItems', 'tests', 'medicines', 'advice']);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** @return BelongsTo<Chamber, $this> */
    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    /** @return BelongsTo<PrescriptionTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PrescriptionTemplate::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CaseComplaint, $this> */
    public function complaints(): HasMany
    {
        return $this->hasMany(CaseComplaint::class)->orderBy('sort_order');
    }

    /** @return HasMany<CaseFinding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(CaseFinding::class)->orderBy('sort_order');
    }

    /** @return HasMany<TreatmentPlanItem, $this> */
    public function planItems(): HasMany
    {
        return $this->hasMany(TreatmentPlanItem::class)->orderBy('sort_order');
    }

    /** @return HasMany<OrderedTest, $this> */
    public function tests(): HasMany
    {
        return $this->hasMany(OrderedTest::class)->orderBy('sort_order');
    }

    /** @return HasMany<PrescribedMedicine, $this> */
    public function medicines(): HasMany
    {
        return $this->hasMany(PrescribedMedicine::class)->orderBy('sort_order');
    }

    /** @return HasMany<CaseAdvice, $this> */
    public function advice(): HasMany
    {
        return $this->hasMany(CaseAdvice::class)->orderBy('sort_order');
    }
}
