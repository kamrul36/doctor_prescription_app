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
            'code' => $this->code,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'default_fee' => $this->default_fee?->toDecimal(),
            'is_billable' => $this->is_billable,
            'is_active' => $this->is_active,
        ];
    }
}
