<?php

namespace App\Http\Controllers\Api\V1\Practice;

use App\Domain\Practice\Actions\AssignDoctorChambersAction;
use App\Domain\Practice\DoctorDirectory;
use App\Domain\Practice\Models\Doctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\AssignDoctorChambersRequest;
use App\Http\Resources\DoctorResource;
use App\Http\Resources\DoctorUserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** Admin (`practice.manage`): doctors and the chambers they work at. */
class DoctorController extends Controller
{
    public function index(DoctorDirectory $doctors): AnonymousResourceCollection
    {
        Gate::authorize('assignChambers', Doctor::class);

        return DoctorUserResource::collection($doctors->all());
    }

    /** Replaces the doctor's chambers with `chamber_ids` (unassigned ones lose their fees). */
    public function assignChambers(AssignDoctorChambersRequest $request, User $user, AssignDoctorChambersAction $assign): DoctorResource
    {
        $doctor = $assign->handle($request->doctorUser(), $request->validated()['chamber_ids']);

        return new DoctorResource($doctor->load(['credentials', 'specialties']));
    }
}
