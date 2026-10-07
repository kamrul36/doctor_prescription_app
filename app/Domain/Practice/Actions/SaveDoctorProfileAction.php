<?php

namespace App\Domain\Practice\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Finance\Money;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\VisitType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates the signed-in user's doctor profile: header details,
 * ordered credentials, and per chamber the visiting hours and fees.
 *
 * Chambers and fees are replaced by what is submitted: a chamber left out is
 * detached (with its fees); a blank fee removes that fee.
 */
class SaveDoctorProfileAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): Doctor
    {
        $this->ensureTemplateUsable($user, $data['default_template_id'] ?? null);

        return DB::transaction(function () use ($user, $data) {
            $doctor = Doctor::query()->where('user_id', $user->id)->first();
            $creating = $doctor === null;
            $doctor ??= new Doctor(['user_id' => $user->id]);

            $doctor->fill(collect($data)->except(['credentials', 'chambers'])->all())->save();

            if (array_key_exists('credentials', $data)) {
                $doctor->credentials()->delete();

                foreach (array_values($data['credentials'] ?? []) as $i => $credential) {
                    $doctor->credentials()->create([
                        'text_en' => $credential['text_en'],
                        'text_bn' => $credential['text_bn'] ?? null,
                        'sort_order' => $i,
                    ]);
                }
            }

            if (array_key_exists('chambers', $data)) {
                $this->syncChambers($doctor, $data['chambers'] ?? []);
            }

            $this->audit->log($creating ? 'doctor.created' : 'doctor.updated', $doctor, [
                'fields' => array_keys(collect($data)->except(['credentials', 'chambers'])->all()),
            ]);

            return $doctor->load(['credentials', 'chambers', 'fees']);
        });
    }

    /** @param  list<array<string, mixed>>  $chambers */
    private function syncChambers(Doctor $doctor, array $chambers): void
    {
        $sync = [];
        foreach ($chambers as $row) {
            $sync[(int) $row['chamber_id']] = [
                'visiting_hours_en' => $row['visiting_hours_en'] ?? null,
                'visiting_hours_bn' => $row['visiting_hours_bn'] ?? null,
            ];
        }
        $doctor->chambers()->sync($sync);

        // Rebuilt from scratch: fees of detached chambers go too.
        $doctor->fees()->delete();

        foreach ($chambers as $row) {
            foreach ($row['fees'] ?? [] as $visitType => $amount) {
                if ($amount === null || $amount === '' || VisitType::tryFrom((string) $visitType) === null) {
                    continue;
                }

                $doctor->fees()->create([
                    'chamber_id' => (int) $row['chamber_id'],
                    'visit_type' => $visitType,
                    'amount' => Money::fromDecimal((string) $amount),
                ]);
            }
        }
    }

    /** The default template must be active and either global or the doctor's own. */
    private function ensureTemplateUsable(User $user, mixed $templateId): void
    {
        if ($templateId === null || $templateId === '') {
            return;
        }

        $ownDoctorId = Doctor::query()->where('user_id', $user->id)->value('id');

        $usable = PrescriptionTemplate::query()
            ->whereKey($templateId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('doctor_id')->when($ownDoctorId, fn ($q) => $q->orWhere('doctor_id', $ownDoctorId)))
            ->exists();

        if (! $usable) {
            throw ValidationException::withMessages([
                'default_template_id' => 'Choose an active template that is shared or your own.',
            ]);
        }
    }
}
