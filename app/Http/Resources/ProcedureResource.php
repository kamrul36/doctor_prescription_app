<?php

namespace App\Http\Resources;

use App\Domain\Catalog\Models\Procedure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Procedure */
class ProcedureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'specialty_id' => $this->specialty_id,
            // Lists eager-load the specialty; a single item loads it on demand.
            'specialty' => $this->specialty_id === null ? null : $this->specialty?->name,
            'code' => $this->code,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'default_fee' => $this->default_fee?->toDecimal(),
            'is_billable' => $this->is_billable,
            'is_active' => $this->is_active,
        ];
    }
}
