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
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('reward_categories')->cascadeOnDelete();
            $table->unsignedBigInteger('submitted_by')->index();
            $table->string('claim_no', 100)->unique()->index();
            $table->text('description');
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->string('document_file')->nullable();
            $table->dateTime('submitted_date')->index();
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])->default('pending')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
