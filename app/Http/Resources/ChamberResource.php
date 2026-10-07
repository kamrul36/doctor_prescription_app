<?php

namespace App\Http\Resources;

use App\Domain\Practice\Models\Chamber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Chamber */
class ChamberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'address_en' => $this->address_en,
            'address_bn' => $this->address_bn,
            'phone' => $this->phone,
            'email' => $this->email,
            'branches' => $this->whenLoaded('branches', fn () => $this->branches->map(fn ($b) => [
                'id' => $b->id,
                'name_en' => $b->name_en,
                'name_bn' => $b->name_bn,
                'phones' => $b->phones ?? [],
            ])->all()),
            'updated_at' => $this->updated_at,
        ];
    }
}
