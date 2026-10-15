<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every application table gets database-filled timestamps:
 * `created_at` DEFAULT CURRENT_TIMESTAMP and `updated_at` DEFAULT CURRENT_TIMESTAMP
 * ON UPDATE CURRENT_TIMESTAMP (MySQL; SQLite has no ON UPDATE). Rows written by raw
 * queries, pivots and seeders now get them too. New tables write the same two lines
 * instead of `$table->timestamps()` (SchemaConventionsTest checks it).
 *
 * Framework-owned tables (migrations, cache, jobs, sessions, password reset tokens)
 * are left alone. No row is deleted: empty timestamps are filled first (from the other
 * timestamp, else now), which down() keeps. down() restores the nullable columns
 * without defaults and drops the columns this migration added.
 *
 * Pre-check (read-only): SELECT COUNT(*) FROM <table> WHERE created_at IS NULL;
 */
return new class extends Migration
{
    /** Tables created with `$table->timestamps()` (both columns nullable, no default). */
    private const TIMESTAMPED = [
        'users', 'sequence_counters', 'permissions', 'roles',
        'chambers', 'chamber_branches', 'doctors', 'doctor_credentials', 'doctor_chambers',
        'doctor_chamber_fees', 'prescription_templates', 'prescription_template_sections',
        'patients', 'lab_tests', 'procedures', 'advice_templates',
        'doctor_specialties', 'specialties',
        'case_histories', 'case_complaints', 'case_findings', 'treatment_plan_items',
        'ordered_tests', 'prescribed_medicines', 'case_advice',
    ];

    /** Tables without timestamps: both columns are added. */
    private const WITHOUT = ['model_has_permissions', 'model_has_roles', 'role_has_permissions'];

    /** audit_logs already has `created_at` DEFAULT CURRENT_TIMESTAMP; only `updated_at` is added. */
    private const AUDIT = 'audit_logs';

    public function up(): void
    {
        foreach (self::TIMESTAMPED as $table) {
            DB::table($table)->whereNull('created_at')
                ->update(['created_at' => DB::raw('COALESCE(updated_at, CURRENT_TIMESTAMP)')]);
            DB::table($table)->whereNull('updated_at')->update(['updated_at' => DB::raw('created_at')]);

            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('created_at')->useCurrent()->change();
                $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->change();
            });
        }

        // Added nullable, filled, then given the default: SQLite cannot add a column
        // with a non-constant default, but it can rebuild the table with one.
        foreach (self::WITHOUT as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
            DB::table($table)->update(['created_at' => DB::raw('CURRENT_TIMESTAMP'), 'updated_at' => DB::raw('CURRENT_TIMESTAMP')]);
            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('created_at')->useCurrent()->change();
                $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->change();
            });
        }

        Schema::table(self::AUDIT, function (Blueprint $t) {
            $t->timestamp('updated_at')->nullable();
        });
        DB::table(self::AUDIT)->update(['updated_at' => DB::raw('created_at')]);
        Schema::table(self::AUDIT, function (Blueprint $t) {
            $t->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->change();
        });
    }

    public function down(): void
    {
        Schema::table(self::AUDIT, function (Blueprint $t) {
            $t->dropColumn('updated_at');
        });

        foreach (self::WITHOUT as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['created_at', 'updated_at']);
            });
        }

        foreach (self::TIMESTAMPED as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('created_at')->nullable()->change();
                $t->timestamp('updated_at')->nullable()->change();
            });
        }
    }
};
