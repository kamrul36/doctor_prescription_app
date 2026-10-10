<?php

namespace App\Http\Controllers\Web\Settings;

use App\Domain\Practice\Actions\SaveDoctorProfileAction;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\Models\Specialty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\SaveDoctorProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** "My profile": the doctor's header details, specialties, credentials, and hours/fees per assigned chamber. */
class DoctorProfileController extends Controller
{
    public function edit(Request $request): View
    {
        Gate::authorize('create', Doctor::class);

        $doctor = Doctor::query()
            ->where('user_id', $request->user()?->id)
            ->with(['credentials', 'chambers', 'fees', 'specialties'])
            ->first() ?? new Doctor(['reg_label' => 'BMDC', 'name_en' => $request->user()?->name]);

        return view('settings.doctor', [
            'doctor' => $doctor,
            // Only the chambers the admin assigned to this doctor.
            'chambers' => $doctor->exists ? $doctor->chambers->sortBy('name_en')->values() : collect(),
            'specialties' => Specialty::options(),
            'templates' => PrescriptionTemplate::query()
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('doctor_id')->when($doctor->exists, fn ($q) => $q->orWhere('doctor_id', $doctor->id)))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(SaveDoctorProfileRequest $request, SaveDoctorProfileAction $save): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $save->handle($user, $request->validated());

        return redirect()->route('settings.doctor.edit')->with('status', 'Doctor profile saved.');
    }
}
