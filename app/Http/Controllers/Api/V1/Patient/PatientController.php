<?php

namespace App\Http\Controllers\Api\V1\Patient;

use App\Domain\Audit\AuditLogger;
use App\Domain\Patient\Actions\DeletePatientAction;
use App\Domain\Patient\Actions\SavePatientAction;
use App\Domain\Patient\Models\Patient;
use App\Domain\Patient\PatientSearchQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\SavePatientRequest;
use App\Http\Resources\PatientResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PatientController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Newest first, or best match first with `q` (code, phone fragment or name; typos tolerated). */
    public function index(Request $request, PatientSearchQuery $search): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Patient::class);

        $term = $request->string('q')->trim()->value();
        $page = max(1, $request->integer('page', 1));
        $results = $search->paginate($term, $page, min(max($request->integer('per_page', 25), 1), 100));

        if ($term !== '') {
            // The term itself may be a patient's name or number, so only the fact is logged.
            $this->audit->log('patient.searched', null, ['results' => $results->total()]);
        }

        return PatientResource::collection($results);
    }

    public function store(SavePatientRequest $request, SavePatientAction $save): JsonResponse
    {
        return (new PatientResource($save->handle(null, $request->validated())))
            ->response()->setStatusCode(201);
    }

    public function show(Patient $patient): PatientResource
    {
        Gate::authorize('view', $patient);

        $this->audit->log('patient.viewed', $patient);

        return new PatientResource($patient);
    }

    /** The few fields shown at the top of a visit. */
    public function summary(Patient $patient): JsonResponse
    {
        Gate::authorize('view', $patient);

        $this->audit->log('patient.viewed', $patient);

        return response()->json(['data' => [
            'id' => $patient->id,
            'code' => $patient->code,
            'name' => $patient->name,
            'age_text' => $patient->age_text,
            'gender' => $patient->gender->value,
            'phone' => $patient->phone,
            'blood_group' => $patient->blood_group,
            'allergies' => $patient->allergies,
            'conditions' => $patient->conditions,
        ]]);
    }

    public function update(SavePatientRequest $request, Patient $patient, SavePatientAction $save): PatientResource
    {
        return new PatientResource($save->handle($patient, $request->validated()));
    }

    public function destroy(Patient $patient, DeletePatientAction $delete): Response
    {
        Gate::authorize('delete', $patient);

        $delete->handle($patient);

        return response()->noContent();
    }
}
