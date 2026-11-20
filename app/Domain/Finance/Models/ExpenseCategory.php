<?php

namespace App\Domain\Finance\Models;

use App\Support\Concerns\HasBilingualFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An expense heading (rent, salary, ...). Kept in the database (rule A6),
 * managed at /expenses/categories; deactivated, never deleted.
 *
 * @property int $id
 * @property string $name_en
 * @property string|null $name_bn
 * @property bool $is_active
 */
class ExpenseCategory extends Model
{
    use HasBilingualFields;

    protected $fillable = ['name_en', 'name_bn', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }
}
