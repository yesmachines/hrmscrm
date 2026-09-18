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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_type_id')->constrained('event_types')->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->dateTime('start_datetime')->index();
            $table->dateTime('end_datetime')->nullable();
            $table->string('external_link')->nullable();
            $table->enum('status', ['draft', 'published', 'cancelled'])->default('published')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->string('file_path')->nullable();
            $table->boolean('show_dashboard')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
