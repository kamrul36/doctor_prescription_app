<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every application table has `created_at` and `updated_at`, defined where
 * the table is created. Migrations write
 *   $table->trackedTimestamps();   // see AppServiceProvider
 * (`useCurrent()` on both: the database fills them on insert, Eloquent keeps
 * `updated_at` current on update).
 */
class SchemaConventionsTest extends TestCase
{
    use RefreshDatabase;

    /** Framework-owned tables, not business data. */
    private const EXCLUDED = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'];

    /** Append-only tables: rows are never updated, so they have `created_at` only. */
    private const APPEND_ONLY = ['audit_logs'];

    public function test_every_table_has_database_filled_timestamps(): void
    {
        $tables = array_diff(array_column(Schema::getTables(Schema::getCurrentSchemaName()), 'name'), self::EXCLUDED, ['sqlite_sequence']);
        $this->assertContains('patients', $tables);

        $wrong = [];
        foreach ($tables as $table) {
            $columns = collect(Schema::getColumns($table))->keyBy('name');
            $expected = in_array($table, self::APPEND_ONLY, true) ? ['created_at'] : ['created_at', 'updated_at'];

            foreach ($expected as $column) {
                $default = strtolower((string) ($columns[$column]['default'] ?? ''));
                if (! $columns->has($column) || $columns[$column]['nullable'] || ! str_contains($default, 'current_timestamp')) {
                    $wrong[] = "{$table}.{$column}";
                }
            }

            if (in_array($table, self::APPEND_ONLY, true) && $columns->has('updated_at')) {
                $wrong[] = "{$table}.updated_at (append-only table)";
            }
        }

        $this->assertSame([], $wrong, "Use \$table->trackedTimestamps() on:\n".implode("\n", $wrong));
    }

    public function test_a_raw_insert_gets_both_timestamps(): void
    {
        DB::table('lab_tests')->insert(['name' => 'Raw insert', 'is_active' => true]);

        $row = DB::table('lab_tests')->where('name', 'Raw insert')->first();
        $this->assertNotNull($row->created_at);
        $this->assertNotNull($row->updated_at);
    }
}
