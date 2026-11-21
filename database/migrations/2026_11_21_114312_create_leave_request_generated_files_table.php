<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_request_generated_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_template_id')->nullable()->constrained('leave_templates')->nullOnDelete();
            $table->string('version_no');
            $table->string('file_path');
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->dateTime('generated_at');
            $table->text('change_notes')->nullable();
            $table->index(['leave_request_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_request_generated_files');
    }
};
