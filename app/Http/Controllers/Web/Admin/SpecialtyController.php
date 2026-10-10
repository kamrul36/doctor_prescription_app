<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Practice\Actions\SaveSpecialtyAction;
use App\Domain\Practice\Models\Specialty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\SaveSpecialtyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin screens for the specialty list (`practice.manage`). */
class SpecialtyController extends Controller
{
    public function index(): View
    {
        Gate::authorize('create', Specialty::class);

        return view('admin.specialties.index', [
            'specialties' => Specialty::query()->withCount('doctors')->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Specialty::class);

        return view('admin.specialties.form', ['specialty' => new Specialty]);
    }

    public function store(SaveSpecialtyRequest $request, SaveSpecialtyAction $save): RedirectResponse
    {
        $specialty = $save->handle(null, $request->validated());

        return redirect()->route('admin.specialties.index')->with('status', "Specialty {$specialty->name} added.");
    }

    public function edit(Specialty $specialty): View
    {
        Gate::authorize('update', $specialty);

        return view('admin.specialties.form', ['specialty' => $specialty]);
    }

    public function update(SaveSpecialtyRequest $request, Specialty $specialty, SaveSpecialtyAction $save): RedirectResponse
    {
        $save->handle($specialty, $request->validated());

        return redirect()->route('admin.specialties.index')->with('status', "Specialty {$specialty->name} saved.");
    }
}
