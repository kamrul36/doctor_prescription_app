<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Document\ExportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Document\PrintPrescriptionRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CaseHistoryExportController extends Controller
{
    /**
     * Export a prescription for printing.
     *
     * `format=html` returns the A4 page (print it or save it as PDF in a browser). `pdf` is
     * not available yet (422). Finalized visits only; a draft exports as a "DRAFT" preview
     * for its own doctor. `lang`: `en`, `bn` or `bilingual` (default: the user's language).
     */
    public function __invoke(PrintPrescriptionRequest $request, CaseHistory $case, ExportService $export): Response
    {
        $format = $request->validate([
            /** Only `html` for now. */
            'format' => ['required', Rule::in(['html', 'pdf'])],
        ])['format'];

        Gate::authorize('print', $case);

        if ($format === 'pdf') {
            throw ValidationException::withMessages(['format' => __('PDF export is not available yet; use format=html and print or save it as PDF.')]);
        }

        return response($export->html($case, $request->options($export->defaultMode($case))))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
