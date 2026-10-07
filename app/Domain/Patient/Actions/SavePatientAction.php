<?php

namespace App\Domain\Patient\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Finance\NumberGenerator;
use App\Domain\Patient\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Registers or updates a patient. The code comes from the row-locked counter
 * inside the same transaction, so a failed save never burns a number, and it is
 * never touched on update.
 */
class SavePatientAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated patient fields
     */
    public function handle(?Patient $patient, array $data): Patient
    {
        return DB::transaction(function () use ($patient, $data) {
            $creating = $patient === null;
            $patient ??= new Patient;

            $patient->fill($this->withAge($patient, $data));

            if ($creating) {
                $patient->code = $this->numbers->next(NumberGenerator::PATIENT);
                $patient->created_by = Auth::id();
            }

            $patient->save();

            // Field names only: allergies, conditions and notes are clinical text.
            $this->audit->log($creating ? 'patient.created' : 'patient.updated', $patient, [
                'code' => $patient->code,
                'fields' => array_keys($data),
            ]);

            return $patient;
        });
    }

    /**
     * A date of birth wins and clears any told age. A told age is stamped with
     * today's date so it can keep advancing, and is only re-stamped when it changes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withAge(Patient $patient, array $data): array
    {
        if (filled($data['dob'] ?? null)) {
            return [...$data, 'age_years' => null, 'age_recorded_on' => null];
        }

        if (array_key_exists('dob', $data)) {
            $data['dob'] = null;
        }

        if (filled($data['age_years'] ?? null)) {
            $changed = $patient->dob !== null
                || $patient->age_years === null
                || $patient->age_recorded_on === null
                || (int) $data['age_years'] !== $patient->ageYears();

            if ($changed) {
                $data['age_recorded_on'] = now()->toDateString();
            }
        }

        return $data;
    }
}
