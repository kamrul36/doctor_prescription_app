<?php

namespace App\Http\Resources;

use App\Domain\Catalog\Models\LabTest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LabTest */
class LabTestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'specialty_id' => $this->specialty_id,
            // Lists eager-load the specialty; a single item loads it on demand.
            'specialty' => $this->specialty_id === null ? null : $this->specialty?->name,
            'name' => $this->name,
            'category' => $this->category,
            'default_timing_note' => $this->default_timing_note,
            'is_active' => $this->is_active,
        ];
    }
}
