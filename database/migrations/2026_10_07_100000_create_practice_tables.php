<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chambers', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_bn')->nullable();
            $table->text('address_en')->nullable();
            $table->text('address_bn')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('letterhead_path')->nullable();
            $table->timestamps();
        });

        Schema::create('chamber_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chamber_id')->constrained()->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_bn')->nullable();
            $table->json('phones')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_bn')->nullable();
            $table->string('designation_en')->nullable();
            $table->string('designation_bn')->nullable();
            $table->string('reg_label', 32)->default('BMDC');
            $table->string('reg_no', 64)->nullable();
            $table->string('specialty_code', 32)->default('general');
            $table->string('signature_path')->nullable();
            // FK added after prescription_templates exists (they reference each other).
            $table->unsignedBigInteger('default_template_id')->nullable();
            $table->timestamps();
        });

        Schema::create('doctor_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->string('text_en');
            $table->string('text_bn')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('doctor_chambers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chamber_id')->constrained()->cascadeOnDelete();
            $table->text('visiting_hours_en')->nullable();
            $table->text('visiting_hours_bn')->nullable();
            $table->timestamps();

            $table->unique(['doctor_id', 'chamber_id']);
        });

        Schema::create('doctor_chamber_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chamber_id')->constrained()->cascadeOnDelete();
            $table->string('visit_type', 32);
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['doctor_id', 'chamber_id', 'visit_type'], 'doctor_chamber_fees_unique');
        });

        Schema::create('prescription_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('specialty_code', 32)->default('general');
            $table->string('paper_size', 8)->default('A4');
            $table->string('default_print_mode', 32)->default('with_letterhead');
            $table->json('margins')->nullable();
            $table->string('layout', 32)->default('single_column');
            $table->boolean('show_barcode')->default(false);
            $table->string('barcode_source', 32)->default('prescription_no');
            $table->boolean('show_branch_footer')->default(false);
            $table->boolean('show_visiting_hours')->default(false);
            $table->boolean('show_signature')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // A code is unique per owner; global templates have doctor_id NULL
            // (uniqueness for those is enforced in SaveTemplateAction).
            $table->unique(['doctor_id', 'code']);
        });

        Schema::create('prescription_template_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('prescription_templates')->cascadeOnDelete();
            $table->string('section_key', 48);
            $table->string('zone', 16);
            $table->string('label_en')->nullable();
            $table->string('label_bn')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['template_id', 'section_key']);
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->foreign('default_template_id')->references('id')->on('prescription_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('doctors', fn (Blueprint $table) => $table->dropForeign(['default_template_id']));

        foreach ([
            'prescription_template_sections', 'prescription_templates', 'doctor_chamber_fees',
            'doctor_chambers', 'doctor_credentials', 'doctors', 'chamber_branches', 'chambers',
        ] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
