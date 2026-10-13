<?php

namespace App\Domain\Document\Builders;

use App\Domain\Clinical\Actions\FinalizeVisitAction;
use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Clinical\Models\PrescribedMedicine;
use App\Domain\Clinical\Specialty\SpecialtySchemas;
use App\Domain\Clinical\TestKind;
use App\Domain\Clinical\Vitals;
use App\Domain\Document\PrescriptionDocument;
use App\Domain\Document\Support\PrintLocale;
use App\Domain\Document\Support\PrintOptions;
use App\Domain\Document\Support\Translator;
use App\Domain\Patient\Gender;
use App\Domain\Patient\Models\Patient;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Domain\Practice\Models\TemplateSection;
use App\Domain\Practice\SectionKey;
use App\Domain\Practice\SectionZone;
use App\Domain\Practice\SpecialtyBlock;
use App\Domain\Practice\TemplateLayout;
use App\Support\Bilingual;
use Illuminate\Support\Collection;

/**
 * Turns a visit into a PrescriptionDocument in the print language (spec §6, §11).
 * The only place that applies the language fallback for prints and reads the
 * snapshots: a finalized visit prints the patient, doctor and chamber as they
 * were at finalization; a draft preview prints the current data.
 *
 * @phpstan-import-type Item from PrescriptionDocument
 */
final class PrescriptionDocumentBuilder
{
    private Translator $t;

    public function build(CaseHistory $case, PrintOptions $options): PrescriptionDocument
    {
        $this->t = new Translator($options);
        $case->loadMissing(['patient', 'doctor', 'chamber.branches', 'complaints', 'findings', 'planItems', 'tests', 'medicines', 'advice']);

        $template = self::templateFor($case);

        $zones = ['header' => [], 'left' => [], 'right' => [], 'footer' => []];
        $showPatient = false;
        foreach ($this->sections($template) as $section) {
            if ($section->section_key === SectionKey::PatientBlock) {
                $showPatient = true;

                continue;
            }
            $items = $this->items($section->section_key, $case);
            if ($items !== []) {
                $defaults = $section->section_key->defaultLabels();
                $zones[$section->zone->value][] = [
                    'key' => $section->section_key->value,
                    'label' => $this->t->label($section->label_en ?: $defaults['en'], $section->label_bn ?: $defaults['bn']),
                    'items' => $items,
                ];
            }
        }

        $doctor = $case->doctor_snapshot ?? FinalizeVisitAction::doctorSnapshot($case);
        $patient = $case->patient_snapshot ?? FinalizeVisitAction::patientSnapshot($case);
        $margins = $template->margins ?? [];

        return new PrescriptionDocument(
            options: $options,
            isDraft: $case->isDraft(),
            prescriptionNo: $case->prescription_no,
            date: $this->t->date($case->visit_date),
            doctor: $this->doctor($doctor),
            chamber: $this->chamber($doctor['chamber'] ?? [], $case),
            patient: $showPatient ? $this->patient($patient, $case) : [],
            zones: $zones,
            margins: [
                'top' => (int) ($margins['top'] ?? 10), 'right' => (int) ($margins['right'] ?? 10),
                'bottom' => (int) ($margins['bottom'] ?? 10), 'left' => (int) ($margins['left'] ?? 10),
            ],
            twoColumns: $template === null || $template->layout !== TemplateLayout::SingleColumn,
            showSignature: $template === null || $template->show_signature,
            showVisitingHours: $template === null || $template->show_visiting_hours,
            showBranchFooter: (bool) $template?->show_branch_footer,
            words: [
                'signature' => $this->t->ui('Signature'),
                'draft' => $this->t->ui('DRAFT'),
                'rx_no' => $this->t->ui('Rx no.'),
            ],
        );
    }

    /** The visit's template, else the doctor's default, else the shared default. */
    public static function templateFor(CaseHistory $case): ?PrescriptionTemplate
    {
        return $case->template()->with('sections')->first()
            ?? $case->doctor?->defaultTemplate()->with('sections')->first()
            ?? PrescriptionTemplate::query()->with('sections')->whereNull('doctor_id')->where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * The visible sections in order; every section (patient block on top, the rest on the right) when there is no template.
     *
     * @return Collection<int, TemplateSection>
     */
    private function sections(?PrescriptionTemplate $template): Collection
    {
        if ($template !== null) {
            return $template->sections->filter(fn (TemplateSection $s) => $s->is_visible)->values();
        }

        return collect(SectionKey::cases())->map(fn (SectionKey $key) => new TemplateSection([
            'section_key' => $key,
            'zone' => $key === SectionKey::PatientBlock ? SectionZone::Header : SectionZone::Right,
            'is_visible' => true,
        ]));
    }

    /** @return list<Item> */
    private function items(SectionKey $key, CaseHistory $case): array
    {
        $t = $this->t;

        return match ($key) {
            SectionKey::PatientBlock => [],
            SectionKey::Complaints => $case->complaints->map(fn ($c) => $this->item(null, [
                $c->text.($c->duration_value && $c->duration_unit
                    ? ' — '.$this->each(fn (string $lang) => $t->digits($c->duration_value, $lang).' '.$c->duration_unit->label((int) $c->duration_value, $lang))
                    : ''),
            ]))->values()->all(),
            SectionKey::Specialty => $this->specialty($case),
            SectionKey::Examination => $case->findings->map(fn ($f) => $this->item(null, [($f->label ? $f->label.': ' : '').$f->value]))->values()->all(),
            SectionKey::Vitals => $this->vitals($case->vitals),
            SectionKey::Diagnosis => filled($case->diagnosis) ? [$this->item(null, [(string) $case->diagnosis])] : [],
            SectionKey::TreatmentPlan => $case->planItems->map(fn ($p) => $this->item(null, [
                $p->description.($p->tooth_no ? ' ('.$t->ui('tooth :no', ['no' => $p->tooth_no]).')' : ''),
            ]))->values()->all(),
            SectionKey::InvestigationsAdvised => $case->tests->where('kind', TestKind::Advised)
                ->map(fn ($test) => $this->item(null, [$test->name_snapshot.($test->timing_note ? ' ('.$test->timing_note.')' : '')]))->values()->all(),
            SectionKey::InvestigationsReviewed => $case->tests->where('kind', TestKind::Reviewed)
                ->map(fn ($test) => $this->item(null, [$test->name_snapshot.($test->result ? ': '.$test->result : '')]))->values()->all(),
            SectionKey::Medicines => $case->medicines->map(fn (PrescribedMedicine $m) => $this->item($m->display_name, [
                ...$this->medicineLines($m),
                ...$t->text($m->instruction_en, $m->instruction_bn),
            ]))->values()->all(),
            SectionKey::Advice => $case->advice->map(fn ($a) => $this->item(null, $t->text($a->text_en, $a->text_bn)))
                ->filter(fn (array $item) => $item['lines'] !== [])->values()->all(),
            SectionKey::FollowUp => $this->followUp($case),
        };
    }

    /**
     * "2+0+2 tablet · 7 days" in each print language (bilingual: the English line, then the Bangla one).
     *
     * @return list<string>
     */
    private function medicineLines(PrescribedMedicine $medicine): array
    {
        $lines = array_map(fn (string $lang) => $medicine->summary($lang, $this->t->options->banglaDigits), $this->t->locale()->languages());

        return array_values(array_unique(array_filter($lines, 'filled')));
    }

    /** @return list<Item> */
    private function specialty(CaseHistory $case): array
    {
        $items = [];
        foreach ($case->specialty_data ?? [] as $block => $values) {
            $enum = SpecialtyBlock::tryFrom((string) $block);
            if ($enum === null || $values === []) {
                continue;
            }
            $fields = SpecialtySchemas::for($enum)->fields();
            $lines = [];
            foreach ($values as $field => $value) {
                $def = $fields[$field] ?? null;
                $label = $def ? $this->t->ui($def['label']) : (string) $field;
                $shown = match (true) {
                    isset($def['options'][$value]) => $this->t->ui($def['options'][$value]),
                    ($def['type'] ?? null) === 'number' => $this->t->number($value),
                    default => (string) $value,
                };
                $lines[] = "{$label}: {$shown}";
            }
            $items[] = $this->item($this->t->enum($enum), $lines);
        }

        return $items;
    }

    /**
     * @param  array<string, int|float>|null  $vitals
     * @return list<Item>
     */
    private function vitals(?array $vitals): array
    {
        if (! $vitals) {
            return [];
        }

        $parts = [];
        if (isset($vitals['bp_sys'], $vitals['bp_dia'])) {
            $parts[] = $this->t->ui('BP').' '.$this->t->number("{$vitals['bp_sys']}/{$vitals['bp_dia']}").' mmHg';
        }
        foreach (Vitals::FIELDS as $key => $field) {
            if (! str_starts_with($key, 'bp_') && isset($vitals[$key])) {
                $parts[] = $this->t->ui($field['label']).' '.$this->t->number($vitals[$key]).$field['unit'];
            }
        }

        return $parts === [] ? [] : [$this->item(null, [implode(' · ', $parts)])];
    }

    /** @return list<Item> */
    private function followUp(CaseHistory $case): array
    {
        $lines = [];
        if ($case->follow_up_value && $case->follow_up_unit) {
            $unit = $case->follow_up_unit;
            $lines[] = $this->each(fn (string $lang) => __('After :n :unit', [
                'n' => $this->t->digits($case->follow_up_value, $lang), 'unit' => $unit->label((int) $case->follow_up_value, $lang),
            ], $lang));
        }
        if ($case->follow_up_date) {
            $lines[] = $this->t->ui('On :date', ['date' => $this->t->date($case->follow_up_date)]);
        }

        return $lines === [] ? [] : [$this->item(null, $lines)];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array{label: string, value: string}>
     */
    private function patient(array $snapshot, CaseHistory $case): array
    {
        $t = $this->t;
        $en = $snapshot['name'] ?? null;
        $bn = $snapshot['name_bn'] ?? null;
        $name = $t->locale() === PrintLocale::Bilingual
            ? implode(' / ', array_values(array_unique(array_filter([$en, $bn], 'filled'))))
            : (string) Bilingual::pick($en, $bn, $t->locale()->value);
        $gender = Gender::tryFrom((string) ($snapshot['gender'] ?? ''));

        $rows = [
            ['label' => $t->ui('Name'), 'value' => $name],
            ['label' => $t->ui('ID'), 'value' => (string) ($snapshot['code'] ?? '')],
            ['label' => $t->ui('Age'), 'value' => $this->each(fn (string $lang) => $t->digits(Patient::ageLabel($snapshot['age_text'] ?? null, $lang), $lang))],
            ['label' => $t->ui('Sex'), 'value' => $gender ? $t->enum($gender) : ''],
            ['label' => $t->ui('Mobile'), 'value' => (string) ($snapshot['phone'] ?? '')],
            ['label' => $t->ui('Date'), 'value' => $t->date($case->visit_date)],
            ['label' => $t->ui('Address'), 'value' => (string) ($snapshot['address'] ?? '')],
        ];

        return array_values(array_filter($rows, fn (array $row) => filled($row['value'])));
    }

    /**
     * @param  array<string, mixed>  $doctor
     * @return array{name: list<string>, credentials: list<string>, designation: list<string>, registration: string|null}
     */
    private function doctor(array $doctor): array
    {
        $credentials = [];
        foreach ($doctor['credentials'] ?? [] as $credential) {
            $credentials[] = implode(' / ', $this->t->text($credential['text_en'] ?? null, $credential['text_bn'] ?? null));
        }

        return [
            'name' => $this->t->text($doctor['name_en'] ?? null, $doctor['name_bn'] ?? null),
            'credentials' => array_values(array_filter($credentials, 'filled')),
            'designation' => $this->t->text($doctor['designation_en'] ?? null, $doctor['designation_bn'] ?? null),
            'registration' => filled($doctor['reg_no'] ?? null)
                ? $this->t->ui(':body Reg. No. :no', ['body' => $doctor['reg_label'] ?? 'BMDC', 'no' => $doctor['reg_no']])
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $chamber  the snapshot's chamber
     * @return array{name: list<string>, address: list<string>, phone: string|null, hours: list<string>, branches: list<string>}
     */
    private function chamber(array $chamber, CaseHistory $case): array
    {
        $branches = $case->chamber->branches
            ->map(fn ($b) => trim(implode(' / ', $this->t->text($b->name_en, $b->name_bn)).' '.implode(', ', $b->phones ?? [])))
            ->filter()->values()->all();

        return [
            'name' => $this->t->text($chamber['name_en'] ?? null, $chamber['name_bn'] ?? null),
            'address' => $this->t->text($chamber['address_en'] ?? null, $chamber['address_bn'] ?? null),
            'phone' => $chamber['phone'] ?? null,
            'hours' => $this->t->text($chamber['visiting_hours_en'] ?? null, $chamber['visiting_hours_bn'] ?? null),
            'branches' => $branches,
        ];
    }

    /** One value per print language, joined "en / bn" when bilingual (once when both are the same). */
    private function each(callable $perLanguage): string
    {
        return implode(' / ', array_values(array_unique(array_map($perLanguage, $this->t->locale()->languages()))));
    }

    /**
     * @param  list<string>  $lines
     * @return Item
     */
    private function item(?string $title, array $lines): array
    {
        return ['title' => $title, 'lines' => array_values(array_filter($lines, 'filled'))];
    }
}
