<?php

namespace App\Domain\Practice\Models;

use App\Domain\Practice\SectionKey;
use App\Domain\Practice\SectionZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property SectionKey $section_key
 * @property SectionZone $zone
 */
class TemplateSection extends Model
{
    protected $table = 'prescription_template_sections';

    protected $fillable = ['template_id', 'section_key', 'zone', 'label_en', 'label_bn', 'sort_order', 'is_visible'];

    protected function casts(): array
    {
        return [
            'section_key' => SectionKey::class,
            'zone' => SectionZone::class,
            'is_visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<PrescriptionTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PrescriptionTemplate::class, 'template_id');
    }
}
