<?php

namespace App\Http\Requests\Practice;

use App\Domain\Access\Role;
use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\Doctor;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin: the chambers a doctor works at. An active chamber can be assigned;
 * an inactive one only stays if the doctor already has it.
 */
class AssignDoctorChambersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('assignChambers', Doctor::class);
    }

    /** The Blade form sends `chamber_ids[<id>]=0|1`; the API sends a list. */
    protected function prepareForValidation(): void
    {
        $ids = $this->input('chamber_ids');

        if (is_array($ids) && ! array_is_list($ids)) {
            $this->merge(['chamber_ids' => array_map('intval', array_keys(array_filter(
                $ids,
                fn ($ticked) => filter_var($ticked, FILTER_VALIDATE_BOOLEAN),
            )))]);
        }

        if ($ids === null || $ids === '') {
            $this->merge(['chamber_ids' => []]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'chamber_ids' => ['present', 'array', 'max:50'],
            'chamber_ids.*' => ['integer', 'distinct', $this->usableChamber()],
        ];
    }

    private function usableChamber(): Closure
    {
        /** @var User $doctorUser */
        $doctorUser = $this->route('user');
        $assigned = Doctor::query()->where('user_id', $doctorUser->id)->first()?->chambers()->pluck('chambers.id')->all() ?? [];

        return function (string $attribute, mixed $value, Closure $fail) use ($assigned): void {
            $chamber = Chamber::query()->find($value);

            if ($chamber === null || (! $chamber->is_active && ! in_array($chamber->id, $assigned))) {
                $fail('Choose an active chamber.');
            }
        };
    }

    /** Only users with the doctor role have chambers. */
    public function doctorUser(): User
    {
        /** @var User $user */
        $user = $this->route('user');
        abort_unless($user->hasRole(Role::DOCTOR), 404);

        return $user;
    }
}
