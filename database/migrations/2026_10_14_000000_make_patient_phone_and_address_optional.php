<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The address is optional, and so is the phone (the prescription pad can register a
 * patient without one). Adds who the phone belongs to: `phone_is_personal` (default
 * true, so every existing row stays "the patient's own") and, when it is someone
 * else's, `phone_relation` ("Father", "Wife"...). Existing rows are not changed.
 * The phone index stays; it is not unique, so many NULLs are fine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->boolean('phone_is_personal')->default(true)->after('phone');
            $table->string('phone_relation', 60)->nullable()->after('phone_is_personal');
        });
    }

    public function down(): void
    {
        // Empty values become '' so the NOT NULL columns can be restored without losing rows.
        DB::table('patients')->whereNull('phone')->update(['phone' => '']);
        DB::table('patients')->whereNull('address')->update(['address' => '']);

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['phone_is_personal', 'phone_relation']);
            $table->string('phone', 32)->nullable(false)->change();
            $table->text('address')->nullable(false)->change();
        });
    }
};
