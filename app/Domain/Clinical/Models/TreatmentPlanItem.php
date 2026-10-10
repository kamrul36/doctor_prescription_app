<?php

namespace App\Domain\Clinical\Models;

use App\Domain\Finance\Casts\MoneyCast;
use App\Domain\Finance\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * A procedure planned or done (e.g. Scaling, Extraction of 36). Billable
 * items with a fee become invoice lines in P1.7.
 *
 * @property int|null $procedure_id
 * @property string $description
 * @property string|null $tooth_no
 * @property string $status
 * @property Money|null $fee
 * @property bool $is_billable
 */
class TreatmentPlanItem extends Model
{
    protected $fillable = ['procedure_id', 'description', 'tooth_no', 'status', 'fee', 'is_billable', 'sort_order'];

    protected function casts(): array
    {
        return ['fee' => MoneyCast::class, 'is_billable' => 'boolean'];
    }
}
