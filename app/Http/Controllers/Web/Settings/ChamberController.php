<?php

namespace App\Http\Controllers\Web\Settings;

use App\Domain\Practice\Actions\SaveChamberAction;
use App\Domain\Practice\Models\Chamber;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\SaveChamberRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ChamberController extends Controller
{
    public function edit(): View
    {
        Gate::authorize('update', Chamber::class);

        $chamber = Chamber::query()->with('branches')->first() ?? new Chamber;

        return view('settings.chamber', compact('chamber'));
    }

    public function update(SaveChamberRequest $request, SaveChamberAction $save): RedirectResponse
    {
        $save->handle(Chamber::query()->first(), $request->validated());

        return redirect()->route('settings.chamber.edit')->with('status', 'Chamber saved.');
    }
}
