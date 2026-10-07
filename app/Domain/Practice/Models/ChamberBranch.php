<?php

namespace App\Domain\Practice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property list<string>|null $phones
 */
class ChamberBranch extends Model
{
    protected $fillable = ['chamber_id', 'name_en', 'name_bn', 'phones', 'sort_order'];

    protected function casts(): array
    {
        return ['phones' => 'array'];
    }

    /** @return BelongsTo<Chamber, $this> */
    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }
}
