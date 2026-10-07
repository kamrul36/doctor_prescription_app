<?php

namespace App\Http\Controllers\Api\V1\Practice;

use App\Domain\Practice\Actions\SaveChamberAction;
use App\Domain\Practice\Models\Chamber;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\SaveChamberRequest;
use App\Http\Resources\ChamberResource;
use Illuminate\Support\Facades\Gate;

/** The chamber (one per installation) with its branches. */
class ChamberController extends Controller
{
    public function show(): ChamberResource
    {
        Gate::authorize('viewAny', Chamber::class);

        return new ChamberResource(Chamber::query()->with('branches')->firstOrFail());
    }

    /** Creates the chamber on first call; `branches`, when sent, replaces the whole list. */
    public function update(SaveChamberRequest $request, SaveChamberAction $save): ChamberResource
    {
        return new ChamberResource($save->handle(Chamber::query()->first(), $request->validated()));
    }
}
