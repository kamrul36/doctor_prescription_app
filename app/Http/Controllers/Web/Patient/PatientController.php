<?php

namespace App\Http\Controllers\Web\Patient;

use App\Domain\Audit\AuditLogger;
use App\Domain\Patient\Actions\DeletePatientAction;
use App\Domain\Patient\Actions\SavePatientAction;
use App\Domain\Patient\Models\Patient;
use App\Domain\Patient\PatientSearchQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\SavePatientRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, PatientSearchQuery $search): View
    {
        Gate::authorize('viewAny', Patient::class);

        $q = $request->string('q')->trim()->value();
        $patients = $search->paginate($q, max(1, $request->integer('page', 1)))->withQueryString();

        if ($q !== '') {
            $this->audit->log('patient.searched', null, ['results' => $patients->total()]);
        }

        return view('patients.index', compact('patients', 'q'));
    }

    public function create(): View
    {
        Gate::authorize('create', Patient::class);

        return view('patients.create', ['patient' => new Patient(['patient_type' => 'general'])]);
    }

    public function store(SavePatientRequest $request, SavePatientAction $save): RedirectResponse
    {
        $patient = $save->handle(null, $request->validated());

        return redirect()->route('patients.show', $patient)
            ->with('status', "Patient registered with code {$patient->code}.");
    }

    public function show(Patient $patient): View
    {
        Gate::authorize('view', $patient);

        $this->audit->log('patient.viewed', $patient);

        return view('patients.show', compact('patient'));
    }

    public function edit(Patient $patient): View
    {
        Gate::authorize('update', $patient);

        return view('patients.edit', compact('patient'));
    }

    public function update(SavePatientRequest $request, Patient $patient, SavePatientAction $save): RedirectResponse
    {
        $save->handle($patient, $request->validated());

        return redirect()->route('patients.show', $patient)->with('status', 'Patient saved.');
    }

    public function destroy(Patient $patient, DeletePatientAction $delete): RedirectResponse
    {
        Gate::authorize('delete', $patient);

        $delete->handle($patient);

        return redirect()->route('patients.index')->with('status', 'Patient deleted.');
    }
}
