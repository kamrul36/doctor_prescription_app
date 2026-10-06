<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the P1.0 `users.role` enum with spatie role assignments and adds
 * the account fields used by user admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('password');
        });

        if (Schema::hasColumn('users', 'role')) {
            $this->moveRolesToSpatie();

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['doctor', 'assistant'])->default('doctor')->after('email');
            $table->dropColumn(['phone', 'is_active']);
        });
    }

    private function moveRolesToSpatie(): void
    {
        $now = now();

        foreach (DB::table('users')->select('id', 'role')->get() as $user) {
            DB::table('roles')->insertOrIgnore([
                'name' => $user->role,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $roleId = DB::table('roles')->where('name', $user->role)->where('guard_name', 'web')->value('id');

            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $roleId,
                'model_type' => 'App\\Models\\User',
                'model_id' => $user->id,
            ]);
        }
    }
};
