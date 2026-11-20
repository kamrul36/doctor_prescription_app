<?php

namespace App\Http\Resources;

use App\Domain\Finance\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment. `voided_at` set = it no longer counts.
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_no' => $this->receipt_no,
            'invoice_id' => $this->invoice_id,
            'amount' => $this->amount->toDecimal(),
            'method' => $this->method->value,
            'reference' => $this->reference,
            'received_by' => $this->received_by,
            'received_at' => $this->received_at,
            'voided_at' => $this->voided_at,
            'void_reason' => $this->void_reason,
            'created_at' => $this->created_at,
        ];
    }
}
