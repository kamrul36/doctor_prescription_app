<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1.5 core: a visit (`case_histories`, the prescription header) and its
 * blocks. Columns for later steps (amend chain, copy-from-previous, row
 * version) are created now as nullable so those steps do not alter the
 * table. MySQL-portable: `json`, no database-specific types.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('prescription_templates')->nullOnDelete();
            $table->unsignedSmallInteger('visit_no_today');
            $table->date('visit_date');
            $table->string('visit_type', 32);
            $table->string('status', 16)->default('draft');
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('amended_from_id')->nullable()->constrained('case_histories')->nullOnDelete();
            $table->foreignId('copied_from_id')->nullable()->constrained('case_histories')->nullOnDelete();
            $table->string('prescription_no', 32)->nullable()->unique();
            $table->json('patient_snapshot')->nullable();
            $table->json('doctor_snapshot')->nullable();
            $table->json('vitals')->nullable();
            $table->text('diagnosis')->nullable();
            $table->json('specialty_data')->nullable();
            $table->unsignedSmallInteger('follow_up_value')->nullable();
            $table->string('follow_up_unit', 16)->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('notes_private')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'visit_date']);
            $table->index(['doctor_id', 'chamber_id', 'visit_date']);
            $table->index(['doctor_id', 'status']);
        });

        Schema::create('case_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_history_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16)->default('complaint');
            $table->string('text', 500);
            $table->unsignedSmallInteger('duration_value')->nullable();
            $table->string('duration_unit', 16)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('case_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_history_id')->constrained()->cascadeOnDelete();
            $table->string('label', 255)->nullable();
            $table->string('value', 500);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('treatment_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_history_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 500);
            $table->string('tooth_no', 32)->nullable();
            $table->string('status', 16)->default('planned');
            $table->decimal('fee', 12, 2)->nullable();
            $table->boolean('is_billable')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ordered_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_history_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16)->default('advised');
            $table->foreignId('lab_test_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_snapshot', 255);
            $table->string('timing_note', 100)->nullable();
            $table->string('result', 1000)->nullable();
            $table->date('result_date')->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('prescribed_medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_history_id')->constrained()->cascadeOnDelete();
            // The medicine catalog is deferred: the name is typed and kept as is.
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->string('display_name', 255);
            $table->string('dose_morning', 8)->nullable();
            $table->string('dose_noon', 8)->nullable();
            $table->string('dose_night', 8)->nullable();
            $table->string('dose_unit', 16)->nullable();
            $table->string('timing', 16)->nullable();
            $table->unsignedSmallInteger('duration_value')->nullable();
            $table->string('duration_unit', 16)->nullable();
            $table->string('instruction_en', 500)->nullable();
            $table->string('instruction_bn', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('case_advice', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_history_id')->constrained()->cascadeOnDelete();
            $table->text('text_en');
            $table->text('text_bn')->nullable();
            $table->foreignId('source_template_id')->nullable()->constrained('advice_templates')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['case_advice', 'prescribed_medicines', 'ordered_tests', 'treatment_plan_items', 'case_findings', 'case_complaints', 'case_histories'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
