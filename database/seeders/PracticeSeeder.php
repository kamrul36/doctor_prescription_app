<?php

namespace Database\Seeders;

use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\Models\Specialty;
use App\Domain\Practice\PrintMode;
use App\Domain\Practice\SectionKey as K;
use App\Domain\Practice\SectionZone as Z;
use App\Domain\Practice\SpecialtyBlock;
use App\Domain\Practice\TemplateLayout;
use Illuminate\Database\Seeder;

/**
 * Seeds the shared (doctor-less) general prescription template, A4. Every
 * doctor is a general physician; specialties on the doctor's profile fill the
 * `specialty` section instead of needing their own template. Sections with no
 * data (e.g. `specialty` for a general physician) print nothing.
 *
 * A template that already exists is left alone, so edits made in Settings
 * survive a re-seed. This seeder never deletes: the older `dental_pad` and
 * `gynae_letterhead` templates were retired (deactivated) by a migration.
 */
class PracticeSeeder extends Seeder
{
    public function run(): void
    {
        // The two specialties with built-in pad blocks. Created only when missing; an admin's
        // renaming or deactivation is kept.
        foreach ([
            ['code' => 'gynae', 'name' => 'Gynaecology & Obstetrics', 'block' => SpecialtyBlock::Gynae],
            ['code' => 'dental', 'name' => 'Dental', 'block' => SpecialtyBlock::Dental],
        ] as $specialty) {
            Specialty::query()->firstOrCreate(['code' => $specialty['code']], $specialty);
        }

        foreach ($this->templates() as $definition) {
            $sections = $definition['sections'];
            unset($definition['sections']);

            $template = PrescriptionTemplate::query()->firstOrCreate(
                ['doctor_id' => null, 'code' => $definition['code']],
                $definition + ['paper_size' => 'A4', 'is_active' => true],
            );

            if ($template->wasRecentlyCreated) {
                foreach ($sections as $i => [$key, $zone]) {
                    $labels = $key->defaultLabels();
                    $template->sections()->create([
                        'section_key' => $key,
                        'zone' => $zone,
                        'label_en' => $labels['en'],
                        'label_bn' => $labels['bn'],
                        'sort_order' => $i,
                        'is_visible' => true,
                    ]);
                }
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function templates(): array
    {
        return [
            [
                'code' => 'general',
                'name' => 'General',
                'layout' => TemplateLayout::TwoColumn,
                'default_print_mode' => PrintMode::WithLetterhead,
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'show_barcode' => false,
                'barcode_source' => 'prescription_no',
                'show_branch_footer' => false,
                'show_visiting_hours' => true,
                'show_signature' => true,
                'is_default' => true,
                'sections' => [
                    [K::PatientBlock, Z::Header],
                    [K::Complaints, Z::Left],
                    [K::Specialty, Z::Left],
                    [K::Examination, Z::Left],
                    [K::Vitals, Z::Left],
                    [K::Diagnosis, Z::Right],
                    [K::TreatmentPlan, Z::Right],
                    [K::InvestigationsReviewed, Z::Right],
                    [K::InvestigationsAdvised, Z::Right],
                    [K::Medicines, Z::Right],
                    [K::Advice, Z::Right],
                    [K::FollowUp, Z::Right],
                ],
            ],
        ];
    }
}
