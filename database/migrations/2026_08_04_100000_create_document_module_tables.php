<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('document_categories')->cascadeOnDelete();
            $table->string('document_name');
            $table->string('document_code');
            $table->boolean('requires_number')->default(false);
            $table->boolean('requires_expiry')->default(false);
            $table->boolean('editable_before_approval')->default(false);
            $table->boolean('requires_hr_approval')->default(false);
            $table->boolean('requires_reminder')->default(false);
            $table->enum('record_source', ['uploaded', 'generated'])->nullable();
            $table->boolean('requires_attachments')->default(false);
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->string('template_name');
            $table->string('template_code');
            $table->tinyInteger('status')->default(1);
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->string('document_number')->nullable();
            $table->string('document_title')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->text('remarks')->nullable();
            $table->string('current_version')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'approved',
                'rejected',
                'cancelled',
                'archived',
                'expired',
            ])->default('draft')->index();
            $table->timestamps();
            $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->nullOnDelete();
        });

        Schema::create('employee_document_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_document_id')->constrained('employee_documents')->cascadeOnDelete();
            $table->enum('action_type', [
                'draft',
                'submitted',
                'under_review',
                'approved',
                'rejected',
                'cancelled',
                'archived',
                'expired',
            ]);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('done_by')->nullable();
            $table->dateTime('action_on')->nullable();
        });

        Schema::create('employee_document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_document_id')->constrained('employee_documents')->cascadeOnDelete();
            $table->string('version_no')->nullable();
            $table->string('file_path');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->dateTime('uploaded_date')->nullable();
            $table->text('change_notes')->nullable();
        });

        Schema::create('document_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_document_id')->constrained('employee_documents')->cascadeOnDelete();
            $table->dateTime('reminder_date')->nullable();
            $table->integer('days_before')->nullable();
            $table->dateTime('notification_sent')->nullable();
        });

        Schema::create('employee_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_document_id')->constrained('employee_documents')->cascadeOnDelete();
            $table->string('field_name')->nullable();
            $table->string('field_key')->nullable();
            $table->text('field_value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_request_details');
        Schema::dropIfExists('document_reminders');
        Schema::dropIfExists('employee_document_files');
        Schema::dropIfExists('employee_document_histories');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_types');
    }
};
