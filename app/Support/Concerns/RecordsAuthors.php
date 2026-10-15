<?php

namespace App\Support\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Fills `created_by` and `updated_by` with the signed-in user (session or JWT:
 * `auth:api` makes the API guard the default). Never taken from the request.
 * Without a signed-in user (seeders, console) the columns are left as they are.
 *
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
trait RecordsAuthors
{
    /** Eager-load list for screens and resources that show the authors. */
    public const AUTHORS = ['creator:id,name', 'updater:id,name'];

    public static function bootRecordsAuthors(): void
    {
        static::creating(function (Model $model) {
            $id = Auth::id();
            if ($id !== null) {
                $model->setAttribute('created_by', $model->getAttribute('created_by') ?? $id);
                $model->setAttribute('updated_by', $id);
            }
        });

        static::updating(function (Model $model) {
            $id = Auth::id();
            if ($id !== null) {
                $model->setAttribute('updated_by', $id);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
