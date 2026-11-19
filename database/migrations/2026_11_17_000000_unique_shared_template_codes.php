<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * unique(doctor_id, code) lets two shared templates (doctor_id NULL) share a
 * code, because NULLs are distinct in a unique index. A virtual column that
 * turns NULL into 0 (doctor ids start at 1) closes the gap in the database.
 * No rows are changed; it aborts if shared duplicates already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        $dupes = DB::table('prescription_templates')
            ->whereNull('doctor_id')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('code');

        if ($dupes->isNotEmpty()) {
            throw new RuntimeException('Duplicate shared template codes, fix them first: '.$dupes->implode(', '));
        }

        Schema::table('prescription_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_key')->virtualAs('COALESCE(doctor_id, 0)');
            $table->unique(['owner_key', 'code'], 'prescription_templates_owner_key_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_templates', function (Blueprint $table) {
            $table->dropUnique('prescription_templates_owner_key_code_unique');
        });

        Schema::table('prescription_templates', function (Blueprint $table) {
            $table->dropColumn('owner_key');
        });
    }
};
