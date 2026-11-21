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
        Schema::create('leave_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_name');
            $table->longText('template_code');
            $table->tinyInteger('status')->default(1)->index();
            $table->softDeletes();
            $table->index(['leave_type_id', 'organisation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_templates');
    }
};
