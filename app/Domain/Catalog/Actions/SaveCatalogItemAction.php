<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\CatalogCache;
use App\Domain\Catalog\Models\AdviceTemplate;
use App\Domain\Catalog\Models\LabTest;
use App\Domain\Catalog\Models\Procedure;
use App\Domain\Practice\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a lab test, procedure or advice template.
 *
 * A new advice template belongs to the actor's doctor profile (shared if they
 * have none); an existing one keeps its owner, and the client can never set it.
 * Only one advice template per owner can be the default for a prescription
 * template. The typeahead cache is flushed after the commit.
 */
class SaveCatalogItemAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CatalogCache $cache,
    ) {}

    /**
     * @template TModel of LabTest|Procedure|AdviceTemplate
     *
     * @param  TModel|null  $item  null to create
     * @param  class-string<TModel>  $model
     * @param  array<string, mixed>  $data  validated fields
     * @return TModel
     */
    public function handle(User $actor, string $model, ?Model $item, array $data): Model
    {
        try {
            $item = DB::transaction(function () use ($actor, $model, $item, $data) {
                $creating = $item === null;
                $item ??= new $model;

                if ($creating && $item instanceof AdviceTemplate) {
                    $item->doctor_id = Doctor::query()->where('user_id', $actor->id)->value('id');
                }

                $item->fill($data);

                if ($item instanceof AdviceTemplate && ! $item->is_active) {
                    $item->is_default_for_template_id = null; // an inactive advice cannot stay a default
                }

                $item->save();

                if ($item instanceof AdviceTemplate && $item->is_default_for_template_id !== null) {
                    AdviceTemplate::query()
                        ->where('is_default_for_template_id', $item->is_default_for_template_id)
                        ->where(fn ($q) => $item->doctor_id === null
                            ? $q->whereNull('doctor_id')
                            : $q->where('doctor_id', $item->doctor_id))
                        ->whereKeyNot($item->id)
                        ->update(['is_default_for_template_id' => null]);
                }

                // Ids and field names only; the catalog texts are not copied into the audit trail.
                $this->audit->log(
                    'catalog.'.$item->getTable().($creating ? '.created' : '.updated'),
                    $item,
                    ['fields' => array_keys($data)],
                );

                return $item;
            });
        } catch (UniqueConstraintViolationException) {
            // Two requests raced past the validation rule; the database constraint is the last word.
            throw ValidationException::withMessages(['code' => 'This code is already in use.']);
        }

        $this->cache->flush();

        return $item;
    }
}
