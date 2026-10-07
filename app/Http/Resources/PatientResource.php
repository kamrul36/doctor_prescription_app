<?php

namespace App\Http\Resources;

use App\Domain\Patient\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Patient */
class PatientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'name_bn' => $this->name_bn,
            'dob' => $this->dob?->toDateString(),
            'age_years' => $this->ageYears(),
            'age_text' => $this->age_text,
            'gender' => $this->gender->value,
            'phone' => $this->phone,
            'alt_phone' => $this->alt_phone,
            'address' => $this->address,
            'occupation' => $this->occupation,
            'blood_group' => $this->blood_group,
            'patient_type' => $this->patient_type->value,
            'allergies' => $this->allergies,
            'conditions' => $this->conditions,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
