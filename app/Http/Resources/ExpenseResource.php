<?php

namespace App\Http\Resources;

use App\Domain\Finance\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An expense. `amount` is a decimal string.
 *
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category->localized('name')),
            'amount' => $this->amount->toDecimal(),
            'spent_on' => $this->spent_on->toDateString(),
            'method' => $this->method->value,
            'note' => $this->note,
            'recorded_by' => $this->recorded_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
