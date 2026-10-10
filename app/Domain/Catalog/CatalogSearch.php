<?php

namespace App\Domain\Catalog;

use App\Domain\Access\Permission;
use App\Domain\Catalog\Models\AdviceTemplate;
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
 * matches only when the prefix matches do not fill `take`. Within the result,
 * general items (no specialty) and items of the doctor's specialties come
 * first; items of other specialties follow, so nothing is unreachable. User
 * input is escaped so `%` and `_` match themselves. Inactive and deleted rows
 * are hidden unless a catalog manager asks for them. Advice templates show
 * only shared ones and the actor's own.
 */
class CatalogSearch
{
    public const DEFAULT_TAKE = 10;

    public const MAX_TAKE = 50;

    /** `specialty=general` filters on items without a specialty. */
    public const GENERAL = 'general';

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
        [$doctorId, $specialties] = $this->viewer($actor);
        $doctorId = $model === AdviceTemplate::class ? $doctorId : null;

        // Doctors with the same specialties share lab test and procedure results; advice is per doctor.
        return $this->cache->remember($model, [
            'term' => $term, 'take' => $take, 'inactive' => $includeInactive,
            'specialty' => $specialty, 'doctor' => $doctorId, 'ranked_for' => implode(',', $specialties),
        ], function () use ($model, $resource, $doctorId, $specialties, $term, $take, $includeInactive, $specialty) {
            $models = $this->match($model, $doctorId, $specialties, $term, $take, $includeInactive, $specialty);

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
        $doctorId = $model === AdviceTemplate::class ? $this->viewer($actor)[0] : null;
        $query = $this->visible($model, $doctorId, $this->mayIncludeInactive($actor, $includeInactive), $specialty);
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
     * @param  list<int>  $specialties
     * @return Collection<int, TModel>
     */
    private function match(string $model, ?int $doctorId, array $specialties, string $term, int $take, bool $includeInactive, ?string $specialty)
    {
        $base = fn () => $this->rankBySpecialty($this->visible($model, $doctorId, $includeInactive, $specialty), $specialties)
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

        // Stable sort: relevant items lead, and within each group prefix matches stay before contains matches.
        return $prefix->concat($contains)
            ->sortBy(fn (Model $m) => $this->isRelevant($m->getAttribute('specialty_id'), $specialties) ? 0 : 1)
            ->values();
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
        }

        if ($specialty === self::GENERAL) {
            $query->whereNull('specialty_id');
        } elseif ($specialty !== null && $specialty !== '') {
            // A specialty id; anything else matches nothing rather than everything.
            $query->where('specialty_id', ctype_digit($specialty) ? (int) $specialty : 0);
        }

        return $query->with('specialty');
    }

    /**
     * Orders general items and those of the given specialties first.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<int>  $specialties
     * @return Builder<TModel>
     */
    private function rankBySpecialty(Builder $query, array $specialties): Builder
    {
        if ($specialties === []) {
            return $query->orderByRaw('CASE WHEN specialty_id IS NULL THEN 0 ELSE 1 END');
        }

        $placeholders = implode(', ', array_fill(0, count($specialties), '?'));

        return $query->orderByRaw("CASE WHEN specialty_id IS NULL OR specialty_id IN ({$placeholders}) THEN 0 ELSE 1 END", $specialties);
    }

    /** @param  list<int>  $specialties */
    private function isRelevant(mixed $specialtyId, array $specialties): bool
    {
        return $specialtyId === null || in_array((int) $specialtyId, $specialties, true);
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

    /**
     * The actor's doctor profile id and specialty ids (sorted), in one query.
     * A user without a profile ranks like a general physician.
     *
     * @return array{int|null, list<int>}
     */
    private function viewer(User $actor): array
    {
        $rows = DB::table('doctors')
            ->leftJoin('doctor_specialties', 'doctor_specialties.doctor_id', '=', 'doctors.id')
            ->where('doctors.user_id', $actor->id)
            ->orderBy('doctor_specialties.specialty_id')
            ->get(['doctors.id', 'doctor_specialties.specialty_id']);

        if ($rows->isEmpty()) {
            return [null, []];
        }

        $specialties = $rows->pluck('specialty_id')->filter()->map(fn ($id) => (int) $id)->values()->all();

        return [(int) $rows->first()->id, $specialties];
    }
}
