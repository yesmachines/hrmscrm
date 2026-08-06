<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->text('title')->nullable();
            $table->longText('description')->nullable();
            $table->string('idea_files')->nullable();
            $table->enum('status', [
                'submitted',
                'accepted',
                'approved',
                'rejected',
                'implemented',
            ])->default('submitted')->index();
            $table->longText('review_comment')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('idea_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('ideas')->cascadeOnDelete();
            $table->enum('action_type', [
                'submitted',
                'accepted',
                'approved',
                'rejected',
                'implemented',
            ]);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('done_by')->nullable();
            $table->dateTime('action_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_tracks');
        Schema::dropIfExists('ideas');
    }
};
