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
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            // In HRMS, employees are in salescrm DB, so we use integer for posted_by without foreign key constraint
            // since cross-database foreign keys are not supported in many DBs, or we just rely on Eloquent.
            $table->unsignedBigInteger('posted_by');
            $table->longText('content')->nullable();
            $table->enum('status', ['published', 'hidden', 'removed', 'pending'])->default('published');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
