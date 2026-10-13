<?php

namespace App\Http\Controllers\Web\Clinical;

use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Document\ExportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Document\PrintPrescriptionRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** The A4 print view with the English / বাংলা / bilingual switch (spec §6). */
class PrescriptionPrintController extends Controller
{
    public function __invoke(PrintPrescriptionRequest $request, CaseHistory $case, ExportService $export): Response
    {
        Gate::authorize('print', $case);

        return response($export->html($case, $request->options($export->defaultMode($case)), toolbar: true));
    }
}
