<?php

namespace App\Http\Resources;

use App\Domain\Practice\Models\PrescriptionTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PrescriptionTemplate */
class TemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doctor_id' => $this->doctor_id,
            'code' => $this->code,
            'name' => $this->name,
            'specialty_code' => $this->specialty_code,
            'paper_size' => $this->paper_size,
            'default_print_mode' => $this->default_print_mode->value,
            'layout' => $this->layout->value,
            'margins' => $this->margins,
            'show_barcode' => $this->show_barcode,
            'barcode_source' => $this->barcode_source,
            'show_branch_footer' => $this->show_branch_footer,
            'show_visiting_hours' => $this->show_visiting_hours,
            'show_signature' => $this->show_signature,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'sections' => $this->whenLoaded('sections', fn () => $this->sections->map(fn ($s) => [
                'section_key' => $s->section_key->value,
                'zone' => $s->zone->value,
                'label_en' => $s->label_en,
                'label_bn' => $s->label_bn,
                'is_visible' => $s->is_visible,
            ])->all()),
            'updated_at' => $this->updated_at,
        ];
    }
}
