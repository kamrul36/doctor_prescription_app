<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Finance\Reports\Report;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ReportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    /**
     * A finance report.
     *
     * `daily-collection` (`date`), `monthly-summary` (`month` = YYYY-MM), `dues` (`date` = as of),
     * `cash-book` (`from`, `to`). Money is a decimal string. `reports.read` sees all;
     * `reports.basic` only the daily collection and the dues.
     */
    public function show(ReportRequest $request, Report $report): JsonResponse
    {
        Gate::authorize('viewReport', $report);

        return response()->json(['data' => $request->run($report)]);
    }
}
