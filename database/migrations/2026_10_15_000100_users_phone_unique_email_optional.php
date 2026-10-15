<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Users log in with their email or their phone, so the phone is the required
 * identifier and must be unique; the email becomes optional. The phone column
 * stays nullable so accounts created before this change survive (the user forms
 * require a phone). Many NULL phones or emails are fine under a unique index.
 *
 * Aborts without changing anything if two users already share a phone.
 * Pre-check (read-only):
 *   SELECT phone, GROUP_CONCAT(id) FROM users WHERE phone IS NOT NULL GROUP BY phone HAVING COUNT(*) > 1;
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('users')
            ->whereNotNull('phone')
            ->select('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone');

        if ($duplicates->isNotEmpty()) {
            $ids = DB::table('users')->whereIn('phone', $duplicates)->orderBy('id')->pluck('id')->implode(', ');

            throw new RuntimeException("Users share a phone number (user ids: {$ids}). Give each a different phone, then migrate again.");
        }

        // Older forms could store '' for "no phone"; that is NULL now.
        DB::table('users')->where('phone', '')->update(['phone' => null]);

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // A user without an email gets a placeholder, so NOT NULL can be restored
        // without losing the account. The .invalid domain can never receive mail.
        DB::table('users')->whereNull('email')->orderBy('id')->each(function (object $user) {
            DB::table('users')->where('id', $user->id)->update(['email' => "user-{$user->id}@no-email.invalid"]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
