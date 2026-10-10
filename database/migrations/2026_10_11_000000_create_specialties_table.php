<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Specialties become data: an admin-managed list that doctors pick from (or
 * add to by typing a new one). Replaces the `specialty_code` strings on
 * doctor_specialties, lab_tests, procedures and advice_templates with a
 * `specialty_id` foreign key.
 *
 * No row is removed: each code is copied to the matching id first and the
 * copy is counted; on a mismatch the migration stops before dropping
 * anything. `block` links a specialty to a built-in prescription block
 * (gynae history, dental findings); typed specialties have none.
 *
 * Read-only pre-check:
 *   SELECT specialty_code, COUNT(*) FROM doctor_specialties GROUP BY specialty_code;
 *   SELECT specialty_code, COUNT(*) FROM lab_tests GROUP BY specialty_code;  -- also procedures, advice_templates
 */
return new class extends Migration
{
    /** Tables whose `specialty_code` string becomes a nullable `specialty_id`. */
    private const CATALOGS = ['lab_tests', 'procedures', 'advice_templates'];

    private const SEED = [
        ['code' => 'gynae', 'name' => 'Gynaecology & Obstetrics', 'block' => 'gynae'],
        ['code' => 'dental', 'name' => 'Dental', 'block' => 'dental'],
    ];

    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            // Built-in prescription block this specialty shows (App\Domain\Practice\SpecialtyBlock); null for typed ones.
            $table->string('block', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        foreach (self::SEED as $row) {
            DB::table('specialties')->insert($row + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Any other code already in use (none expected) becomes a list entry too, so nothing is lost.
        $codes = collect(['doctor_specialties', ...self::CATALOGS])
            ->flatMap(fn ($t) => DB::table($t)->whereNotNull('specialty_code')->distinct()->pluck('specialty_code'))
            ->unique()
            ->diff(array_column(self::SEED, 'code'));
        foreach ($codes as $code) {
            DB::table('specialties')->insert([
                'code' => $code, 'name' => ucfirst((string) $code), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (['doctor_specialties', ...self::CATALOGS] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('specialty_id')->nullable()->after('id')->constrained('specialties')->restrictOnDelete();
            });
        }

        DB::transaction(function () {
            $ids = DB::table('specialties')->pluck('id', 'code');

            foreach (['doctor_specialties', ...self::CATALOGS] as $name) {
                foreach ($ids as $code => $id) {
                    DB::table($name)->where('specialty_code', $code)->update(['specialty_id' => $id]);
                }

                $expected = DB::table($name)->whereNotNull('specialty_code')->count();
                $copied = DB::table($name)->whereNotNull('specialty_id')->count();

                if ($expected !== $copied) {
                    throw new RuntimeException("{$name}: copied {$copied} specialties, expected {$expected}; nothing was dropped.");
                }
            }
        });

        // The new index goes in first: on MySQL the old composite one backs the doctor_id foreign key.
        Schema::table('doctor_specialties', fn (Blueprint $table) => $table->unique(['doctor_id', 'specialty_id']));
        Schema::table('doctor_specialties', function (Blueprint $table) {
            $table->dropUnique(['doctor_id', 'specialty_code']);
            $table->dropColumn('specialty_code');
        });

        Schema::table('advice_templates', fn (Blueprint $table) => $table->index(['doctor_id', 'specialty_id']));
        Schema::table('advice_templates', function (Blueprint $table) {
            $table->dropIndex(['doctor_id', 'specialty_code']);
            $table->dropColumn('specialty_code');
        });

        foreach (['lab_tests', 'procedures'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['specialty_code']);
                $table->dropColumn('specialty_code');
            });
        }
    }

    public function down(): void
    {
        $codes = DB::table('specialties')->pluck('code', 'id');

        Schema::table('doctor_specialties', fn (Blueprint $table) => $table->string('specialty_code', 32)->nullable()->after('doctor_id'));
        Schema::table('advice_templates', fn (Blueprint $table) => $table->string('specialty_code', 32)->nullable()->after('doctor_id'));
        foreach (['lab_tests', 'procedures'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('specialty_code', 32)->nullable()->after('id'));
        }

        foreach (['doctor_specialties', ...self::CATALOGS] as $name) {
            foreach ($codes as $id => $code) {
                DB::table($name)->where('specialty_id', $id)->update(['specialty_code' => $code]);
            }
        }

        Schema::table('doctor_specialties', function (Blueprint $table) {
            $table->string('specialty_code', 32)->nullable(false)->change();
        });
        Schema::table('doctor_specialties', function (Blueprint $table) {
            // MySQL keeps using the composite unique for the doctor_id FK until another index exists.
            $table->unique(['doctor_id', 'specialty_code']);
            $table->dropUnique(['doctor_id', 'specialty_id']);
            $table->dropConstrainedForeignId('specialty_id');
        });
        Schema::table('advice_templates', function (Blueprint $table) {
            $table->index(['doctor_id', 'specialty_code']);
            $table->dropIndex(['doctor_id', 'specialty_id']);
            $table->dropConstrainedForeignId('specialty_id');
        });
        foreach (['lab_tests', 'procedures'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('specialty_id');
                $table->index('specialty_code');
            });
        }

        Schema::dropIfExists('specialties');
    }
};
