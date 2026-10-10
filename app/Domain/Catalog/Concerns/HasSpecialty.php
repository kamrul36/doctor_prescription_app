<?php

namespace App\Domain\Catalog\Concerns;

use App\Domain\Practice\Models\Specialty;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A catalog item tagged with an optional specialty (null = general, for every doctor). */
trait HasSpecialty
{
    /** @return BelongsTo<Specialty, $this> */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }
}
