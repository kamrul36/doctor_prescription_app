<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\AdviceTemplate;
use App\Domain\Catalog\Models\LabTest;
use App\Domain\Catalog\Models\Procedure;
use Illuminate\Database\Seeder;

/**
 * Starter catalogs. Idempotent and never overwrites what an admin changed:
 * an entry is created only if nothing (not even a deleted one) matches.
 * No fees are seeded; the doctor sets them. Bangla text is left for the
 * native-speaker review (spec section 15).
 */
class CatalogSeeder extends Seeder
{
    /** @var array<string, list<array{string, string|null}>> category => [name, timing note] */
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
            ['OPG (orthopantomogram)', null], ['IOPA X-ray', null], ['ECG', null],
        ],
        'Gynae' => [
            ['Pregnancy test (urine)', null], ['Serum beta-hCG', null], ['FSH', 'D2'], ['LH', 'D2'],
            ['Prolactin', 'D2'], ['Pap smear', null], ['Transvaginal USG', null],
        ],
    ];

    /** @var list<array{string, string}> code, name */
    private const PROCEDURES = [
        ['SCALING', 'Scaling'], ['POLISHING', 'Polishing'], ['EXTRACTION', 'Extraction'],
        ['FILLING', 'Filling'], ['RCT', 'Root canal treatment'], ['CROWN', 'Crown'],
        ['DRESSING', 'Dressing'], ['INJECTION', 'Injection'], ['IUCD', 'IUCD insertion'],
    ];

    /** @var list<array{string, string, string}> specialty, title, text */
    private const ADVICE = [
        ['general', 'General advice', "Drink plenty of water.\nTake rest and a balanced diet.\nTake the medicines regularly as advised.\nReturn at once if the problem gets worse."],
        ['dental', 'After extraction', "Bite on the gauze for 30 minutes.\nDo not rinse the mouth or spit for 24 hours.\nAvoid hot food and drinks today.\nDo not touch the area with the tongue or fingers."],
        ['dental', 'After scaling', "Mild gum soreness or bleeding for a day or two is normal.\nBrush gently twice a day.\nAvoid very hot, cold or spicy food for 24 hours."],
        ['gynae', 'Antenatal advice', "Take the prescribed iron, calcium and folic acid regularly.\nEat a balanced diet and drink plenty of water.\nReport bleeding, severe headache, swelling of the face or reduced baby movement at once."],
    ];

    public function run(): void
    {
        foreach (self::LAB_TESTS as $category => $tests) {
            foreach ($tests as [$name, $timing]) {
                if (! LabTest::withTrashed()->where('name', $name)->exists()) {
                    LabTest::create(['name' => $name, 'category' => $category, 'default_timing_note' => $timing]);
                }
            }
        }

        foreach (self::PROCEDURES as [$code, $name]) {
            if (! Procedure::withTrashed()->where('code', $code)->exists()) {
                Procedure::create(['code' => $code, 'name_en' => $name]);
            }
        }

        foreach (self::ADVICE as [$specialty, $title, $text]) {
            $exists = AdviceTemplate::withTrashed()->whereNull('doctor_id')->where('title', $title)->exists();

            if (! $exists) {
                AdviceTemplate::create(['specialty_code' => $specialty, 'title' => $title, 'text_en' => $text]);
            }
        }
    }
}
