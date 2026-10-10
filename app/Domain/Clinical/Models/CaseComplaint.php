<?php

namespace App\Domain\Clinical\Models;

use App\Domain\Clinical\PeriodUnit;
use Illuminate\Database\Eloquent\Model;

/**
 * A complaint with how long it has lasted ("Lower abdominal pain, 3 months").
 *
 * @property string $kind
 * @property string $text
 * @property int|null $duration_value
 * @property PeriodUnit|null $duration_unit
 */
class CaseComplaint extends Model
{
    protected $fillable = ['kind', 'text', 'duration_value', 'duration_unit', 'sort_order'];

    protected function casts(): array
    {
        return ['duration_unit' => PeriodUnit::class];
    }
}
