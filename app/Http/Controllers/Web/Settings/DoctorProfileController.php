<?php

namespace App\Http\Controllers\Web\Settings;

use App\Domain\Practice\Actions\SaveDoctorProfileAction;
use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\SaveDoctorProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DoctorProfileController extends Controller
{
    public function edit(Request $request): View
    {
        Gate::authorize('create', Doctor::class);

        $doctor = Doctor::query()
            ->where('user_id', $request->user()?->id)
            ->with(['credentials', 'chambers', 'fees'])
            ->first() ?? new Doctor(['reg_label' => 'BMDC', 'specialty_code' => 'general', 'name_en' => $request->user()?->name]);

        return view('settings.doctor', [
            'doctor' => $doctor,
            'chambers' => Chamber::query()->orderBy('name_en')->get(),
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
