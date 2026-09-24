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
        Schema::create('reward_approval_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_id')->constrained('rewards')->cascadeOnDelete();
            $table->unsignedBigInteger('done_by')->index();
            $table->dateTime('done_on');
            $table->text('comments')->nullable();
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_approval_statuses');
    }
};
