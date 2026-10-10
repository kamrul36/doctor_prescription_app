<?php

namespace App\Domain\Clinical\Actions;

use App\Domain\Clinical\Exceptions\PossibleDuplicatePatients;
use App\Domain\Patient\Actions\SavePatientAction;
use App\Domain\Patient\Models\Patient;
use App\Domain\Patient\PatientSearchQuery;
use Illuminate\Support\Collection;

/**
 * Who the prescription is for. A doctor never has to register the patient
 * first: with `patient_id` the registered patient is used; otherwise the
 * typed details are checked for likely duplicates and, when none are found
 * (or the doctor chose "register new"), the patient is registered here with
 * a new code, inside the caller's transaction.
 */
class ResolvePatientAction
{
    public function __construct(private readonly SavePatientAction $register) {}

    /**
     * @param  array<string, mixed>  $data  validated pad data (`patient_id`, `patient`, `patient_choice`)
     *
     * @throws PossibleDuplicatePatients
     */
    public function handle(array $data): Patient
    {
        if (! empty($data['patient_id'])) {
            return Patient::query()->findOrFail($data['patient_id']);
        }

        $choice = $data['patient_choice'] ?? null;

        if ($choice !== null && $choice !== 'new') {
            return Patient::query()->findOrFail((int) $choice);
        }

        $details = $data['patient'];

        if ($choice !== 'new') {
            $candidates = $this->candidates($details);

            if ($candidates->isNotEmpty()) {
                throw new PossibleDuplicatePatients($candidates);
            }
        }

        return $this->register->handle(null, [
            'name' => $details['name'],
            'age_years' => (int) $details['age_years'],
            'dob' => null,
            'gender' => $details['gender'],
            'phone' => $details['phone'],
            'address' => $details['address'] ?? '',
        ]);
    }

    /**
     * Same phone, or a very similar name with about the same age.
     *
     * @param  array<string, mixed>  $details
     * @return Collection<int, Patient>
     */
    public function candidates(array $details): Collection
    {
        $byPhone = Patient::query()
            ->where(fn ($q) => $q->where('phone', $details['phone'])->orWhere('alt_phone', $details['phone']))
            ->limit(5)->get();

        $minScore = (float) config('clinical.duplicate_name_similarity', 0.85);
        $tolerance = (int) config('clinical.duplicate_age_tolerance', 2);
        $age = (int) $details['age_years'];

        $byName = app(PatientSearchQuery::class)->search((string) $details['name'])
            ->filter(fn (Patient $p) => PatientSearchQuery::similarity($details['name'], $p->name) >= $minScore
                && $p->ageYears() !== null && abs($p->ageYears() - $age) <= $tolerance)
            ->take(5);

        return $byPhone->concat($byName)->unique('id')->values();
    }
}
