<?php

namespace App\Http\Controllers\Api\V1\Practice;

use App\Domain\Practice\Actions\SaveTemplateAction;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\SaveTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** Templates are deactivated with `is_active: false`, never deleted. */
class TemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PrescriptionTemplate::class);

        return TemplateResource::collection(
            PrescriptionTemplate::query()->with('sections')->orderBy('name')->get(),
        );
    }

    public function store(SaveTemplateRequest $request, SaveTemplateAction $save): TemplateResource
    {
        return new TemplateResource($save->handle($this->actor($request), null, $request->validated()));
    }

    public function show(PrescriptionTemplate $template): TemplateResource
    {
        Gate::authorize('view', $template);

        return new TemplateResource($template->load('sections'));
    }

    public function update(SaveTemplateRequest $request, PrescriptionTemplate $template, SaveTemplateAction $save): TemplateResource
    {
        return new TemplateResource($save->handle($this->actor($request), $template, $request->validated()));
    }

    private function actor(SaveTemplateRequest $request): User
    {
        /** @var User */
        return $request->user();
    }
}
