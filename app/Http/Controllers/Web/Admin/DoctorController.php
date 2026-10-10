<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Access\Role;
use App\Domain\Practice\Actions\AssignDoctorChambersAction;
use App\Domain\Practice\DoctorDirectory;
use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\AssignDoctorChambersRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin: which chambers each doctor works at (`practice.manage`). */
class DoctorController extends Controller
{
    public function index(DoctorDirectory $doctors): View
    {
        Gate::authorize('assignChambers', Doctor::class);

        return view('admin.doctors.index', ['users' => $doctors->all()]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('assignChambers', Doctor::class);
        abort_unless($user->hasRole(Role::DOCTOR), 404);

        $assigned = $user->doctor?->chambers()->pluck('chambers.id')->map(fn ($id) => (int) $id)->all() ?? [];

        return view('admin.doctors.edit', [
            'user' => $user,
            'assigned' => $assigned,
            // Active chambers, plus inactive ones this doctor still has.
            'chambers' => Chamber::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $assigned))
                ->orderBy('name_en')->get(),
        ]);
    }

    public function update(AssignDoctorChambersRequest $request, User $user, AssignDoctorChambersAction $assign): RedirectResponse
    {
        $user = $request->doctorUser();
        $assign->handle($user, $request->validated()['chamber_ids']);

        return redirect()->route('admin.doctors.index')->with('status', "Chambers saved for {$user->name}.");
    }
}
