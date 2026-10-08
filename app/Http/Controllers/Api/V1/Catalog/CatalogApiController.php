<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Domain\Catalog\CatalogEntry;
use App\Domain\Catalog\CatalogSearch;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * Shared listing for the catalog endpoints. Without `page` the index is a
 * cached typeahead (`search`, `take`) returning `{data: [...]}`; with `page`
 * it is a paginated list for management screens.
 */
abstract class CatalogApiController extends Controller
{
    /**
     * @param  class-string<Model&CatalogEntry>  $model
     * @param  class-string<JsonResource>  $resource
     */
    protected function list(Request $request, CatalogSearch $search, string $model, string $resource): mixed
    {
        Gate::authorize('viewAny', $model);

        /** @var User $actor */
        $actor = $request->user();
        $term = $request->string('search')->value();
        $includeInactive = $request->boolean('include_inactive');
        $specialty = $request->filled('specialty') ? $request->string('specialty')->value() : null;

        if ($request->has('page')) {
            return $resource::collection($search->paginate(
                $actor, $model, $term, $request->integer('page', 1), $request->integer('per_page', 25),
                $includeInactive, $specialty,
            ));
        }

        return new JsonResponse(['data' => $search->typeahead(
            $actor, $model, $resource, $term, $request->integer('take', CatalogSearch::DEFAULT_TAKE),
            $includeInactive, $specialty,
        )]);
    }
}
