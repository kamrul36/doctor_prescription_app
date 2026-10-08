<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\CatalogEntry;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\PrescriptionTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * `doctor_id` null = shared with every doctor, otherwise private to that doctor.
 *
 * @property int $id
 * @property int|null $doctor_id
 * @property string $specialty_code
 * @property string $title
 * @property string $text_en
 * @property string|null $text_bn
 * @property int|null $is_default_for_template_id
 * @property bool $is_active
 */
class AdviceTemplate extends Model implements CatalogEntry
{
    use SoftDeletes;

    // `doctor_id` is deliberately not fillable: the owner is set by the Action, never by the client.
    /** @var list<string> */
    protected $fillable = ['specialty_code', 'title', 'text_en', 'text_bn', 'is_default_for_template_id', 'is_active'];

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** @return BelongsTo<PrescriptionTemplate, $this> */
    public function defaultForTemplate(): BelongsTo
    {
        return $this->belongsTo(PrescriptionTemplate::class, 'is_default_for_template_id');
    }

    /** @return list<string> */
    public static function searchColumns(): array
    {
        return ['title'];
    }

    public static function sortColumn(): string
    {
        return 'title';
    }
}
