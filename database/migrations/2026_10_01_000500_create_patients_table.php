<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->date('dob')->nullable();
            $table->unsignedSmallInteger('age_years')->nullable();
            $table->date('age_recorded_on')->nullable();
            $table->string('gender', 16);
            // Phone and address are optional (the pad can register a patient without them).
            $table->string('phone', 32)->nullable()->index();
            // Whose phone it is: the patient's own, or a relative's ("Father", "Wife"...).
            $table->boolean('phone_is_personal')->default(true);
            $table->string('phone_relation', 60)->nullable();
            $table->string('alt_phone', 32)->nullable()->index();
            $table->text('address')->nullable();
            $table->string('occupation')->nullable();
            $table->string('blood_group', 4)->nullable();
            $table->string('patient_type', 16)->default('general');
            // Free text on purpose: a general physician just needs to read them at the visit.
            $table->text('allergies')->nullable();
            $table->text('conditions')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->trackedTimestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
