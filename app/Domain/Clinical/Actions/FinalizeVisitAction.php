<?php

namespace App\Domain\Clinical\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Clinical\Models\PrescribedMedicine;
use App\Domain\Clinical\ValueObjects\DosePattern;
use App\Domain\Clinical\ValueObjects\Duration;
use App\Domain\Clinical\VisitStatus;
use App\Domain\Finance\NumberGenerator;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Finalizes a draft (spec §7.3): checks it is complete enough to print,
 * assigns the RX number, freezes patient and doctor snapshots, and makes it
 * read-only. Running it again on a finalized visit changes nothing.
 */
class FinalizeVisitAction
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, CaseHistory $case): CaseHistory
    {
        $doctor = SaveVisitAction::doctorFor($actor);

        if ($case->doctor_id !== $doctor->id) {
            throw new AuthorizationException('Only the doctor who wrote this prescription can finalize it.');
        }

        return DB::transaction(function () use ($actor, $case) {
            $case = CaseHistory::query()->lockForUpdate()->withBlocks()->findOrFail($case->id);

            if ($case->status === VisitStatus::Finalized) {
                return $case; // idempotent
            }
            if ($case->status === VisitStatus::Cancelled) {
                throw ValidationException::withMessages(['status' => 'A cancelled prescription cannot be finalized.']);
            }

            $this->ensureComplete($case);

            $case->prescription_no = $this->numbers->next(NumberGenerator::PRESCRIPTION);
            $case->patient_snapshot = $this->patientSnapshot($case);
            $case->doctor_snapshot = $this->doctorSnapshot($case);
            $case->status = VisitStatus::Finalized;
            $case->finalized_at = now();
            $case->finalized_by = $actor->id;
            $case->row_version++;
            $case->save();

            $this->audit->log('case.finalized', $case, ['prescription_no' => $case->prescription_no]);

            return $case;
        });
    }

    /** A diagnosis or at least one medicine; every medicine with a valid dose and duration. */
    private function ensureComplete(CaseHistory $case): void
    {
        $errors = [];

        if (blank($case->diagnosis) && $case->medicines->isEmpty()) {
            $errors['diagnosis'] = 'Write a diagnosis or at least one medicine before finalizing.';
        }

        foreach ($case->medicines->values() as $i => $medicine) {
            /** @var PrescribedMedicine $medicine */
            try {
                DosePattern::fromSlots($medicine->dose_morning, $medicine->dose_noon, $medicine->dose_night);
            } catch (InvalidArgumentException $e) {
                $errors["medicines.{$i}.dose"] = "{$medicine->display_name}: {$e->getMessage()}";
            }

            try {
                Duration::of($medicine->duration_value, $medicine->duration_unit);
            } catch (InvalidArgumentException $e) {
                $errors["medicines.{$i}.duration_unit"] = "{$medicine->display_name}: {$e->getMessage()}";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, mixed> */
    private function patientSnapshot(CaseHistory $case): array
    {
        $patient = $case->patient;

        return [
            'code' => $patient->code,
            'name' => $patient->name,
            'name_bn' => $patient->name_bn,
            'age_text' => $patient->age_text,
            'gender' => $patient->gender->value,
            'phone' => $patient->phone,
            'address' => $patient->address,
        ];
    }

    /** @return array<string, mixed> */
    private function doctorSnapshot(CaseHistory $case): array
    {
        $doctor = $case->doctor()->with(['credentials', 'specialties'])->firstOrFail();
        $chamber = $case->chamber()->with('branches')->firstOrFail();
        $hours = $doctor->chambers()->where('chambers.id', $chamber->id)->first()?->getRelation('pivot');

        return [
            'name_en' => $doctor->name_en,
            'name_bn' => $doctor->name_bn,
            'designation_en' => $doctor->designation_en,
            'designation_bn' => $doctor->designation_bn,
            'reg_label' => $doctor->reg_label,
            'reg_no' => $doctor->reg_no,
            'credentials' => $doctor->credentials->map(fn ($c) => ['text_en' => $c->text_en, 'text_bn' => $c->text_bn])->all(),
            'specialties' => $doctor->specialties->pluck('name')->all(),
            'chamber' => [
                'name_en' => $chamber->name_en, 'name_bn' => $chamber->name_bn,
                'address_en' => $chamber->address_en, 'address_bn' => $chamber->address_bn, 'phone' => $chamber->phone,
                'visiting_hours_en' => $hours?->getAttribute('visiting_hours_en'),
                'visiting_hours_bn' => $hours?->getAttribute('visiting_hours_bn'),
            ],
        ];
    }
}
