<?php

namespace App\Domain\Clinical\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Advice printed on the prescription; copied from an advice template or typed.
 *
 * @property string $text_en
 * @property string|null $text_bn
 * @property int|null $source_template_id
 */
class CaseAdvice extends Model
{
    protected $table = 'case_advice';

    protected $fillable = ['text_en', 'text_bn', 'source_template_id', 'sort_order'];
}
