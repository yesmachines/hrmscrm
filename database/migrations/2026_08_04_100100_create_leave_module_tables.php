<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('leave_name');
            $table->string('code');
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('requires_approval')->default(true);
            $table->integer('max_days')->nullable();
            $table->integer('annual_limit')->nullable();
            $table->string('gender')->nullable();
            $table->boolean('allow_once')->default(false);
            $table->boolean('allow_balance')->default(true);
            $table->tinyInteger('status')->default(1);
            $table->boolean('requires_handover')->default(false);
        });

        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->integer('full_pay_days')->nullable();
            $table->integer('half_pay_days')->nullable();
            $table->integer('no_pay_days')->nullable();
            $table->integer('requires_document_after_days')->nullable();
            $table->boolean('requires_weekend_document')->default(false);
            $table->integer('allocation_days')->nullable();
            $table->boolean('carry_forward')->default(false);
            $table->boolean('encashment')->default(false);
            $table->text('remarks')->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('probation_applicable')->default(false);
            $table->integer('minimum_service_months')->nullable();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->dateTime('start_date')->index();
            $table->dateTime('end_date')->nullable()->index();
            $table->float('total_days')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['applied', 'approved', 'rejected', 'cancelled'])->default('applied')->index();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
        });

        Schema::create('leave_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->enum('action_type', ['applied', 'approved', 'rejected', 'cancelled']);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('done_by')->nullable();
            $table->dateTime('action_on')->nullable();
        });

        Schema::create('leave_request_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->string('file_path');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->dateTime('uploaded_date')->nullable();
        });

        Schema::create('leave_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->string('field_name')->nullable();
            $table->string('field_key')->nullable();
            $table->string('field_value')->nullable();
        });

        Schema::create('leave_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->string('approval_level')->nullable();
            $table->integer('approver_id')->nullable();
            $table->text('remarks')->nullable();
            $table->dateTime('approved_date')->nullable();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->integer('year');
            $table->float('allocated')->default(0);
            $table->float('carried_forward')->default(0);
            $table->float('used')->default(0);
            $table->float('balance')->default(0);
            $table->float('encashed')->default(0);
            $table->float('earned')->default(0);
            $table->dateTime('last_calculated_at')->nullable();
            $table->float('pending')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_request_approvals');
        Schema::dropIfExists('leave_request_details');
        Schema::dropIfExists('leave_request_files');
        Schema::dropIfExists('leave_histories');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
    }
};
