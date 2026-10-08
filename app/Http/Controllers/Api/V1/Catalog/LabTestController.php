<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Domain\Catalog\Actions\DeleteCatalogItemAction;
use App\Domain\Catalog\Actions\SaveCatalogItemAction;
use App\Domain\Catalog\CatalogSearch;
use App\Domain\Catalog\Models\LabTest;
use App\Http\Requests\Catalog\SaveLabTestRequest;
use App\Http\Resources\LabTestResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LabTestController extends CatalogApiController
{
    public function index(Request $request, CatalogSearch $search): mixed
    {
        return $this->list($request, $search, LabTest::class, LabTestResource::class);
    }

    public function store(SaveLabTestRequest $request, SaveCatalogItemAction $save): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return (new LabTestResource($save->handle($actor, LabTest::class, null, $request->validated())))
            ->response()->setStatusCode(201);
    }

    public function show(LabTest $labTest): LabTestResource
    {
        Gate::authorize('view', $labTest);

        return new LabTestResource($labTest);
    }

    public function update(SaveLabTestRequest $request, LabTest $labTest, SaveCatalogItemAction $save): LabTestResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new LabTestResource($save->handle($actor, LabTest::class, $labTest, $request->validated()));
    }

    public function destroy(LabTest $labTest, DeleteCatalogItemAction $delete): Response
    {
        Gate::authorize('delete', $labTest);

        $delete->handle($labTest);

        return response()->noContent();
    }
}
