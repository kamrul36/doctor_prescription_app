<?php

namespace App\Domain\Clinical\Models;

use App\Domain\Clinical\DoseTiming;
use App\Domain\Clinical\DoseUnit;
use App\Domain\Clinical\DurationUnit;
use App\Domain\Clinical\ValueObjects\DosePattern;
use App\Domain\Clinical\ValueObjects\Duration;
use Illuminate\Database\Eloquent\Model;

/**
 * One Rx line: the name as typed (the medicine catalog is deferred), the
 * `M+N+E` dose, unit, timing and duration.
 *
 * @property string $display_name
 * @property string|null $dose_morning
 * @property string|null $dose_noon
 * @property string|null $dose_night
 * @property DoseUnit|null $dose_unit
 * @property DoseTiming|null $timing
 * @property int|null $duration_value
 * @property DurationUnit|null $duration_unit
 * @property string|null $instruction_en
 */
class PrescribedMedicine extends Model
{
    protected $fillable = [
        'display_name', 'dose_morning', 'dose_noon', 'dose_night', 'dose_unit', 'timing',
        'duration_value', 'duration_unit', 'instruction_en', 'instruction_bn', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['dose_unit' => DoseUnit::class, 'timing' => DoseTiming::class, 'duration_unit' => DurationUnit::class];
    }

    public function dose(): ?DosePattern
    {
        return DosePattern::tryFromSlots($this->dose_morning, $this->dose_noon, $this->dose_night);
    }

    public function duration(): ?Duration
    {
        try {
            return $this->duration_unit === null ? null : Duration::of($this->duration_value, $this->duration_unit);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** "2+0+2 tablet · 7 days · after meal" (English; Bangla printing comes with P1.6). */
    public function summary(): string
    {
        $parts = [];
        if ($dose = $this->dose()) {
            $parts[] = trim($dose->format().' '.mb_strtolower($this->dose_unit?->label() ?? ''));
        }
        if ($duration = $this->duration()) {
            $parts[] = $duration->format();
        }
        if ($this->timing !== null && $this->timing !== DoseTiming::Any) {
            $parts[] = mb_strtolower($this->timing->label());
        }

        return implode(' · ', $parts);
    }
}
