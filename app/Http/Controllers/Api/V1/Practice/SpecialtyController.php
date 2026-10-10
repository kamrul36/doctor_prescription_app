<?php

namespace App\Http\Controllers\Api\V1\Practice;

use App\Domain\Practice\Actions\SaveSpecialtyAction;
use App\Domain\Practice\Models\Specialty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\SaveSpecialtyRequest;
use App\Http\Resources\SpecialtyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The admin-managed specialty list. Everyone who works with setup or
 * clinical data reads it (active entries; `include_inactive=1` for a manager);
 * writes need `practice.manage`. Deactivate with `is_active: false`.
 */
class SpecialtyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Specialty::class);

        $all = $request->boolean('include_inactive') && $request->user()?->can('create', Specialty::class);

        return SpecialtyResource::collection(
            Specialty::query()->withCount('doctors')->when(! $all, fn ($q) => $q->active())->orderBy('name')->get(),
        );
    }

    public function store(SaveSpecialtyRequest $request, SaveSpecialtyAction $save): SpecialtyResource
    {
        return new SpecialtyResource($save->handle(null, $request->validated()));
    }

    public function show(Specialty $specialty): SpecialtyResource
    {
        Gate::authorize('view', $specialty);

        return new SpecialtyResource($specialty->loadCount('doctors'));
    }

    public function update(SaveSpecialtyRequest $request, Specialty $specialty, SaveSpecialtyAction $save): SpecialtyResource
    {
        return new SpecialtyResource($save->handle($specialty, $request->validated()));
    }
}
