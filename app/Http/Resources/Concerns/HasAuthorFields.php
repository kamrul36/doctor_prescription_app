<?php

namespace App\Http\Resources\Concerns;

use App\Models\User;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;

/**
 * `created_by` / `updated_by` as `{id, name}` (null when unknown), shown only
 * when the creator and updater are loaded (show, store, update and paginated
 * lists). Typeahead results leave them out.
 */
trait HasAuthorFields
{
    protected function authorFields(bool $withTimestamps = false): MergeValue|MissingValue
    {
        return $this->mergeWhen($this->resource->relationLoaded('creator'), fn () => [
            'created_by' => self::author($this->resource->creator),
            'updated_by' => self::author($this->resource->updater),
            ...($withTimestamps ? ['created_at' => $this->resource->created_at, 'updated_at' => $this->resource->updated_at] : []),
        ]);
    }

    /** @return array{id: int, name: string}|null */
    private static function author(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }
}
