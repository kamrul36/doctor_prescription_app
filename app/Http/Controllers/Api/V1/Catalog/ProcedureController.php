<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Domain\Catalog\Actions\DeleteCatalogItemAction;
use App\Domain\Catalog\Actions\SaveCatalogItemAction;
use App\Domain\Catalog\CatalogSearch;
use App\Domain\Catalog\Models\Procedure;
use App\Http\Requests\Catalog\SaveProcedureRequest;
use App\Http\Resources\ProcedureResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProcedureController extends CatalogApiController
{
    public function index(Request $request, CatalogSearch $search): mixed
    {
        return $this->list($request, $search, Procedure::class, ProcedureResource::class);
    }

    public function store(SaveProcedureRequest $request, SaveCatalogItemAction $save): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return (new ProcedureResource($save->handle($actor, Procedure::class, null, $request->validated())))
            ->response()->setStatusCode(201);
    }

    public function show(Procedure $procedure): ProcedureResource
    {
        Gate::authorize('view', $procedure);

        return new ProcedureResource($procedure);
    }

    public function update(SaveProcedureRequest $request, Procedure $procedure, SaveCatalogItemAction $save): ProcedureResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new ProcedureResource($save->handle($actor, Procedure::class, $procedure, $request->validated()));
    }

    public function destroy(Procedure $procedure, DeleteCatalogItemAction $delete): Response
    {
        Gate::authorize('delete', $procedure);

        $delete->handle($procedure);

        return response()->noContent();
    }
}
