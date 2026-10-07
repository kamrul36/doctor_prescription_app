<?php

namespace App\Domain\Practice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chamber extends Model
{
    protected $fillable = [
        'name_en', 'name_bn', 'address_en', 'address_bn', 'phone', 'email', 'logo_path', 'letterhead_path',
    ];

    /** @return HasMany<ChamberBranch, $this> */
    public function branches(): HasMany
    {
        return $this->hasMany(ChamberBranch::class)->orderBy('sort_order');
    }
}
