<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\CatalogEntry;
use App\Domain\Catalog\Concerns\HasSpecialty;
use App\Domain\Practice\Models\Specialty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $specialty_id null = general (every doctor)
 * @property-read Specialty|null $specialty
 * @property string $name
 * @property string|null $category
 * @property string|null $default_timing_note
 * @property bool $is_active
 */
class LabTest extends Model implements CatalogEntry
{
    use HasSpecialty;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['specialty_id', 'name', 'category', 'default_timing_note', 'is_active'];

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
