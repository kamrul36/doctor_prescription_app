<?php

namespace App\Http\Resources;

use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\InvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invoice. Money is a decimal string (`"500.00"`); `status` is derived
 * (`issued` = nothing paid yet, `partially_paid`, `paid`, `voided`).
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'status' => $this->status->value,
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id, 'code' => $this->patient->code, 'name' => $this->patient->name, 'phone' => $this->patient->phone,
            ]),
            'case_history_id' => $this->case_history_id,
            'prescription_no' => $this->whenLoaded('caseHistory', fn () => $this->caseHistory?->prescription_no),
            'subtotal' => $this->subtotal->toDecimal(),
            'discount' => $this->discount->toDecimal(),
            'discount_reason' => $this->discount_reason,
            'total' => $this->total->toDecimal(),
            'paid' => $this->paid->toDecimal(),
            'due' => $this->due->toDecimal(),
            'referred_by' => $this->referred_by,
            'under_doctor_id' => $this->under_doctor_id,
            'note' => $this->note,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (InvoiceLine $line) => [
                'id' => $line->id,
                'kind' => $line->kind->value,
                'item_code' => $line->item_code,
                'description_en' => $line->description_en,
                'description_bn' => $line->description_bn,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price->toDecimal(),
                'line_total' => $line->line_total->toDecimal(),
                'procedure_id' => $line->procedure_id,
                'treatment_plan_item_id' => $line->treatment_plan_item_id,
            ])->all()),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'issued_at' => $this->issued_at,
            'voided_at' => $this->voided_at,
            'void_reason' => $this->void_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
