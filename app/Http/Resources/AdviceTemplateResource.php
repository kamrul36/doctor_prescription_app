<?php

namespace App\Http\Resources;

use App\Domain\Catalog\Models\AdviceTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdviceTemplate */
class AdviceTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doctor_id' => $this->doctor_id,
            'specialty_id' => $this->specialty_id,
            // Lists eager-load the specialty; a single item loads it on demand.
            'specialty' => $this->specialty_id === null ? null : $this->specialty?->name,
            'title' => $this->title,
            'text_en' => $this->text_en,
            'text_bn' => $this->text_bn,
            'is_default_for_template_id' => $this->is_default_for_template_id,
            'is_active' => $this->is_active,
        ];
    }
}
