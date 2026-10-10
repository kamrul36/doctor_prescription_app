<?php

namespace App\Domain\Practice\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Practice\Models\Specialty;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin create/update of a specialty list entry. The code is made from the
 * name once and never changes (it identifies the entry in seeds and data);
 * renaming only changes the label. Specialties are deactivated, never deleted.
 */
class SaveSpecialtyAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function handle(?Specialty $specialty, array $data): Specialty
    {
        return DB::transaction(function () use ($specialty, $data) {
            $creating = $specialty === null;
            $specialty ??= new Specialty(['code' => Specialty::uniqueCode($data['name'])]);

            $specialty->fill($data);
            if ($creating) {
                $specialty->created_by = Auth::id();
            }
            $specialty->save();

            $this->audit->log($creating ? 'specialty.created' : 'specialty.updated', $specialty, [
                'fields' => array_keys($data),
            ]);

            return $specialty;
        });
    }
}
