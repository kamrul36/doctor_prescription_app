<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\Models\Procedure;
use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Clinical\Models\TreatmentPlanItem;
use App\Domain\Finance\InvoiceLineKind;
use App\Domain\Finance\InvoiceTotals;
use App\Domain\Finance\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * On finalize (spec §8.3): every billable treatment-plan item with a fee
 * becomes a `procedure` line, keyed by the plan item, so running it again
 * adds nothing. Totals and status are recomputed, so a bill that was already
 * paid becomes partially paid when procedures are added. A voided invoice
 * gets no lines.
 *
 * Called inside FinalizeVisitAction's transaction (visit locked first).
 */
class SyncProcedureLinesAction
{
    public function __construct(
        private readonly SyncVisitInvoiceAction $invoices,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(CaseHistory $case): Invoice
    {
        return DB::transaction(function () use ($case) {
            $invoice = $this->invoices->handle($case);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->isVoided()) {
                return $invoice;
            }

            $billed = $invoice->lines()->whereNotNull('treatment_plan_item_id')->pluck('treatment_plan_item_id')->map(fn ($id) => (int) $id)->all();
            $order = (int) $invoice->lines()->max('sort_order');
            $added = 0;

            $items = $case->planItems()->get()->filter(fn (TreatmentPlanItem $item) => $item->is_billable && $item->fee !== null && ! $item->fee->isZero());
            $procedures = Procedure::withTrashed()->whereIn('id', $items->pluck('procedure_id')->filter())->get()->keyBy('id');

            foreach ($items as $item) {
                if (in_array($item->id, $billed, true)) {
                    continue;
                }

                /** @var Procedure|null $procedure */
                $procedure = $item->procedure_id !== null ? $procedures->get($item->procedure_id) : null;
                $invoice->lines()->create([
                    'kind' => InvoiceLineKind::Procedure,
                    'item_code' => $procedure?->code,
                    'description_en' => $item->description,
                    'description_bn' => $procedure?->name_bn,
                    'quantity' => 1,
                    'unit_price' => $item->fee,
                    'line_total' => $item->fee,
                    'procedure_id' => $item->procedure_id,
                    'treatment_plan_item_id' => $item->id,
                    'sort_order' => ++$order,
                ]);
                $added++;
            }

            if ($added > 0) {
                InvoiceTotals::apply($invoice);
                $this->audit->log('invoice.procedures_synced', $invoice, ['invoice_no' => $invoice->invoice_no, 'lines_added' => $added]);
            }

            return $invoice;
        });
    }
}
