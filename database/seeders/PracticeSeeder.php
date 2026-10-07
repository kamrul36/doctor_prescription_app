<?php

namespace Database\Seeders;

use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\PrintMode;
use App\Domain\Practice\SectionKey as K;
use App\Domain\Practice\SectionZone as Z;
use App\Domain\Practice\TemplateLayout;
use Illuminate\Database\Seeder;

/**
 * Seeds the shared (doctor-less) templates that reproduce the two sample
 * prescriptions plus a general one, all A4. A template that already exists is
 * left alone, so edits made in Settings survive a re-seed.
 *
 * The pad_only margins are starting values: calibrate them on the real pad.
 */
class PracticeSeeder extends Seeder
{
    public function run(): void
    {
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
                // Sample 1: pre-printed dental pad, examination and plan on the left, Rx on the right.
                'code' => 'dental_pad',
                'name' => 'Dental pad',
                'specialty_code' => 'dental',
                'layout' => TemplateLayout::SidebarLeft,
                'default_print_mode' => PrintMode::PadOnly,
                'margins' => ['top' => 55, 'right' => 12, 'bottom' => 30, 'left' => 12],
                'show_barcode' => true,
                'barcode_source' => 'prescription_no',
                'show_branch_footer' => true,
                'show_visiting_hours' => false,
                'show_signature' => true,
                'is_default' => true,
                'sections' => [
                    [K::PatientBlock, Z::Header],
                    [K::Examination, Z::Left],
                    [K::TreatmentPlan, Z::Left],
                    [K::Medicines, Z::Right],
                    [K::Advice, Z::Right],
                    [K::FollowUp, Z::Right],
                ],
            ],
            [
                // Sample 2: letterhead gynae prescription, history left, results and Rx right.
                'code' => 'gynae_letterhead',
                'name' => 'Gynae letterhead',
                'specialty_code' => 'gynae',
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
                    [K::Vitals, Z::Left],
                    [K::InvestigationsReviewed, Z::Right],
                    [K::InvestigationsAdvised, Z::Right],
                    [K::Medicines, Z::Right],
                    [K::Advice, Z::Right],
                ],
            ],
            [
                'code' => 'general',
                'name' => 'General',
                'specialty_code' => 'general',
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
                    [K::Examination, Z::Left],
                    [K::Vitals, Z::Left],
                    [K::Diagnosis, Z::Right],
                    [K::InvestigationsAdvised, Z::Right],
                    [K::Medicines, Z::Right],
                    [K::Advice, Z::Right],
                    [K::FollowUp, Z::Right],
                ],
            ],
        ];
    }
}
