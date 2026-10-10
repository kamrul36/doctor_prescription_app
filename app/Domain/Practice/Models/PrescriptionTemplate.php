<?php

namespace App\Domain\Practice\Models;

use App\Domain\Practice\PrintMode;
use App\Domain\Practice\TemplateLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property TemplateLayout $layout
 * @property PrintMode $default_print_mode
 * @property array<string, int|null>|null $margins
 */
class PrescriptionTemplate extends Model
{
    protected $fillable = [
        'doctor_id', 'code', 'name', 'paper_size', 'default_print_mode', 'margins',
        'layout', 'show_barcode', 'barcode_source', 'show_branch_footer', 'show_visiting_hours',
        'show_signature', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'margins' => 'array',
            'layout' => TemplateLayout::class,
            'default_print_mode' => PrintMode::class,
            'show_barcode' => 'boolean',
            'show_branch_footer' => 'boolean',
            'show_visiting_hours' => 'boolean',
            'show_signature' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** @return HasMany<TemplateSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(TemplateSection::class, 'template_id')->orderBy('sort_order');
    }
}
