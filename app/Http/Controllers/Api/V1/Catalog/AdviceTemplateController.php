<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Domain\Catalog\Actions\DeleteCatalogItemAction;
use App\Domain\Catalog\Actions\SaveCatalogItemAction;
use App\Domain\Catalog\CatalogSearch;
use App\Domain\Catalog\Models\AdviceTemplate;
use App\Http\Requests\Catalog\SaveAdviceTemplateRequest;
use App\Http\Resources\AdviceTemplateResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AdviceTemplateController extends CatalogApiController
{
    public function index(Request $request, CatalogSearch $search): mixed
    {
        return $this->list($request, $search, AdviceTemplate::class, AdviceTemplateResource::class);
    }

    public function store(SaveAdviceTemplateRequest $request, SaveCatalogItemAction $save): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return (new AdviceTemplateResource($save->handle($actor, AdviceTemplate::class, null, $request->validated())))
            ->response()->setStatusCode(201);
    }

    public function show(AdviceTemplate $adviceTemplate): AdviceTemplateResource
    {
        Gate::authorize('view', $adviceTemplate);

        return new AdviceTemplateResource($adviceTemplate);
    }

    public function update(SaveAdviceTemplateRequest $request, AdviceTemplate $adviceTemplate, SaveCatalogItemAction $save): AdviceTemplateResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new AdviceTemplateResource($save->handle($actor, AdviceTemplate::class, $adviceTemplate, $request->validated()));
    }

    public function destroy(AdviceTemplate $adviceTemplate, DeleteCatalogItemAction $delete): Response
    {
        Gate::authorize('delete', $adviceTemplate);

        $delete->handle($adviceTemplate);

        return response()->noContent();
    }
}
