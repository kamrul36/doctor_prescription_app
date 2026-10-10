<?php

namespace App\Domain\Clinical\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Clinical\Specialty\SpecialtySchemas;
use App\Domain\Clinical\TestKind;
use App\Domain\Clinical\VisitStatus;
use App\Domain\Clinical\Vitals;
use App\Domain\Finance\Money;
use App\Domain\Patient\Models\Patient;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\VisitType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes a draft prescription: creates the visit (resolving or registering
 * the patient) or updates an existing draft, then replaces every block with
 * the submitted rows. One transaction, so a failed save leaves nothing
 * behind (not even a patient code).
 *
 * Only the visit's own doctor may write it, and only while it is a draft;
 * this holds for every user, a super admin included.
 */
class SaveVisitAction
{
    public function __construct(
        private readonly ResolvePatientAction $patients,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated by SaveVisitRequest
     */
    public function handle(User $actor, ?CaseHistory $case, array $data): CaseHistory
    {
        $doctor = self::doctorFor($actor);

        if ($case !== null) {
            if ($case->doctor_id !== $doctor->id) {
                throw new AuthorizationException('Only the doctor who wrote this prescription can change it.');
            }
            if ($case->status !== VisitStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'A finalized or cancelled prescription cannot be changed.']);
            }
        }

        $chamberId = (int) $data['chamber_id'];
        $usable = $doctor->activeChambers()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! in_array($chamberId, $usable, true) && $chamberId !== $case?->chamber_id) {
            throw ValidationException::withMessages(['chamber_id' => 'Choose one of your chambers.']);
        }

        return DB::transaction(function () use ($actor, $doctor, $case, $data, $chamberId) {
            $creating = $case === null;

            if ($creating) {
                $patient = $this->patients->handle($data);
                $case = new CaseHistory;
                $case->patient_id = $patient->id;
                $case->doctor_id = $doctor->id;
                $case->visit_date = now()->startOfDay();
                $case->created_by = $actor->id;
                $case->template_id = $doctor->default_template_id ?? $this->sharedDefaultTemplateId();
            } else {
                $case = CaseHistory::query()->lockForUpdate()->findOrFail($case->id);
                $patient = $case->patient;
            }

            if ($creating || $case->chamber_id !== $chamberId) {
                $case->visit_no_today = $this->nextVisitNo($doctor->id, $chamberId, $case->visit_date->toDateString());
            }

            $case->fill([
                'chamber_id' => $chamberId,
                'visit_type' => $data['visit_type'] ?? ($creating ? self::suggestVisitType($patient, $doctor)->value : $case->visit_type->value),
                'vitals' => Vitals::clean($data['vitals'] ?? null),
                'diagnosis' => $data['diagnosis'] ?? null,
                'specialty_data' => SpecialtySchemas::clean($data['specialty_data'] ?? null),
                'follow_up_value' => $data['follow_up_value'] ?? null,
                'follow_up_unit' => isset($data['follow_up_value']) ? ($data['follow_up_unit'] ?? null) : null,
                'follow_up_date' => $data['follow_up_date'] ?? null,
                'notes_private' => $data['notes_private'] ?? null,
            ]);
            if (! $creating) {
                $case->row_version++;
            }
            $case->save();

            $this->replaceBlocks($case, $data);

            $this->audit->log($creating ? 'case.created' : 'case.updated', $case, [
                'patient_id' => $patient->id,
                'patient_registered' => $creating && $patient->wasRecentlyCreated,
            ]);

            // Keep the resolved instance: it knows whether the patient was registered just now.
            return $case->setRelation('patient', $patient)->load(['chamber', 'doctor'])->loadMissing(['complaints', 'findings', 'planItems', 'tests', 'medicines', 'advice']);
        });
    }

    /** The signed-in user's doctor profile; writing prescriptions needs one. */
    public static function doctorFor(User $actor): Doctor
    {
        $doctor = Doctor::query()->where('user_id', $actor->id)->with(['chambers', 'specialties'])->first();

        if ($doctor === null) {
            throw ValidationException::withMessages(['doctor' => 'Set up your doctor profile before writing prescriptions.']);
        }

        return $doctor;
    }

    /** Follow-up when the patient's last finalized visit with this doctor is recent enough. */
    public static function suggestVisitType(Patient $patient, Doctor $doctor): VisitType
    {
        $last = CaseHistory::query()
            ->where('patient_id', $patient->id)
            ->where('doctor_id', $doctor->id)
            ->where('status', VisitStatus::Finalized)
            ->max('visit_date');

        $window = (int) config('clinical.follow_up_window_days', 14);

        return $last !== null && now()->startOfDay()->diffInDays($last, true) <= $window ? VisitType::FollowUp : VisitType::New;
    }

    /** Daily serial per doctor and chamber, taken under a lock so two saves never share one. */
    private function nextVisitNo(int $doctorId, int $chamberId, string $date): int
    {
        $max = CaseHistory::query()
            ->where('doctor_id', $doctorId)
            ->where('chamber_id', $chamberId)
            ->whereDate('visit_date', $date)
            ->lockForUpdate()
            ->max('visit_no_today');

        return ((int) $max) + 1;
    }

    private function sharedDefaultTemplateId(): ?int
    {
        $id = PrescriptionTemplate::query()->whereNull('doctor_id')->where('is_default', true)->where('is_active', true)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Replaces each block with the submitted rows, in order. A block that was
     * not sent at all (API partial update) is left as it is.
     *
     * @param  array<string, mixed>  $data
     */
    private function replaceBlocks(CaseHistory $case, array $data): void
    {
        $blocks = [
            'complaints' => fn (array $r) => [
                'kind' => 'complaint', 'text' => $r['text'],
                'duration_value' => $r['duration_value'] ?? null,
                'duration_unit' => isset($r['duration_value']) ? ($r['duration_unit'] ?? null) : null,
            ],
            'findings' => fn (array $r) => ['label' => $r['label'] ?? null, 'value' => $r['value']],
            'plan_items' => fn (array $r) => [
                'procedure_id' => $r['procedure_id'] ?? null, 'description' => $r['description'],
                'tooth_no' => $r['tooth_no'] ?? null, 'status' => $r['status'] ?? 'planned',
                'fee' => isset($r['fee']) && $r['fee'] !== '' ? Money::fromDecimal((string) $r['fee']) : null,
                'is_billable' => (bool) ($r['is_billable'] ?? true),
            ],
            'tests' => fn (array $r) => [
                'kind' => $r['kind'] ?? TestKind::Advised->value, 'lab_test_id' => $r['lab_test_id'] ?? null,
                'name_snapshot' => $r['name'], 'timing_note' => $r['timing_note'] ?? null, 'result' => $r['result'] ?? null,
            ],
            'medicines' => fn (array $r) => [
                'display_name' => $r['name'],
                'dose_morning' => $r['dose_morning'] ?? null, 'dose_noon' => $r['dose_noon'] ?? null, 'dose_night' => $r['dose_night'] ?? null,
                'dose_unit' => $r['dose_unit'] ?? null, 'timing' => $r['timing'] ?? null,
                'duration_value' => $r['duration_value'] ?? null, 'duration_unit' => $r['duration_unit'] ?? null,
                'instruction_en' => $r['instruction'] ?? null,
            ],
            'advice' => fn (array $r) => [
                'text_en' => $r['text'], 'text_bn' => $r['text_bn'] ?? null, 'source_template_id' => $r['source_template_id'] ?? null,
            ],
        ];

        $relations = ['complaints' => 'complaints', 'findings' => 'findings', 'plan_items' => 'planItems', 'tests' => 'tests', 'medicines' => 'medicines', 'advice' => 'advice'];

        foreach ($blocks as $key => $map) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $relation = $case->{$relations[$key]}();
            $relation->delete();

            foreach (array_values($data[$key] ?? []) as $i => $row) {
                $relation->create($map($row) + ['sort_order' => $i]);
            }
        }
    }
}
