<?php

namespace App\Domain\Finance;

use App\Domain\Finance\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The invoice list for the web page and the API, built in one place (rule A9).
 * Filters: issue date range, status, patient, unpaid only, and a search on the
 * invoice number or the patient's name, code or phone.
 */
final class InvoiceListQuery
{
    /**
     * @param  array{from?: string|null, to?: string|null, status?: string|null, patient_id?: int|string|null, unpaid?: mixed, q?: string|null}  $filters
     * @return Builder<Invoice>
     */
    public static function build(array $filters): Builder
    {
        $query = Invoice::query()->with(['patient', 'caseHistory:id,prescription_no,status,visit_type']);

        if (filled($filters['from'] ?? null)) {
            $query->where('issued_at', '>=', Carbon::parse((string) $filters['from'])->startOfDay());
        }
        if (filled($filters['to'] ?? null)) {
            $query->where('issued_at', '<=', Carbon::parse((string) $filters['to'])->endOfDay());
        }
        if (($status = InvoiceStatus::tryFrom((string) ($filters['status'] ?? ''))) !== null) {
            $query->where('status', $status->value);
        }
        if (filled($filters['patient_id'] ?? null)) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }
        if (filter_var($filters['unpaid'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])->where('due', '>', 0);
        }
        if (filled($term = trim((string) ($filters['q'] ?? '')))) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
            $query->where(fn (Builder $q) => $q
                ->whereRaw("invoice_no LIKE ? ESCAPE '!'", [$like])
                ->orWhereHas('patient', fn (Builder $p) => $p
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("code LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("phone LIKE ? ESCAPE '!'", [$like])));
        }

        return $query->orderByDesc('issued_at')->orderByDesc('id');
    }
}
