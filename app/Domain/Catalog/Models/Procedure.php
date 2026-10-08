<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\CatalogEntry;
use App\Domain\Finance\Casts\MoneyCast;
use App\Domain\Finance\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string $name_en
 * @property string|null $name_bn
 * @property Money|null $default_fee
 * @property bool $is_billable
 * @property bool $is_active
 */
class Procedure extends Model implements CatalogEntry
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['code', 'name_en', 'name_bn', 'default_fee', 'is_billable', 'is_active'];

    /** @var array<string, mixed> */
    protected $attributes = ['is_billable' => true, 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['default_fee' => MoneyCast::class, 'is_billable' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @return list<string> */
    public static function searchColumns(): array
    {
        return ['code', 'name_en', 'name_bn'];
    }

    public static function sortColumn(): string
    {
        return 'name_en';
    }
}
