<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who created and who last updated a patient or a catalog item. `patients`
 * already has `created_by`. Existing rows keep NULL (unknown): nothing is
 * guessed. Set by the RecordsAuthors model trait, never from the request.
 */
return new class extends Migration
{
    private const CATALOGS = ['lab_tests', 'procedures', 'advice_templates'];

    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        foreach (self::CATALOGS as $catalog) {
            Schema::table($catalog, function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::CATALOGS as $catalog) {
            Schema::table($catalog, function (Blueprint $table) {
                $table->dropConstrainedForeignId('updated_by');
                $table->dropConstrainedForeignId('created_by');
            });
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
