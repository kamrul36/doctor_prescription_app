<?php

namespace App\Domain\Document\Support;

use App\Domain\Clinical\DoseTiming;
use App\Domain\Clinical\DoseUnit;
use App\Domain\Clinical\DurationUnit;
use App\Domain\Clinical\Models\CaseAdvice;
use App\Domain\Clinical\Models\CaseComplaint;
use App\Domain\Clinical\Models\CaseFinding;
use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Clinical\Models\OrderedTest;
use App\Domain\Clinical\Models\PrescribedMedicine;
use App\Domain\Clinical\Models\TreatmentPlanItem;
use App\Domain\Clinical\PeriodUnit;
use App\Domain\Clinical\TestKind;
use App\Domain\Clinical\VisitStatus;
use App\Domain\Patient\Models\Patient;
use App\Domain\Practice\Models\Chamber;
use App\Domain\Practice\Models\ChamberBranch;
use App\Domain\Practice\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * An unsaved visit with data in every section, for the template editor's live
 * preview: hiding, moving or relabelling a section always changes the page.
 * The letterhead is the signed-in doctor's (first assigned chamber) when there
 * is one, else a sample; the patient is always fictional. Nothing is saved.
 */
final class SamplePrescription
{
    public static function make(?User $user): CaseHistory
    {
        $doctor = $user?->doctor()->with(['credentials', 'chambers.branches'])->first();
        $chamber = $doctor?->chambers->first();

        $case = new CaseHistory([
            'visit_date' => today(),
            'diagnosis' => 'Acute pharyngitis',
            'vitals' => ['bp_sys' => 120, 'bp_dia' => 80, 'pulse' => 78, 'temp' => 100.4, 'weight_kg' => 64, 'spo2' => 98],
            'follow_up_value' => 7,
            'follow_up_unit' => PeriodUnit::Day,
        ]);
        $case->forceFill(['status' => VisitStatus::Finalized, 'prescription_no' => 'RX-'.today()->year.'-000123']);

        $case->forceFill([
            'patient_snapshot' => [
                'code' => today()->format('y').'000123', 'name' => 'Rahim Uddin', 'name_bn' => 'রহিম উদ্দিন',
                'age_text' => '45 y', 'gender' => 'male', 'phone' => '01711000000', 'address' => 'Mirpur, Dhaka',
            ],
            'doctor_snapshot' => $doctor ? self::doctorSnapshot($doctor, $chamber) : self::sampleDoctor(),
        ]);

        $case->setRelation('patient', new Patient);
        $case->setRelation('doctor', $doctor ?? new Doctor);
        $case->setRelation('chamber', $chamber ?? self::sampleChamber());

        $case->setRelation('complaints', self::rows(CaseComplaint::class, [
            ['kind' => 'complaint', 'text' => 'Sore throat', 'duration_value' => 3, 'duration_unit' => PeriodUnit::Day],
            ['kind' => 'complaint', 'text' => 'Fever', 'duration_value' => 2, 'duration_unit' => PeriodUnit::Day],
        ]));
        $case->setRelation('findings', self::rows(CaseFinding::class, [
            ['label' => 'Throat', 'value' => 'Congested, tonsils enlarged'],
            ['label' => 'Chest', 'value' => 'Clear'],
        ]));
        $case->setRelation('planItems', self::rows(TreatmentPlanItem::class, [
            ['description' => 'Warm saline gargle', 'status' => 'planned'],
        ]));
        $case->setRelation('tests', self::rows(OrderedTest::class, [
            ['kind' => TestKind::Reviewed, 'name_snapshot' => 'CBC', 'result' => 'WBC 11,200'],
            ['kind' => TestKind::Advised, 'name_snapshot' => 'Throat swab C/S'],
            ['kind' => TestKind::Advised, 'name_snapshot' => 'CRP'],
        ]));
        $case->setRelation('medicines', self::rows(PrescribedMedicine::class, [
            ['display_name' => 'Tab. Azithromycin 500 mg', 'dose_morning' => 1, 'dose_noon' => 0, 'dose_night' => 0,
                'dose_unit' => DoseUnit::Tablet, 'duration_value' => 5, 'duration_unit' => DurationUnit::Day, 'timing' => DoseTiming::AfterMeal],
            ['display_name' => 'Tab. Paracetamol 500 mg', 'dose_morning' => 1, 'dose_noon' => 1, 'dose_night' => 1,
                'dose_unit' => DoseUnit::Tablet, 'duration_value' => 3, 'duration_unit' => DurationUnit::Day, 'timing' => DoseTiming::AfterMeal,
                'instruction_en' => 'Only if fever is over 100°F', 'instruction_bn' => 'জ্বর ১০০°F এর বেশি হলে'],
        ]));
        $case->setRelation('advice', self::rows(CaseAdvice::class, [
            ['text_en' => 'Drink plenty of warm water', 'text_bn' => 'প্রচুর কুসুম গরম পানি পান করবেন'],
            ['text_en' => 'Take rest', 'text_bn' => 'বিশ্রাম নেবেন'],
        ]));

        return $case;
    }

    /**
     * The same shape as FinalizeVisitAction::doctorSnapshot(), from loaded models (no queries).
     *
     * @return array<string, mixed>
     */
    private static function doctorSnapshot(Doctor $doctor, ?Chamber $chamber): array
    {
        $hours = $chamber?->getRelation('pivot');

        return [
            'name_en' => $doctor->name_en, 'name_bn' => $doctor->name_bn,
            'designation_en' => $doctor->designation_en, 'designation_bn' => $doctor->designation_bn,
            'reg_label' => $doctor->reg_label, 'reg_no' => $doctor->reg_no,
            'credentials' => $doctor->credentials->map(fn ($c) => ['text_en' => $c->text_en, 'text_bn' => $c->text_bn])->all(),
            'chamber' => $chamber ? [
                'name_en' => $chamber->name_en, 'name_bn' => $chamber->name_bn,
                'address_en' => $chamber->address_en, 'address_bn' => $chamber->address_bn, 'phones' => $chamber->phones ?? [],
                'visiting_hours_en' => $hours?->getAttribute('visiting_hours_en'),
                'visiting_hours_bn' => $hours?->getAttribute('visiting_hours_bn'),
            ] : self::sampleDoctor()['chamber'],
        ];
    }

    /** @return array<string, mixed> */
    private static function sampleDoctor(): array
    {
        return [
            'name_en' => 'Dr. Sample Doctor', 'name_bn' => 'ডা. নমুনা চিকিৎসক',
            'designation_en' => 'Consultant, Medicine', 'designation_bn' => 'কনসালট্যান্ট, মেডিসিন',
            'reg_label' => 'BMDC', 'reg_no' => 'A-00000',
            'credentials' => [['text_en' => 'MBBS', 'text_bn' => 'এমবিবিএস'], ['text_en' => 'FCPS (Medicine)', 'text_bn' => 'এফসিপিএস (মেডিসিন)']],
            'chamber' => [
                'name_en' => 'Sample Chamber', 'name_bn' => 'নমুনা চেম্বার',
                'address_en' => 'House 1, Road 1, Dhaka', 'address_bn' => 'বাড়ি ১, রোড ১, ঢাকা', 'phones' => ['01700000000'],
                'visiting_hours_en' => 'Sat–Thu, 5 pm – 9 pm', 'visiting_hours_bn' => 'শনি–বৃহস্পতি, বিকেল ৫টা – রাত ৯টা',
            ],
        ];
    }

    private static function sampleChamber(): Chamber
    {
        $chamber = new Chamber;
        $chamber->setRelation('branches', self::rows(ChamberBranch::class, [
            ['name_en' => 'Branch: Uttara', 'name_bn' => 'শাখা: উত্তরা', 'phones' => ['01800000000']],
        ]));

        return $chamber;
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, T>
     */
    private static function rows(string $class, array $rows): Collection
    {
        return new Collection(array_map(fn (array $row, int $i) => (new $class)->forceFill([...$row, 'sort_order' => $i]), $rows, array_keys($rows)));
    }
}
