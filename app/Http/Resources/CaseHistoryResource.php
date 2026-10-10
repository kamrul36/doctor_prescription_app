<?php

namespace App\Http\Resources;

use App\Domain\Clinical\Models\CaseHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A visit / prescription with its blocks. `notes_private` only for the own
 * doctor or `cases.read_private`.
 *
 * @mixin CaseHistory
 */
class CaseHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'prescription_no' => $this->prescription_no,
            'visit_date' => $this->visit_date->toDateString(),
            'visit_no_today' => $this->visit_no_today,
            'visit_type' => $this->visit_type->value,
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id, 'code' => $this->patient->code, 'name' => $this->patient->name,
                'age_text' => $this->patient->age_text, 'gender' => $this->patient->gender->value, 'phone' => $this->patient->phone,
            ]),
            'doctor_id' => $this->doctor_id,
            'chamber_id' => $this->chamber_id,
            'template_id' => $this->template_id,
            'vitals' => $this->vitals,
            'diagnosis' => $this->diagnosis,
            'specialty_data' => $this->specialty_data,
            'complaints' => $this->whenLoaded('complaints', fn () => $this->complaints->map(fn ($c) => [
                'text' => $c->text, 'duration_value' => $c->duration_value, 'duration_unit' => $c->duration_unit?->value,
            ])->all()),
            'findings' => $this->whenLoaded('findings', fn () => $this->findings->map(fn ($f) => ['label' => $f->label, 'value' => $f->value])->all()),
            'plan_items' => $this->whenLoaded('planItems', fn () => $this->planItems->map(fn ($p) => [
                'procedure_id' => $p->procedure_id, 'description' => $p->description, 'tooth_no' => $p->tooth_no,
                'status' => $p->status, 'fee' => $p->fee?->toDecimal(), 'is_billable' => $p->is_billable,
            ])->all()),
            'tests' => $this->whenLoaded('tests', fn () => $this->tests->map(fn ($t) => [
                'lab_test_id' => $t->lab_test_id, 'name' => $t->name_snapshot, 'timing_note' => $t->timing_note,
                'kind' => $t->kind->value, 'result' => $t->result,
            ])->all()),
            'medicines' => $this->whenLoaded('medicines', fn () => $this->medicines->map(fn ($m) => [
                'name' => $m->display_name,
                'dose_morning' => $m->dose_morning, 'dose_noon' => $m->dose_noon, 'dose_night' => $m->dose_night,
                'dose' => $m->dose()?->format(),
                'dose_unit' => $m->dose_unit?->value, 'timing' => $m->timing?->value,
                'duration_value' => $m->duration_value, 'duration_unit' => $m->duration_unit?->value,
                'instruction' => $m->instruction_en,
                'summary' => $m->summary(),
            ])->all()),
            'advice' => $this->whenLoaded('advice', fn () => $this->advice->map(fn ($a) => [
                'text' => $a->text_en, 'text_bn' => $a->text_bn, 'source_template_id' => $a->source_template_id,
            ])->all()),
            'follow_up_value' => $this->follow_up_value,
            'follow_up_unit' => $this->follow_up_unit?->value,
            'follow_up_date' => $this->follow_up_date?->toDateString(),
            'notes_private' => $this->when($request->user()?->can('viewPrivateNotes', $this->resource) ?? false, $this->notes_private),
            'patient_snapshot' => $this->patient_snapshot,
            'doctor_snapshot' => $this->doctor_snapshot,
            'finalized_at' => $this->finalized_at,
            'cancelled_at' => $this->cancelled_at,
            'cancel_reason' => $this->cancel_reason,
            'row_version' => $this->row_version,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
