<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasDuplicates = DB::table('leave_balances')
            ->select('employee_id', 'leave_type_id', 'year')
            ->groupBy('employee_id', 'leave_type_id', 'year')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException(
                'Cannot add the employee/type/year leave balance constraint until duplicate balance rows are reconciled.',
            );
        }

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->unique(
                ['employee_id', 'leave_type_id', 'year'],
                'leave_balance_employee_type_year_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropUnique('leave_balance_employee_type_year_unique');
        });
    }
};
