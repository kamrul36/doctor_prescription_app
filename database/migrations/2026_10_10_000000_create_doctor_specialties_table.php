<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every doctor is a general physician; specialties are add-ons and a doctor
 * can have several. Moves `doctors.specialty_code` into `doctor_specialties`.
 *
 * Safe to run on live data: the old value is copied first, the copy is
 * checked, and only then is the column dropped. Read-only pre-check:
 *
 *   SELECT specialty_code, COUNT(*) FROM doctors GROUP BY specialty_code;
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_specialties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->string('specialty_code', 32);
            $table->timestamps();

            $table->unique(['doctor_id', 'specialty_code']);
        });

        try {
            $this->copySpecialties();
        } catch (Throwable $e) {
            // Leave the database as it was so the migration can simply be re-run.
            Schema::dropIfExists('doctor_specialties');

            throw $e;
        }

        Schema::table('doctors', fn (Blueprint $table) => $table->dropColumn('specialty_code'));
    }

    private function copySpecialties(): void
    {
        DB::transaction(function () {
            $now = now();
            $rows = DB::table('doctors')
                ->whereNotNull('specialty_code')
                ->where('specialty_code', '!=', 'general')
                ->get(['id', 'specialty_code']);

            foreach ($rows as $row) {
                DB::table('doctor_specialties')->insert([
                    'doctor_id' => $row->id,
                    'specialty_code' => $row->specialty_code,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $copied = DB::table('doctor_specialties')->count();

            if ($copied !== $rows->count()) {
                throw new RuntimeException("Copied {$copied} doctor specialties, expected {$rows->count()}; doctors.specialty_code is kept.");
            }
        });
    }

    /**
     * Puts one specialty per doctor back (the alphabetically first one). A
     * doctor who was given several after this migration keeps only that one.
     */
    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('specialty_code', 32)->default('general')->after('reg_no');
        });

        $first = DB::table('doctor_specialties')
            ->select('doctor_id', DB::raw('MIN(specialty_code) as specialty_code'))
            ->groupBy('doctor_id')
            ->get();

        foreach ($first as $row) {
            DB::table('doctors')->where('id', $row->doctor_id)->update(['specialty_code' => $row->specialty_code]);
        }

        Schema::dropIfExists('doctor_specialties');
    }
};
