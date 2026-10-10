<?php

namespace App\Http\Controllers\Web;

use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Clinical\VisitStatus;
use App\Domain\Practice\Models\Doctor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Home: for a doctor, the prescription desk (new prescription, today's list); for others, quick links. */
class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $doctor = Doctor::query()->where('user_id', $request->user()?->id)->with('chambers')->first();

        $today = $doctor === null ? collect() : CaseHistory::query()
            ->with('patient')
            ->where('doctor_id', $doctor->id)
            ->whereDate('visit_date', today())
            ->where('status', '!=', VisitStatus::Cancelled)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [VisitStatus::Draft->value])
            ->orderByDesc('visit_no_today')
            ->limit(15)
            ->get();

        return view('home', ['doctor' => $doctor, 'today' => $today]);
    }
}
