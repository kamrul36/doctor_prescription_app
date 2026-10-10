<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One general prescription template for every doctor; a doctor's specialties
 * add their block to its `specialty` section instead of needing their own
 * template.
 *
 * Nothing is deleted:
 * - the shared `dental_pad` and `gynae_letterhead` templates are deactivated
 *   (they stay editable, and can be reactivated in Settings);
 * - doctors whose default was one of them are moved to the shared `general`
 *   template; advice defaults move to `general` too, unless that owner already
 *   has a default there (then they are cleared);
 * - the `specialty`, `treatment_plan` and `investigations_reviewed` sections
 *   are added to the shared `general` template if missing (so it matches a
 *   fresh PracticeSeeder run), and that template's sections are renumbered so
 *   they land in the right place.
 * Every old value is written to one `audit_logs` row, which `down()` reads to
 * restore it.
 *
 * `prescription_templates.specialty_code` is left in place but no longer used;
 * drop it in a later cleanup once the live data has been checked.
 *
 * Read-only pre-check:
 *   SELECT id, code, is_active, is_default FROM prescription_templates WHERE doctor_id IS NULL;
 *   SELECT id, default_template_id FROM doctors WHERE default_template_id IN (<retired ids>);
 *   SELECT id, doctor_id, is_default_for_template_id FROM advice_templates WHERE is_default_for_template_id IN (<retired ids>);
 */
return new class extends Migration
{
    private const ACTION = 'migration.retire_specialty_templates';

    private const RETIRED_CODES = ['dental_pad', 'gynae_letterhead'];

    public function up(): void
    {
        DB::transaction(function () {
            $generalId = DB::table('prescription_templates')->whereNull('doctor_id')->where('code', 'general')->value('id');
            $retired = DB::table('prescription_templates')
                ->whereNull('doctor_id')
                ->whereIn('code', self::RETIRED_CODES)
                ->get(['id', 'is_active', 'is_default']);
            $retiredIds = $retired->pluck('id')->all();

            $log = [
                'general_template_id' => $generalId,
                'templates' => $retired->map(fn ($t) => ['id' => $t->id, 'is_active' => (bool) $t->is_active, 'is_default' => (bool) $t->is_default])->all(),
                'doctors' => [],
                'advice' => [],
                'sections_added' => [],
                'section_sort_orders' => [],
            ];

            if ($retiredIds !== []) {
                if ($generalId !== null) {
                    $log['doctors'] = $this->repointDoctors($retiredIds, (int) $generalId);
                }
                $log['advice'] = $this->repointAdvice($retiredIds, $generalId === null ? null : (int) $generalId);

                DB::table('prescription_templates')->whereIn('id', $retiredIds)
                    ->update(['is_active' => false, 'is_default' => false, 'updated_at' => now()]);
            }

            if ($generalId !== null) {
                [$log['sections_added'], $log['section_sort_orders']] = $this->addGeneralSections((int) $generalId);
            }

            $log['owners_with_several_defaults'] = $this->ownersWithSeveralDefaults();

            if ($log['owners_with_several_defaults'] !== []) {
                Log::warning('Several active default templates for one owner; the next save in Settings keeps one.', [
                    'doctor_ids' => $log['owners_with_several_defaults'],
                ]);
            }

            DB::table('audit_logs')->insert([
                'action' => self::ACTION,
                'properties' => json_encode($log, JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        $properties = DB::table('audit_logs')->where('action', self::ACTION)->orderByDesc('id')->value('properties');

        if (! is_string($properties)) {
            return; // up() never ran to completion, so there is nothing to restore
        }

        /** @var array{templates: list<array{id: int, is_active: bool, is_default: bool}>, doctors: list<array{id: int, default_template_id: int}>, advice: list<array{id: int, is_default_for_template_id: int}>, sections_added: list<int>, section_sort_orders: array<string, int>} $log */
        $log = json_decode($properties, true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($log) {
            foreach ($log['templates'] as $t) {
                DB::table('prescription_templates')->where('id', $t['id'])
                    ->update(['is_active' => $t['is_active'], 'is_default' => $t['is_default']]);
            }

            foreach ($log['doctors'] as $d) {
                DB::table('doctors')->where('id', $d['id'])->update(['default_template_id' => $d['default_template_id']]);
            }

            foreach ($log['advice'] as $a) {
                DB::table('advice_templates')->where('id', $a['id'])->update(['is_default_for_template_id' => $a['is_default_for_template_id']]);
            }

            // Only the rows up() inserted; if the template was re-saved since, they are already gone.
            DB::table('prescription_template_sections')->whereIn('id', $log['sections_added'])->delete();

            foreach ($log['section_sort_orders'] as $id => $sortOrder) {
                DB::table('prescription_template_sections')->where('id', (int) $id)->update(['sort_order' => $sortOrder]);
            }
        });
    }

    /**
     * @param  list<int>  $retiredIds
     * @return list<array{id: int, default_template_id: int}>
     */
    private function repointDoctors(array $retiredIds, int $generalId): array
    {
        $doctors = DB::table('doctors')->whereIn('default_template_id', $retiredIds)->get(['id', 'default_template_id']);

        DB::table('doctors')->whereIn('id', $doctors->pluck('id'))->update(['default_template_id' => $generalId]);

        return $doctors->map(fn ($d) => ['id' => (int) $d->id, 'default_template_id' => (int) $d->default_template_id])->all();
    }

    /**
     * @param  list<int>  $retiredIds
     * @return list<array{id: int, is_default_for_template_id: int, now: int|null}>
     */
    private function repointAdvice(array $retiredIds, ?int $generalId): array
    {
        $changes = [];
        $advice = DB::table('advice_templates')->whereIn('is_default_for_template_id', $retiredIds)->orderBy('id')
            ->get(['id', 'doctor_id', 'is_default_for_template_id']);

        foreach ($advice as $a) {
            $ownerHasGeneralDefault = $generalId !== null && DB::table('advice_templates')
                ->where('is_default_for_template_id', $generalId)
                ->whereNull('deleted_at')
                ->where(fn ($q) => $a->doctor_id === null ? $q->whereNull('doctor_id') : $q->where('doctor_id', $a->doctor_id))
                ->exists();
            $target = $generalId !== null && ! $ownerHasGeneralDefault ? $generalId : null;

            DB::table('advice_templates')->where('id', $a->id)->update(['is_default_for_template_id' => $target]);
            $changes[] = ['id' => (int) $a->id, 'is_default_for_template_id' => (int) $a->is_default_for_template_id, 'now' => $target];
        }

        return $changes;
    }

    /**
     * Inserts `specialty` after `complaints`, `treatment_plan` after
     * `diagnosis` and `investigations_reviewed` after that (or at the end of
     * their zone) when missing, then renumbers.
     *
     * @return array{list<int>, array<int, int>} inserted ids, old sort_order by id
     */
    private function addGeneralSections(int $templateId): array
    {
        $sections = DB::table('prescription_template_sections')->where('template_id', $templateId)
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'section_key', 'zone', 'sort_order']);
        $oldOrders = $sections->mapWithKeys(fn ($s) => [(int) $s->id => (int) $s->sort_order])->all();
        $order = $sections->map(fn ($s) => ['id' => (int) $s->id, 'key' => $s->section_key, 'zone' => $s->zone])->all();
        $added = [];

        foreach ([
            ['specialty', 'left', 'complaints', 'History', 'ইতিহাস'],
            ['treatment_plan', 'right', 'diagnosis', 'Treatment Plan', 'চিকিৎসা পরিকল্পনা'],
            ['investigations_reviewed', 'right', 'treatment_plan', 'Previous Reports', 'পূর্বের রিপোর্ট'],
        ] as [$key, $zone, $after, $labelEn, $labelBn]) {
            if (collect($order)->contains('key', $key)) {
                continue;
            }

            $id = (int) DB::table('prescription_template_sections')->insertGetId([
                'template_id' => $templateId, 'section_key' => $key, 'zone' => $zone,
                'label_en' => $labelEn, 'label_bn' => $labelBn, 'sort_order' => 0, 'is_visible' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $added[] = $id;

            $position = $this->positionAfter($order, $after, $zone);
            array_splice($order, $position, 0, [['id' => $id, 'key' => $key, 'zone' => $zone]]);
        }

        if ($added === []) {
            return [[], []];
        }

        foreach ($order as $i => $row) {
            DB::table('prescription_template_sections')->where('id', $row['id'])->update(['sort_order' => $i]);
        }

        return [$added, $oldOrders];
    }

    /** @param  list<array{id: int, key: string, zone: string}>  $order */
    private function positionAfter(array $order, string $afterKey, string $zone): int
    {
        $last = null;

        foreach ($order as $i => $row) {
            if ($row['key'] === $afterKey) {
                return $i + 1;
            }
            if ($row['zone'] === $zone) {
                $last = $i;
            }
        }

        return $last === null ? count($order) : $last + 1;
    }

    /** @return list<int|null> owners (doctor id, null = shared) with more than one active default */
    private function ownersWithSeveralDefaults(): array
    {
        return DB::table('prescription_templates')
            ->where('is_active', true)->where('is_default', true)
            ->select('doctor_id')->groupBy('doctor_id')->havingRaw('COUNT(*) > 1')
            ->pluck('doctor_id')->map(fn ($id) => $id === null ? null : (int) $id)->all();
    }
};
