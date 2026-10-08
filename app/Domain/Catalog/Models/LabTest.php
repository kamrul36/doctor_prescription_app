<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\CatalogEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string|null $category
 * @property string|null $default_timing_note
 * @property bool $is_active
 */
class LabTest extends Model implements CatalogEntry
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name', 'category', 'default_timing_note', 'is_active'];

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function searchColumns(): array
    {
        return ['name', 'category'];
    }

    public static function sortColumn(): string
    {
        return 'name';
    }
}
