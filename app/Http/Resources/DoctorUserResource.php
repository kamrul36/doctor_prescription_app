<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user with the doctor role, for the admin list: profile (if any),
 * specialties and chambers.
 *
 * @mixin User
 */
class DoctorUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $doctor = $this->doctor;

        return [
            'user_id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'doctor_id' => $doctor?->id,
            'doctor_name' => $doctor?->name_en,
            'specialties' => $doctor?->specialties->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all() ?? [],
            'chambers' => $doctor?->chambers->map(fn ($c) => ['id' => $c->id, 'name_en' => $c->name_en, 'is_active' => $c->is_active])->all() ?? [],
        ];
    }
}
