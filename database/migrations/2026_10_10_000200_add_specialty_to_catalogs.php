<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog items can belong to a specialty (null = general, for every doctor).
 * Typeahead ranks general items and those of the doctor's specialties first.
 *
 * Adds a nullable `specialty_code` to lab tests and procedures, tags the rows
 * seeded by CatalogSeeder (exact name/code match only, and only while still
 * untagged), and turns advice `'general'` into null. No rows are removed.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> specialty => lab test names */
    private const LAB_TESTS = [
        'gynae' => ['Pregnancy test (urine)', 'Serum beta-hCG', 'FSH', 'LH', 'Prolactin', 'Pap smear', 'Transvaginal USG'],
        'dental' => ['OPG (orthopantomogram)', 'IOPA X-ray'],
    ];

    /** @var array<string, list<string>> specialty => procedure codes */
    private const PROCEDURES = [
        'dental' => ['SCALING', 'POLISHING', 'EXTRACTION', 'FILLING', 'RCT', 'CROWN'],
        'gynae' => ['IUCD'],
    ];

    public function up(): void
    {
        foreach (['lab_tests', 'procedures'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('specialty_code', 32)->nullable()->after('id');
                $table->index('specialty_code');
            });
        }

        Schema::table('advice_templates', fn (Blueprint $table) => $table->string('specialty_code', 32)->nullable()->change());

        DB::transaction(function () {
            foreach (self::LAB_TESTS as $specialty => $names) {
                DB::table('lab_tests')->whereNull('specialty_code')->whereIn('name', $names)->update(['specialty_code' => $specialty]);
            }

            foreach (self::PROCEDURES as $specialty => $codes) {
                DB::table('procedures')->whereNull('specialty_code')->whereIn('code', $codes)->update(['specialty_code' => $specialty]);
            }

            DB::table('advice_templates')->where('specialty_code', 'general')->update(['specialty_code' => null]);
        });
    }

    public function down(): void
    {
        DB::table('advice_templates')->whereNull('specialty_code')->update(['specialty_code' => 'general']);

        Schema::table('advice_templates', fn (Blueprint $table) => $table->string('specialty_code', 32)->nullable(false)->change());

        foreach (['lab_tests', 'procedures'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['specialty_code']);
                $table->dropColumn('specialty_code');
            });
        }
    }
};
