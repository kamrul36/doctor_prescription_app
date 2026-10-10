<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Practice\Actions\SaveChamberAction;
use App\Domain\Practice\Models\Chamber;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\SaveChamberRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin screens for chambers (`practice.manage`); doctors are assigned under Admin → Doctors. */
class ChamberController extends Controller
{
    public function index(): View
    {
        Gate::authorize('create', Chamber::class);

        return view('admin.chambers.index', [
            'chambers' => Chamber::query()->withCount('doctors')->with('branches')->orderByDesc('is_active')->orderBy('name_en')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Chamber::class);

        return view('admin.chambers.form', ['chamber' => new Chamber]);
    }

    public function store(SaveChamberRequest $request, SaveChamberAction $save): RedirectResponse
    {
        $chamber = $save->handle(null, $request->validated());

        return redirect()->route('admin.chambers.index')->with('status', "Chamber {$chamber->name_en} created.");
    }

    public function edit(Chamber $chamber): View
    {
        Gate::authorize('update', $chamber);

        return view('admin.chambers.form', ['chamber' => $chamber->load('branches')]);
    }

    public function update(SaveChamberRequest $request, Chamber $chamber, SaveChamberAction $save): RedirectResponse
    {
        $save->handle($chamber, $request->validated());

        return redirect()->route('admin.chambers.index')->with('status', "Chamber {$chamber->name_en} saved.");
    }
}
