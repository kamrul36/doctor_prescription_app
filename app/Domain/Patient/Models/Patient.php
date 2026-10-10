<?php

namespace App\Domain\Patient\Models;

use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Patient\Gender;
use App\Domain\Patient\PatientType;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * `code` is issued once at registration and never changes.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $name_bn
 * @property Carbon|null $dob
 * @property int|null $age_years
 * @property Carbon|null $age_recorded_on
 * @property Gender $gender
 * @property string $phone
 * @property string|null $alt_phone
 * @property string $address
 * @property string|null $occupation
 * @property string|null $blood_group
 * @property PatientType $patient_type
 * @property string|null $allergies
 * @property string|null $conditions
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read string|null $age_text
 */
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name', 'name_bn', 'dob', 'age_years', 'age_recorded_on', 'gender', 'phone', 'alt_phone',
        'address', 'occupation', 'blood_group', 'patient_type', 'allergies', 'conditions', 'notes',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['patient_type' => 'general'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'age_recorded_on' => 'date',
            'age_years' => 'integer',
            'gender' => Gender::class,
            'patient_type' => PatientType::class,
        ];
    }

    /**
     * Visits (prescriptions), newest first.
     *
     * @return HasMany<CaseHistory, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(CaseHistory::class)->orderByDesc('visit_date')->orderByDesc('id');
    }

    protected static function newFactory(): PatientFactory
    {
        return PatientFactory::new();
    }

    /**
     * Whole years: from the date of birth when known, otherwise the age that was
     * told at registration advanced by the time passed since.
     */
    public function ageYears(?Carbon $on = null): ?int
    {
        $on ??= now();

        if ($this->dob !== null) {
            return max(0, (int) $this->dob->diffInYears($on, true));
        }

        if ($this->age_years === null) {
            return null;
        }

        $since = $this->age_recorded_on === null ? 0 : (int) $this->age_recorded_on->diffInYears($on, true);

        return $this->age_years + $since;
    }

    /** "34 y", or "7 m" / "12 d" for a baby whose date of birth is known. */
    public function getAgeTextAttribute(): ?string
    {
        if ($this->dob !== null && $this->dob->diffInYears(now(), true) < 1) {
            $months = (int) $this->dob->diffInMonths(now(), true);

            return $months >= 1 ? "{$months} m" : (int) $this->dob->diffInDays(now(), true).' d';
        }

        $years = $this->ageYears();

        return $years === null ? null : "{$years} y";
    }
}
