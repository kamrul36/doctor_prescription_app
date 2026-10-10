<?php

namespace App\Http\Controllers\Api\V1\Practice;

use App\Domain\Practice\Actions\SaveChamberAction;
use App\Domain\Practice\Models\Chamber;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\SaveChamberRequest;
use App\Http\Resources\ChamberResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Chambers with their branches. Staff read them (active ones; a manager may
 * add `include_inactive=1`); creating and editing needs `practice.manage`.
 * Deactivate with `is_active: false`; `branches`, when sent, replaces the list.
 */
class ChamberController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Chamber::class);

        $all = $request->boolean('include_inactive') && $request->user()?->can('create', Chamber::class);

        return ChamberResource::collection(
            Chamber::query()->with('branches')->withCount('doctors')
                ->when(! $all, fn ($q) => $q->where('is_active', true))
                ->orderBy('name_en')->get(),
        );
    }

    public function store(SaveChamberRequest $request, SaveChamberAction $save): ChamberResource
    {
        return new ChamberResource($save->handle(null, $request->validated()));
    }

    public function show(Chamber $chamber): ChamberResource
    {
        Gate::authorize('view', $chamber);

        return new ChamberResource($chamber->load('branches')->loadCount('doctors'));
    }

    public function update(SaveChamberRequest $request, Chamber $chamber, SaveChamberAction $save): ChamberResource
    {
        return new ChamberResource($save->handle($chamber, $request->validated()));
    }
}
