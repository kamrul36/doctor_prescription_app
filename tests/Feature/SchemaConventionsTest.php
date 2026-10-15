<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every application table has `created_at` and `updated_at` filled by the
 * database (DEFAULT CURRENT_TIMESTAMP). New tables write
 *   $table->timestamp('created_at')->useCurrent();
 *   $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
 * instead of `$table->timestamps()`.
 */
class SchemaConventionsTest extends TestCase
{
    use RefreshDatabase;

    /** Framework-owned tables, not business data. */
    private const EXCLUDED = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'];

    public function test_every_table_has_database_filled_timestamps(): void
    {
        $tables = array_diff(array_column(Schema::getTables(Schema::getCurrentSchemaName()), 'name'), self::EXCLUDED, ['sqlite_sequence']);
        $this->assertContains('patients', $tables);

        $wrong = [];
        foreach ($tables as $table) {
            $columns = collect(Schema::getColumns($table))->keyBy('name');

            foreach (['created_at', 'updated_at'] as $column) {
                $default = strtolower((string) ($columns[$column]['default'] ?? ''));
                if (! $columns->has($column) || $columns[$column]['nullable'] || ! str_contains($default, 'current_timestamp')) {
                    $wrong[] = "{$table}.{$column}";
                }
            }
        }

        $this->assertSame([], $wrong, "Use useCurrent() / useCurrentOnUpdate() timestamps on:\n".implode("\n", $wrong));
    }

    public function test_a_raw_insert_gets_both_timestamps(): void
    {
        DB::table('lab_tests')->insert(['name' => 'Raw insert', 'is_active' => true]);

        $row = DB::table('lab_tests')->where('name', 'Raw insert')->first();
        $this->assertNotNull($row->created_at);
        $this->assertNotNull($row->updated_at);
    }
}
