<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chambers are created by the admin and assigned to doctors. A chamber that
 * closes is deactivated (never deleted, visits point at it): it disappears
 * from assignment and from the prescription pad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chambers', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('letterhead_path');
        });
    }

    public function down(): void
    {
        Schema::table('chambers', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};
