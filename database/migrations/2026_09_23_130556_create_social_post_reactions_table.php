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
        Schema::create('social_post_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id');
            $table->enum('reactions', ['like', 'sad', 'angry', 'dislike', 'excited', 'clap', 'celebrate']);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['post_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_post_reactions');
    }
};
