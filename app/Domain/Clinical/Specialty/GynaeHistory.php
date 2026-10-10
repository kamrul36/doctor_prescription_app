<?php

namespace App\Domain\Clinical\Specialty;

use App\Domain\Practice\SpecialtyBlock;

/** Menstrual and obstetric history (sample 2: M/F, Para, MC, LMP). */
final class GynaeHistory implements SpecialtySchema
{
    public function block(): SpecialtyBlock
    {
        return SpecialtyBlock::Gynae;
    }

    public function fields(): array
    {
        return [
            'married_years' => ['label' => 'Married for (years)', 'type' => 'number', 'rules' => ['integer', 'between:0,80']],
            'parity' => ['label' => 'Para', 'type' => 'text', 'rules' => ['string', 'max:32'], 'hint' => 'e.g. P2+1'],
            'menstrual_flow_days' => ['label' => 'Flow (days)', 'type' => 'number', 'rules' => ['integer', 'between:0,30']],
            'cycle_days' => ['label' => 'Cycle (days)', 'type' => 'number', 'rules' => ['integer', 'between:0,120']],
            'cycle_regular' => ['label' => 'Cycle', 'type' => 'select', 'rules' => ['in:regular,irregular'], 'options' => ['regular' => 'Regular', 'irregular' => 'Irregular']],
            'lmp' => ['label' => 'LMP', 'type' => 'date', 'rules' => ['date', 'before_or_equal:today']],
            'withdrawal_bleeding' => ['label' => 'Withdrawal bleeding', 'type' => 'select', 'rules' => ['in:yes,no'], 'options' => ['yes' => 'Yes', 'no' => 'No']],
            'notes' => ['label' => 'Other history', 'type' => 'textarea', 'rules' => ['string', 'max:1000']],
        ];
    }
}
