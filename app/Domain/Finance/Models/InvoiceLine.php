<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Casts\MoneyCast;
use App\Domain\Finance\InvoiceLineKind;
use App\Domain\Finance\Money;
use App\Support\Concerns\HasBilingualFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string|null $item_code
 * @property InvoiceLineKind $kind
 * @property string $description_en
 * @property string|null $description_bn
 * @property int $quantity
 * @property Money $unit_price
 * @property Money $line_total
 * @property int|null $procedure_id
 * @property int|null $treatment_plan_item_id
 * @property int $sort_order
 */
class InvoiceLine extends Model
{
    use HasBilingualFields;

    protected $fillable = [
        'item_code', 'kind', 'description_en', 'description_bn', 'quantity', 'unit_price', 'line_total',
        'procedure_id', 'treatment_plan_item_id', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'kind' => InvoiceLineKind::class,
            'unit_price' => MoneyCast::class,
            'line_total' => MoneyCast::class,
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
