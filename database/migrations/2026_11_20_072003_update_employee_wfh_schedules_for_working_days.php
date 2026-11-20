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
        Schema::table('employee_wfh_schedules', function (Blueprint $table): void {
            $table->dropColumn(['weekdays', 'is_default']);
            $table->integer('working_days')->after('employee_id');
            $table->date('requested_date')->after('working_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_wfh_schedules', function (Blueprint $table): void {
            $table->json('weekdays')->nullable();
            $table->boolean('is_default')->default(false);
            $table->dropColumn(['working_days', 'requested_date']);
        });
    }
};
