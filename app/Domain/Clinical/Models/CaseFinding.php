<?php

namespace App\Domain\Clinical\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An "on examination" finding: an optional label and the finding ("Anaemia: mild").
 *
 * @property string|null $label
 * @property string $value
 */
class CaseFinding extends Model
{
    protected $fillable = ['label', 'value', 'sort_order'];
}
