<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deletes the shared `dental_pad` and `gynae_letterhead` templates that
 * 2026_10_10_000100_retire_specialty_templates deactivated, with their
 * sections. Visits, doctors and advice that still pointed at one lose that
 * link (the foreign keys are ON DELETE SET NULL).
 *
 * Aborts without changing anything if one of them is still active.
 * The full rows and every reference are written to one `audit_logs` row, which
 * `down()` reads to put them back with the same ids.
 *
 * Read-only pre-check:
 *   SELECT id, code, is_active FROM prescription_templates WHERE doctor_id IS NULL AND code IN ('dental_pad', 'gynae_letterhead');
 */
return new class extends Migration
{
    private const ACTION = 'migration.delete_retired_templates';

    private const CODES = ['dental_pad', 'gynae_letterhead'];

    /** Tables with a nullable foreign key to prescription_templates, and its column. */
    private const REFERENCES = [
        'doctors' => 'default_template_id',
        'advice_templates' => 'is_default_for_template_id',
        'case_histories' => 'template_id',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $templates = DB::table('prescription_templates')->whereNull('doctor_id')->whereIn('code', self::CODES)->get();

            if ($templates->isEmpty()) {
                return;
            }

            $active = $templates->where('is_active', true)->pluck('code');
            if ($active->isNotEmpty()) {
                throw new RuntimeException('Template still active: '.$active->implode(', ').'. Deactivate it first, then migrate again.');
            }

            $ids = $templates->pluck('id')->all();
            $sections = DB::table('prescription_template_sections')->whereIn('template_id', $ids)->get();
            $references = [];
            foreach (self::REFERENCES as $table => $column) {
                $references[$table] = DB::table($table)->whereIn($column, $ids)->get(['id', $column])
                    ->map(fn ($r) => ['id' => (int) $r->id, 'template_id' => (int) $r->{$column}])->all();
            }

            DB::table('audit_logs')->insert([
                'action' => self::ACTION,
                'properties' => json_encode([
                    'templates' => $templates->map(fn ($t) => (array) $t)->all(),
                    'sections' => $sections->map(fn ($s) => (array) $s)->all(),
                    'references' => $references,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            DB::table('prescription_template_sections')->whereIn('template_id', $ids)->delete();
            $deleted = DB::table('prescription_templates')->whereIn('id', $ids)->delete();

            if ($deleted !== count($ids)) {
                throw new RuntimeException("Deleted {$deleted} templates, expected ".count($ids).'; nothing was changed.');
            }
        });
    }

    public function down(): void
    {
        $properties = DB::table('audit_logs')->where('action', self::ACTION)->orderByDesc('id')->value('properties');

        if (! is_string($properties)) {
            return; // up() deleted nothing, so there is nothing to restore
        }

        $log = json_decode($properties, true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($log) {
            DB::table('prescription_templates')->insert($log['templates']);
            DB::table('prescription_template_sections')->insert($log['sections']);

            foreach (self::REFERENCES as $table => $column) {
                foreach ($log['references'][$table] ?? [] as $row) {
                    DB::table($table)->where('id', $row['id'])->update([$column => $row['template_id']]);
                }
            }

            DB::table('audit_logs')->where('action', self::ACTION)->delete();
        });
    }
};
