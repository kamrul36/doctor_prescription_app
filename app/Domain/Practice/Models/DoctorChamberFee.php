<?php

namespace App\Domain\Practice\Models;

use App\Domain\Finance\Casts\MoneyCast;
use App\Domain\Finance\Money;
use App\Domain\Practice\VisitType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property VisitType $visit_type
 * @property Money $amount
 */
class DoctorChamberFee extends Model
{
    protected $fillable = ['doctor_id', 'chamber_id', 'visit_type', 'amount'];

    protected function casts(): array
    {
        return [
            'visit_type' => VisitType::class,
            'amount' => MoneyCast::class,
        ];
    }
}
