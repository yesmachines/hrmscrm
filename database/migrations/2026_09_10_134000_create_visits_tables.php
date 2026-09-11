<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->integer('total_visitors');
            $table->json('visitor_details');
            $table->string('company');
            $table->string('contact_no');
            $table->string('email');
            $table->text('purpose');
            $table->string('location');
            $table->dateTime('expected_start_date');
            $table->dateTime('expected_end_date')->nullable();
            $table->unsignedBigInteger('created_by')->index();
            $table->text('required_approvals')->nullable();
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'completed',
                'cancelled',
            ])->default('pending')->index();
            $table->timestamps();
        });

        Schema::create('visit_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->unsignedBigInteger('approver_id')->index();
            $table->text('instructions')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_approvals');
        Schema::dropIfExists('visits');
    }
};
