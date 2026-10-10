<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\AdviceTemplate;
use App\Domain\Catalog\Models\LabTest;
use App\Domain\Catalog\Models\Procedure;
use App\Domain\Practice\Models\Specialty;
use Illuminate\Database\Seeder;

/**
 * Starter catalogs. Idempotent and never overwrites what an admin changed:
 * an entry is created only if nothing (not even a deleted one) matches.
 * No fees are seeded; the doctor sets them. Bangla text is left for the
 * native-speaker review (spec section 15).
 */
class CatalogSeeder extends Seeder
{
    /** @var array<string, list<array{0: string, 1: string|null, 2?: string}>> category => [name, timing note, specialty (omitted = general)] */
    private const LAB_TESTS = [
        'Hematology' => [
            ['CBC', null], ['Hemoglobin (Hb%)', null], ['ESR', null], ['Blood group and Rh', null],
            ['Bleeding time / Clotting time', null], ['Platelet count', null],
        ],
        'Biochemistry' => [
            ['Random blood sugar (RBS)', null], ['Fasting blood sugar (FBS)', 'Fasting'],
            ['2 hours after breakfast (2HABF)', null], ['HbA1c', null], ['Serum creatinine', null],
            ['Serum uric acid', null], ['SGPT (ALT)', null], ['Lipid profile', 'Fasting'],
            ['Serum calcium', null], ['Serum TSH', null],
        ],
        'Urine and stool' => [
            ['Urine R/M/E', null], ['Urine culture and sensitivity', null], ['Stool R/M/E', null],
        ],
        'Imaging' => [
            ['X-ray chest P/A view', null], ['USG of lower abdomen', null], ['USG of whole abdomen', null],
            ['OPG (orthopantomogram)', null, 'dental'], ['IOPA X-ray', null, 'dental'], ['ECG', null],
        ],
        'Gynae' => [
            ['Pregnancy test (urine)', null, 'gynae'], ['Serum beta-hCG', null, 'gynae'], ['FSH', 'D2', 'gynae'],
            ['LH', 'D2', 'gynae'], ['Prolactin', 'D2', 'gynae'], ['Pap smear', null, 'gynae'],
            ['Transvaginal USG', null, 'gynae'],
        ],
    ];

    /** @var list<array{string, string, string|null}> code, name, specialty (null = general) */
    private const PROCEDURES = [
        ['SCALING', 'Scaling', 'dental'], ['POLISHING', 'Polishing', 'dental'], ['EXTRACTION', 'Extraction', 'dental'],
        ['FILLING', 'Filling', 'dental'], ['RCT', 'Root canal treatment', 'dental'], ['CROWN', 'Crown', 'dental'],
        ['DRESSING', 'Dressing', null], ['INJECTION', 'Injection', null], ['IUCD', 'IUCD insertion', 'gynae'],
    ];

    /** @var list<array{string|null, string, string}> specialty (null = general), title, text */
    private const ADVICE = [
        [null, 'General advice', "Drink plenty of water.\nTake rest and a balanced diet.\nTake the medicines regularly as advised.\nReturn at once if the problem gets worse."],
        ['dental', 'After extraction', "Bite on the gauze for 30 minutes.\nDo not rinse the mouth or spit for 24 hours.\nAvoid hot food and drinks today.\nDo not touch the area with the tongue or fingers."],
        ['dental', 'After scaling', "Mild gum soreness or bleeding for a day or two is normal.\nBrush gently twice a day.\nAvoid very hot, cold or spicy food for 24 hours."],
        ['gynae', 'Antenatal advice', "Take the prescribed iron, calcium and folic acid regularly.\nEat a balanced diet and drink plenty of water.\nReport bleeding, severe headache, swelling of the face or reduced baby movement at once."],
    ];

    public function run(): void
    {
        // Seeded specialties are looked up by code (PracticeSeeder and the specialties migration create them).
        $specialty = fn (?string $code) => $code === null ? null : Specialty::query()->where('code', $code)->value('id');

        foreach (self::LAB_TESTS as $category => $tests) {
            foreach ($tests as $test) {
                [$name, $timing] = $test;

                if (! LabTest::withTrashed()->where('name', $name)->exists()) {
                    LabTest::create([
                        'name' => $name, 'category' => $category, 'default_timing_note' => $timing,
                        'specialty_id' => $specialty($test[2] ?? null),
                    ]);
                }
            }
        }

        foreach (self::PROCEDURES as [$code, $name, $specialtyCode]) {
            if (! Procedure::withTrashed()->where('code', $code)->exists()) {
                Procedure::create(['code' => $code, 'name_en' => $name, 'specialty_id' => $specialty($specialtyCode)]);
            }
        }

        foreach (self::ADVICE as [$specialtyCode, $title, $text]) {
            $exists = AdviceTemplate::withTrashed()->whereNull('doctor_id')->where('title', $title)->exists();

            if (! $exists) {
                AdviceTemplate::create(['specialty_id' => $specialty($specialtyCode), 'title' => $title, 'text_en' => $text]);
            }
        }
    }
}
