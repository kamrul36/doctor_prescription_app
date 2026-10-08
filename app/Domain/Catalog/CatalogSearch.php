<?php

namespace App\Domain\Catalog;

use App\Domain\Access\Permission;
use App\Domain\Catalog\Models\AdviceTemplate;
use App\Domain\Practice\Models\Doctor;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * Typeahead and listing for the three catalogs.
 *
 * Typeahead returns prefix matches first (index friendly), and contains
 * matches only when the prefix matches do not fill `take`. User input is
 * escaped so `%` and `_` match themselves. Inactive and deleted rows are
 * hidden unless a catalog manager asks for them. Advice templates show only
 * shared ones and the actor's own.
 */
class CatalogSearch
{
    public const DEFAULT_TAKE = 10;

    public const MAX_TAKE = 50;

    private const MAX_TERM_LENGTH = 100;

    private const ESCAPE = '!';

    public function __construct(private readonly CatalogCache $cache) {}

    /**
     * @param  class-string<Model&CatalogEntry>  $model
     * @param  class-string<JsonResource>  $resource
     * @return list<array<string, mixed>>
     */
    public function typeahead(
        User $actor,
        string $model,
        string $resource,
        string $term,
        int $take = self::DEFAULT_TAKE,
        bool $includeInactive = false,
        ?string $specialty = null,
    ): array {
        $term = $this->cleanTerm($term);
        $take = min(max($take, 1), self::MAX_TAKE);
        $includeInactive = $this->mayIncludeInactive($actor, $includeInactive);
        $doctorId = $this->doctorId($actor, $model);

        return $this->cache->remember($model, [
            'term' => $term, 'take' => $take, 'inactive' => $includeInactive,
            'specialty' => $specialty, 'doctor' => $doctorId,
        ], function () use ($model, $resource, $doctorId, $term, $take, $includeInactive, $specialty) {
            $models = $this->match($model, $doctorId, $term, $take, $includeInactive, $specialty);

            /** @var list<array<string, mixed>> */
            return $resource::collection($models)->resolve(request());
        });
    }

    /** @param  class-string<Model&CatalogEntry>  $model */
    public function paginate(
        User $actor,
        string $model,
        string $term,
        int $page = 1,
        int $perPage = 25,
        bool $includeInactive = false,
        ?string $specialty = null,
    ): LengthAwarePaginator {
        $query = $this->visible($model, $this->doctorId($actor, $model), $this->mayIncludeInactive($actor, $includeInactive), $specialty);
        $term = $this->cleanTerm($term);

        if ($term !== '') {
            $this->whereAnyColumn($query, $model, '%'.$this->escape($term).'%');
        }

        return $query->orderBy($model::sortColumn())->orderBy('id')
            ->paginate(min(max($perPage, 1), 100), ['*'], 'page', max($page, 1));
    }

    /**
     * @template TModel of Model&CatalogEntry
     *
     * @param  class-string<TModel>  $model
     * @return Collection<int, TModel>
     */
    private function match(string $model, ?int $doctorId, string $term, int $take, bool $includeInactive, ?string $specialty)
    {
        $base = fn () => $this->visible($model, $doctorId, $includeInactive, $specialty)
            ->orderBy($model::sortColumn())->orderBy('id');

        if ($term === '') {
            return $base()->limit($take)->get();
        }

        $escaped = $this->escape($term);
        $prefix = $this->whereAnyColumn($base(), $model, $escaped.'%')->limit($take)->get();

        if ($prefix->count() >= $take) {
            return $prefix;
        }

        $contains = $this->whereAnyColumn($base(), $model, '%'.$escaped.'%')
            ->whereNotIn('id', $prefix->modelKeys())
            ->limit($take - $prefix->count())
            ->get();

        return $prefix->concat($contains);
    }

    /**
     * @template TModel of Model&CatalogEntry
     *
     * @param  class-string<TModel>  $model
     * @return Builder<TModel>
     */
    private function visible(string $model, ?int $doctorId, bool $includeInactive, ?string $specialty): Builder
    {
        $query = $model::query();

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        if ($model === AdviceTemplate::class) {
            $query->where(fn (Builder $q) => $q->whereNull('doctor_id')
                ->when($doctorId !== null, fn (Builder $q) => $q->orWhere('doctor_id', $doctorId)));

            if ($specialty !== null && $specialty !== '') {
                $query->where('specialty_code', $specialty);
            }
        }

        return $query;
    }

    /**
     * @template TModel of Model&CatalogEntry
     *
     * @param  Builder<TModel>  $query
     * @param  class-string<TModel>  $model
     * @return Builder<TModel>
     */
    private function whereAnyColumn(Builder $query, string $model, string $pattern): Builder
    {
        $grammar = DB::getQueryGrammar();
        $escape = self::ESCAPE;

        return $query->where(function (Builder $q) use ($model, $pattern, $grammar, $escape) {
            foreach ($model::searchColumns() as $column) {
                $q->orWhereRaw($grammar->wrap($column)." LIKE ? ESCAPE '{$escape}'", [$pattern]);
            }
        });
    }

    private function escape(string $term): string
    {
        return (string) preg_replace('/[%_'.preg_quote(self::ESCAPE, '/').']/', self::ESCAPE.'$0', $term);
    }

    private function cleanTerm(string $term): string
    {
        return mb_substr(trim($term), 0, self::MAX_TERM_LENGTH);
    }

    private function mayIncludeInactive(User $actor, bool $requested): bool
    {
        return $requested && $actor->can(Permission::CatalogManage->value);
    }

    /** Only advice templates are owned by a doctor; the other catalogs skip the lookup. */
    private function doctorId(User $actor, string $model): ?int
    {
        if ($model !== AdviceTemplate::class) {
            return null;
        }

        $id = Doctor::query()->where('user_id', $actor->id)->value('id');

        return $id === null ? null : (int) $id;
    }
}
