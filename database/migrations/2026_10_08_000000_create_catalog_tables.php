<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->string('default_timing_note', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name_en');
            $table->string('name_bn')->nullable();
            $table->decimal('default_fee', 12, 2)->nullable();
            $table->boolean('is_billable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name_en');
        });

        Schema::create('advice_templates', function (Blueprint $table) {
            $table->id();
            // Null = shared with every doctor. Never cascades to shared: a private text must not become public.
            $table->foreignId('doctor_id')->nullable()->constrained('doctors');
            $table->string('specialty_code', 32);
            $table->string('title');
            $table->text('text_en');
            $table->text('text_bn')->nullable();
            $table->foreignId('is_default_for_template_id')->nullable()
                ->constrained('prescription_templates')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('title');
            $table->index(['doctor_id', 'specialty_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advice_templates');
        Schema::dropIfExists('procedures');
        Schema::dropIfExists('lab_tests');
    }
};
