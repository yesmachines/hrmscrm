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
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->unique();
            $table->enum('gender', ['M', 'F'])->nullable();
            $table->date('dob_personal')->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->string('nationality')->nullable();
            $table->string('religion')->nullable();
            $table->string('blood_group')->nullable();
            $table->string('personal_email')->nullable();
            $table->string('personal_mobile')->nullable();
            $table->string('address_uae')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_relation')->nullable();
            $table->string('emergency_mobile')->nullable();
            $table->integer('home_country')->nullable();
            $table->string('address_home')->nullable();
            $table->string('home_mobile')->nullable();
            $table->string('home_emergency_name')->nullable();
            $table->string('home_emergency_relation')->nullable();
            $table->string('home_emergency_mobile')->nullable();
            $table->enum('visa_type', ['visa', 'workpermit'])->nullable();
            $table->string('visa_from')->nullable();
            $table->date('dob_passport')->nullable();
            $table->float('total_experience')->nullable();
            $table->string('highest_education')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
