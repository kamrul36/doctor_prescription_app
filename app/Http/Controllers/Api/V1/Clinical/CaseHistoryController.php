<?php

namespace App\Http\Controllers\Api\V1\Clinical;

use App\Domain\Clinical\Actions\CancelVisitAction;
use App\Domain\Clinical\Actions\FinalizeVisitAction;
use App\Domain\Clinical\Actions\SaveVisitAction;
use App\Domain\Clinical\Exceptions\PossibleDuplicatePatients;
use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Patient\Models\Patient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\CancelVisitRequest;
use App\Http\Requests\Clinical\SaveVisitRequest;
use App\Http\Resources\CaseHistoryResource;
use App\Http\Resources\PatientResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Visits / prescriptions (`case-histories`). Create and update save a draft;
 * `finalize` numbers it and makes it read-only. Without `patient_id` the
 * patient is registered from `patient`; if they may already exist the answer
 * is 409 with `candidates`, and the client repeats the call with
 * `patient_choice` (a candidate's id, or `new`).
 */
class CaseHistoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CaseHistory::class);

        $cases = CaseHistory::query()
            ->with('patient')
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('visit_date', $request->date('date')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when($request->boolean('mine'), fn ($q) => $q->whereHas('doctor', fn ($d) => $d->where('user_id', $request->user()?->id)))
            ->orderByDesc('visit_date')->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return CaseHistoryResource::collection($cases);
    }

    /** A patient's visits, newest first (the timeline). */
    public function forPatient(Patient $patient): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CaseHistory::class);

        return CaseHistoryResource::collection(
            $patient->visits()->withBlocks()->get(),
        );
    }

    public function store(SaveVisitRequest $request, SaveVisitAction $save): JsonResponse
    {
        try {
            $case = $save->handle($this->actor($request), null, $request->validated());
        } catch (PossibleDuplicatePatients $e) {
            return $this->duplicates($request, $e);
        }

        return (new CaseHistoryResource($case->loadMissing('patient')))->response()->setStatusCode(201);
    }

    public function show(CaseHistory $case): CaseHistoryResource
    {
        Gate::authorize('view', $case);

        return new CaseHistoryResource($case->load('patient')->loadMissing(['complaints', 'findings', 'planItems', 'tests', 'medicines', 'advice']));
    }

    public function update(SaveVisitRequest $request, CaseHistory $case, SaveVisitAction $save): CaseHistoryResource
    {
        return new CaseHistoryResource($save->handle($this->actor($request), $case, $request->validated()));
    }

    public function finalize(Request $request, CaseHistory $case, FinalizeVisitAction $finalize): CaseHistoryResource
    {
        // Finalizing again is harmless (idempotent), so a finalized visit only needs read access.
        Gate::authorize($case->isFinalized() ? 'view' : 'finalize', $case);

        $case = $finalize->handle($this->actor($request), $case);

        return new CaseHistoryResource($case->load('patient')->loadMissing(['complaints', 'findings', 'planItems', 'tests', 'medicines', 'advice']));
    }

    public function cancel(CancelVisitRequest $request, CaseHistory $case, CancelVisitAction $cancel): CaseHistoryResource
    {
        return new CaseHistoryResource($cancel->handle($this->actor($request), $case, $request->validated()['reason']));
    }

    private function duplicates(Request $request, PossibleDuplicatePatients $e): JsonResponse
    {
        return new JsonResponse([
            'type' => 'about:blank',
            'title' => 'Possible duplicate patient',
            'status' => 409,
            'detail' => 'This patient may already be registered. Send patient_choice with one of the candidate ids, or "new".',
            'instance' => '/'.ltrim($request->path(), '/'),
            'candidates' => PatientResource::collection($e->candidates)->resolve($request),
        ], 409, ['Content-Type' => 'application/problem+json']);
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
