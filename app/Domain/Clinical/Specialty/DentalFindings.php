<?php

namespace App\Domain\Clinical\Specialty;

use App\Domain\Practice\SpecialtyBlock;

/** Dental findings (sample 1). Teeth for procedures go on the treatment plan rows (tooth no.). */
final class DentalFindings implements SpecialtySchema
{
    public function block(): SpecialtyBlock
    {
        return SpecialtyBlock::Dental;
    }

    public function fields(): array
    {
        return [
            'findings' => ['label' => 'Dental findings', 'type' => 'textarea', 'rules' => ['string', 'max:2000'], 'hint' => 'e.g. Calculus ++, 36 deep caries'],
            'oral_hygiene' => ['label' => 'Oral hygiene', 'type' => 'select', 'rules' => ['in:good,fair,poor'], 'options' => ['good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor']],
        ];
    }
}
