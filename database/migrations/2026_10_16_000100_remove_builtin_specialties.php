<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Specialties are created by users now, so the two the system used to seed
 * (`gynae`, `dental`) and the built-in prescription blocks they carried go.
 *
 * - catalog rows tagged with them (lab tests, procedures, advice) become
 *   general (specialty_id NULL); nothing in the catalog is deleted;
 * - a seeded specialty no doctor has is deleted; one a doctor has is only
 *   deactivated (doctors keep it);
 * - `specialties.block` is dropped.
 * `case_histories.specialty_data` is left alone: saved prescriptions keep it.
 * The old values are written to one `audit_logs` row, which `down()` reads.
 *
 * Read-only pre-check:
 *   SELECT s.code, s.is_active, COUNT(ds.id) FROM specialties s LEFT JOIN doctor_specialties ds ON ds.specialty_id = s.id
 *     WHERE s.code IN ('gynae', 'dental') GROUP BY s.id;
 */
return new class extends Migration
{
    private const ACTION = 'migration.remove_builtin_specialties';

    private const CODES = ['gynae', 'dental'];

    private const CATALOGS = ['lab_tests', 'procedures', 'advice_templates'];

    public function up(): void
    {
        DB::transaction(function () {
            $specialties = DB::table('specialties')->whereIn('code', self::CODES)->get();
            $ids = $specialties->pluck('id')->all();

            $log = [
                'specialties' => $specialties->map(fn ($s) => (array) $s)->all(),
                'blocks' => DB::table('specialties')->whereNotNull('block')->pluck('block', 'id')->all(),
                'deleted' => [],
                'catalog' => [],
            ];

            foreach (self::CATALOGS as $table) {
                $log['catalog'][$table] = DB::table($table)->whereIn('specialty_id', $ids)->pluck('specialty_id', 'id')->all();
                DB::table($table)->whereIn('specialty_id', $ids)->update(['specialty_id' => null]);
            }

            foreach ($specialties as $s) {
                if (DB::table('doctor_specialties')->where('specialty_id', $s->id)->exists()) {
                    DB::table('specialties')->where('id', $s->id)->update(['is_active' => false, 'updated_at' => now()]);
                } else {
                    DB::table('specialties')->where('id', $s->id)->delete();
                    $log['deleted'][] = (int) $s->id;
                }
            }

            DB::table('audit_logs')->insert([
                'action' => self::ACTION,
                'properties' => json_encode($log, JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        });

        Schema::table('specialties', fn (Blueprint $table) => $table->dropColumn('block'));
    }

    public function down(): void
    {
        Schema::table('specialties', fn (Blueprint $table) => $table->string('block', 32)->nullable()->after('name'));

        $properties = DB::table('audit_logs')->where('action', self::ACTION)->orderByDesc('id')->value('properties');

        if (! is_string($properties)) {
            return; // up() never ran to completion, so there is nothing to restore
        }

        $log = json_decode($properties, true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($log) {
            foreach ($log['specialties'] as $s) {
                if (in_array($s['id'], $log['deleted'], true)) {
                    DB::table('specialties')->insert($s);
                } else {
                    DB::table('specialties')->where('id', $s['id'])->update(['is_active' => $s['is_active']]);
                }
            }

            foreach ($log['blocks'] as $id => $block) {
                DB::table('specialties')->where('id', (int) $id)->update(['block' => $block]);
            }

            foreach ($log['catalog'] as $table => $rows) {
                foreach ($rows as $id => $specialtyId) {
                    DB::table($table)->where('id', (int) $id)->update(['specialty_id' => $specialtyId]);
                }
            }

            DB::table('audit_logs')->where('action', self::ACTION)->delete();
        });
    }
};
