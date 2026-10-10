<?php

namespace App\Domain\Practice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A chamber (practice location), created by the admin and assigned to doctors.
 * Deactivated, never deleted.
 *
 * @property int $id
 * @property string $name_en
 * @property bool $is_active
 */
class Chamber extends Model
{
    protected $fillable = [
        'name_en', 'name_bn', 'address_en', 'address_bn', 'phone', 'email', 'logo_path', 'letterhead_path', 'is_active',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsToMany<Doctor, $this> */
    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_chambers')
            ->withPivot(['visiting_hours_en', 'visiting_hours_bn'])
            ->withTimestamps();
    }

    /** @return HasMany<ChamberBranch, $this> */
    public function branches(): HasMany
    {
        return $this->hasMany(ChamberBranch::class)->orderBy('sort_order');
    }
}
