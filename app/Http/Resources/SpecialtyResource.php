<?php

namespace App\Http\Resources;

use App\Domain\Practice\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Specialty */
class SpecialtyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'block' => $this->block?->value,
            'is_active' => $this->is_active,
            'doctors_count' => $this->whenCounted('doctors'),
        ];
    }
}
