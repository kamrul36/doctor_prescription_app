<?php

namespace App\Domain\Practice;

use App\Domain\Access\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Users with the doctor role, with their profile and chambers, for the admin screens. */
class DoctorDirectory
{
    /** @return Collection<int, User> */
    public function all(): Collection
    {
        return User::query()
            ->role(Role::DOCTOR, Role::GUARD)
            ->with(['doctor.chambers', 'doctor.specialties'])
            ->orderBy('name')
            ->get();
    }
}
