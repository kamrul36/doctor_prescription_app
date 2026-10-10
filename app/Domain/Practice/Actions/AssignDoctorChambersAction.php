<?php

namespace App\Domain\Practice\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Practice\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The admin decides which chambers a doctor works at. Ticked chambers are
 * attached (with empty hours, which the doctor fills in), unticked ones are
 * detached together with that doctor's fees for them. A doctor user without
 * a profile gets a minimal one, so chambers can be assigned before their
 * first login.
 */
class AssignDoctorChambersAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  list<int>  $chamberIds */
    public function handle(User $doctorUser, array $chamberIds): Doctor
    {
        return DB::transaction(function () use ($doctorUser, $chamberIds) {
            $doctor = Doctor::query()->firstOrCreate(
                ['user_id' => $doctorUser->id],
                ['name_en' => $doctorUser->name, 'reg_label' => 'BMDC'],
            );

            $chamberIds = array_values(array_unique(array_map('intval', $chamberIds)));
            $before = $doctor->chambers()->pluck('chambers.id')->map(fn ($id) => (int) $id)->all();
            $removed = array_values(array_diff($before, $chamberIds));
            $added = array_values(array_diff($chamberIds, $before));

            $doctor->chambers()->detach($removed);
            $doctor->fees()->whereIn('chamber_id', $removed)->delete();
            $doctor->chambers()->attach($added);

            if ($added !== [] || $removed !== [] || $doctor->wasRecentlyCreated) {
                $this->audit->log('doctor.chambers_assigned', $doctor, [
                    'added' => $added, 'removed' => $removed, 'profile_created' => $doctor->wasRecentlyCreated,
                ]);
            }

            return $doctor->load(['chambers', 'fees', 'user']);
        });
    }
}
