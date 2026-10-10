<?php

namespace App\Http\Controllers\Api\V1\Practice;

use App\Domain\Practice\Actions\SaveDoctorProfileAction;
use App\Domain\Practice\Models\Doctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\SaveDoctorProfileRequest;
use App\Http\Resources\DoctorResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The signed-in user's own doctor profile (`doctors/me`). */
class DoctorProfileController extends Controller
{
    public function show(Request $request): DoctorResource
    {
        Gate::authorize('viewAny', Doctor::class);

        $doctor = Doctor::query()
            ->where('user_id', $request->user()?->id)
            ->with(['credentials', 'chambers', 'fees', 'specialties'])
            ->firstOrFail();

        return new DoctorResource($doctor);
    }

    /** Full replace; creates the profile on first call. */
    public function update(SaveDoctorProfileRequest $request, SaveDoctorProfileAction $save): DoctorResource
    {
        /** @var User $user */
        $user = $request->user();

        return new DoctorResource($save->handle($user, $request->validated()));
    }
}
