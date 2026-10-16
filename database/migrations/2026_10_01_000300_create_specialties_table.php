<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An admin-managed list that doctors pick from (or add to by typing a new
 * one). Every doctor is a general physician; a specialty is an add-on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->trackedTimestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialties');
    }
};
