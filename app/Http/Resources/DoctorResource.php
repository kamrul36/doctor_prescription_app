<?php

namespace App\Http\Resources;

use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\VisitType;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Doctor */
class DoctorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'designation_en' => $this->designation_en,
            'designation_bn' => $this->designation_bn,
            'reg_label' => $this->reg_label,
            'reg_no' => $this->reg_no,
            'specialty_code' => $this->specialty_code,
            'default_template_id' => $this->default_template_id,
            'credentials' => $this->whenLoaded('credentials', fn () => $this->credentials->map(fn ($c) => [
                'text_en' => $c->text_en,
                'text_bn' => $c->text_bn,
            ])->all()),
            'chambers' => $this->whenLoaded('chambers', fn () => $this->chambers->map(function ($chamber) {
                /** @var Pivot&object{visiting_hours_en: ?string, visiting_hours_bn: ?string} $pivot */
                $pivot = $chamber->getRelation('pivot');
                $fees = $this->fees->where('chamber_id', $chamber->id)->keyBy(fn ($fee) => $fee->visit_type->value);

                return [
                    'chamber_id' => $chamber->id,
                    'visiting_hours_en' => $pivot->visiting_hours_en,
                    'visiting_hours_bn' => $pivot->visiting_hours_bn,
                    /** @var array<string, string> visit type => amount (BDT) */
                    'fees' => collect(VisitType::cases())
                        ->filter(fn ($type) => $fees->has($type->value))
                        ->mapWithKeys(fn ($type) => [$type->value => $fees[$type->value]->amount->toDecimal()])
                        ->all(),
                ];
            })->all()),
            'updated_at' => $this->updated_at,
        ];
    }
}
