<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1.7 Finance (spec §4.6, §8): invoices with their lines and payments, and
 * expenses with their categories. All five tables are created here, in their
 * final shape, so the expenses and reports step adds code only.
 *
 * Money is decimal(12,2) (Money cast). Financial rows are never deleted:
 * invoices and payments are voided with a reason, expenses soft-deleted.
 * Unique keys (spec §4.6, rule A11): invoices.invoice_no, invoices.case_history_id,
 * invoice_lines(invoice_id, treatment_plan_item_id), payments.receipt_no,
 * payments(received_by, idempotency_key).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 32)->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('case_history_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('issued');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('discount_reason', 255)->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('due', 12, 2)->default(0);
            $table->string('referred_by', 255)->nullable();
            $table->foreignId('under_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->trackedTimestamps();

            $table->index(['status', 'issued_at']);
            $table->index('issued_at');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('item_code', 32)->nullable();
            $table->string('kind', 16);
            $table->string('description_en', 255);
            $table->string('description_bn', 255)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->foreignId('procedure_id')->nullable()->constrained()->nullOnDelete();
            // Plan items are replaced on every draft save; the link is a key for the finalize sync, not a hard reference.
            $table->foreignId('treatment_plan_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->trackedTimestamps();

            $table->unique(['invoice_id', 'treatment_plan_item_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 32)->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 16);
            $table->string('reference', 100)->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            // Scoped by the user who sent it (rule A10): a double submit makes one payment.
            $table->string('idempotency_key', 100);
            $table->trackedTimestamps();

            $table->unique(['received_by', 'idempotency_key']);
            $table->index('received_at');
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 100);
            $table->string('name_bn', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->trackedTimestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('spent_on');
            $table->string('method', 16);
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->trackedTimestamps();

            $table->index('spent_on');
        });
    }

    public function down(): void
    {
        foreach (['expenses', 'expense_categories', 'payments', 'invoice_lines', 'invoices'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
