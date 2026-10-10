<?php

namespace App\Http\Controllers\Web\Clinical;

use App\Domain\Clinical\Actions\CancelVisitAction;
use App\Domain\Clinical\Actions\FinalizeVisitAction;
use App\Domain\Clinical\Actions\SaveVisitAction;
use App\Domain\Clinical\Exceptions\PossibleDuplicatePatients;
use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Clinical\Specialty\SpecialtySchemas;
use App\Domain\Clinical\VisitStatus;
use App\Domain\Patient\Models\Patient;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\SpecialtyBlock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\CancelVisitRequest;
use App\Http\Requests\Clinical\SaveVisitRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The prescription pad and the day's prescriptions (web, session auth).
 * Same FormRequest and Actions as the `case-histories` API.
 */
class PrescriptionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CaseHistory::class);

        $date = $request->filled('date') ? Carbon::parse($request->string('date')->value())->startOfDay() : now()->startOfDay();
        $doctor = Doctor::query()->where('user_id', $request->user()?->id)->first();
        $mine = $doctor !== null && ! $request->boolean('all');

        $cases = CaseHistory::query()
            ->with(['patient', 'chamber', 'doctor'])
            ->whereDate('visit_date', $date)
            ->when($mine, fn ($q) => $q->where('doctor_id', $doctor->id))
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [VisitStatus::Draft->value, VisitStatus::Finalized->value])
            ->orderBy('visit_no_today')
            ->get();

        return view('prescriptions.index', ['cases' => $cases, 'date' => $date, 'mine' => $mine, 'isDoctor' => $doctor !== null]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', CaseHistory::class);

        $doctor = $this->doctorReadyToPrescribe($request);
        if ($doctor instanceof RedirectResponse) {
            return $doctor;
        }

        $patient = $request->filled('patient') ? Patient::query()->find($request->integer('patient')) : null;

        return view('prescriptions.pad', $this->padData($doctor, null, $patient));
    }

    public function store(SaveVisitRequest $request, SaveVisitAction $save, FinalizeVisitAction $finalize): RedirectResponse
    {
        try {
            $case = $save->handle($this->actor($request), null, $request->validated());
        } catch (PossibleDuplicatePatients $e) {
            return back()->withInput()->with('patient_candidates', $e->candidates->pluck('id')->all());
        }

        return $this->afterSave($request, $case, $finalize, created: true);
    }

    public function show(Request $request, CaseHistory $case): View
    {
        Gate::authorize('view', $case);

        $case->load(['patient', 'chamber', 'doctor.specialties'])->loadMissing(['complaints', 'findings', 'planItems', 'tests', 'medicines', 'advice']);

        return view('prescriptions.show', ['case' => $case]);
    }

    public function edit(Request $request, CaseHistory $case): View|RedirectResponse
    {
        if (! $case->isDraft()) {
            return redirect()->route('prescriptions.show', $case);
        }

        Gate::authorize('update', $case);

        $doctor = SaveVisitAction::doctorFor($this->actor($request));
        $case->load(['patient'])->loadMissing(['complaints', 'findings', 'planItems', 'tests', 'medicines', 'advice']);

        return view('prescriptions.pad', $this->padData($doctor, $case, $case->patient));
    }

    public function update(SaveVisitRequest $request, CaseHistory $case, SaveVisitAction $save, FinalizeVisitAction $finalize): RedirectResponse
    {
        $case = $save->handle($this->actor($request), $case, $request->validated());

        return $this->afterSave($request, $case, $finalize, created: false);
    }

    public function finalize(Request $request, CaseHistory $case, FinalizeVisitAction $finalize): RedirectResponse
    {
        Gate::authorize($case->isFinalized() ? 'view' : 'finalize', $case);

        try {
            $case = $finalize->handle($this->actor($request), $case);
        } catch (ValidationException $e) {
            return redirect()->route('prescriptions.edit', $case)->withErrors($e->errors());
        }

        return redirect()->route('prescriptions.show', $case)->with('status', "Prescription {$case->prescription_no} finalized.");
    }

    public function cancel(CancelVisitRequest $request, CaseHistory $case, CancelVisitAction $cancel): RedirectResponse
    {
        $cancel->handle($this->actor($request), $case, $request->validated()['reason']);

        return redirect()->route('prescriptions.index')->with('status', 'Draft cancelled.');
    }

    /** "Save & finalize" saves first, so a finalize problem never loses the doctor's work. */
    private function afterSave(SaveVisitRequest $request, CaseHistory $case, FinalizeVisitAction $finalize, bool $created): RedirectResponse
    {
        $registered = $created && $case->patient->wasRecentlyCreated ? " Patient registered as {$case->patient->code}." : '';

        if ($request->input('action') !== 'finalize') {
            return redirect()->route('prescriptions.edit', $case)->with('status', 'Draft saved.'.$registered);
        }

        if ($this->actor($request)->cannot('finalize', $case)) {
            return redirect()->route('prescriptions.edit', $case)->with('status', 'Draft saved. You may not finalize prescriptions.'.$registered);
        }

        try {
            $case = $finalize->handle($this->actor($request), $case);
        } catch (ValidationException $e) {
            return redirect()->route('prescriptions.edit', $case)
                ->withErrors($e->errors())
                ->with('status', 'Draft saved, but it cannot be finalized yet.'.$registered);
        }

        return redirect()->route('prescriptions.show', $case)->with('status', "Prescription {$case->prescription_no} finalized.".$registered);
    }

    /** The pad needs a doctor profile with at least one open chamber. */
    private function doctorReadyToPrescribe(Request $request): Doctor|RedirectResponse
    {
        $doctor = Doctor::query()->where('user_id', $request->user()?->id)->with(['chambers', 'specialties'])->first();

        if ($doctor === null) {
            return redirect()->route('settings.doctor.edit')->with('status', 'Set up your doctor profile before writing prescriptions.');
        }

        if ($doctor->activeChambers()->isEmpty()) {
            return redirect()->route('settings.doctor.edit')->with('status', 'No chamber is assigned to you yet. Ask the admin to assign one before writing prescriptions.');
        }

        return $doctor;
    }

    /** @return array<string, mixed> */
    private function padData(Doctor $doctor, ?CaseHistory $case, ?Patient $patient): array
    {
        $doctor->loadMissing(['chambers', 'specialties']);
        $suggested = $patient !== null && $case === null ? SaveVisitAction::suggestVisitType($patient, $doctor) : null;

        return [
            'case' => $case,
            'doctor' => $doctor,
            'patient' => $patient,
            'chambers' => $doctor->activeChambers(),
            'schemas' => array_map(fn ($block) => SpecialtySchemas::for($block), $doctor->blocks()),
            'hasDental' => in_array(SpecialtyBlock::Dental, $doctor->blocks(), true),
            'suggestedVisitType' => $suggested,
            'initial' => $this->initialRows($case),
            'candidates' => Patient::query()->whereIn('id', (array) session('patient_candidates', []))->get(),
        ];
    }

    /**
     * The saved rows of each block, shaped like the form fields.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function initialRows(?CaseHistory $case): array
    {
        if ($case === null) {
            return ['complaints' => [], 'findings' => [], 'plan_items' => [], 'tests' => [], 'medicines' => [], 'advice' => []];
        }

        return [
            'complaints' => $case->complaints->map(fn ($c) => ['text' => $c->text, 'duration_value' => $c->duration_value, 'duration_unit' => $c->duration_unit?->value])->all(),
            'findings' => $case->findings->map(fn ($f) => ['label' => $f->label, 'value' => $f->value])->all(),
            'plan_items' => $case->planItems->map(fn ($p) => [
                'procedure_id' => $p->procedure_id, 'description' => $p->description, 'tooth_no' => $p->tooth_no,
                'status' => $p->status, 'fee' => $p->fee?->toDecimal(),
            ])->all(),
            'tests' => $case->tests->map(fn ($t) => [
                'lab_test_id' => $t->lab_test_id, 'name' => $t->name_snapshot, 'timing_note' => $t->timing_note,
                'kind' => $t->kind->value, 'result' => $t->result,
            ])->all(),
            'medicines' => $case->medicines->map(fn ($m) => [
                'name' => $m->display_name, 'dose_morning' => $m->dose_morning, 'dose_noon' => $m->dose_noon, 'dose_night' => $m->dose_night,
                'dose_unit' => $m->dose_unit?->value, 'timing' => $m->timing?->value,
                'duration_value' => $m->duration_value, 'duration_unit' => $m->duration_unit?->value, 'instruction' => $m->instruction_en,
            ])->all(),
            'advice' => $case->advice->map(fn ($a) => ['text' => $a->text_en, 'text_bn' => $a->text_bn, 'source_template_id' => $a->source_template_id])->all(),
        ];
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
