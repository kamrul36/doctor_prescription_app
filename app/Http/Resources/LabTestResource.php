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
            'name' => $this->name,
            'category' => $this->category,
            'default_timing_note' => $this->default_timing_note,
            'is_active' => $this->is_active,
        ];
    }
}
