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
        if (Schema::connection('salescrm')->hasTable('employee_managers')) {
            Schema::connection('salescrm')->table('employee_managers', function (Blueprint $table) {
                if (! Schema::connection('salescrm')->hasColumn('employee_managers', 'department_id')) {
                    $table->unsignedBigInteger('department_id')->nullable()->after('manager_id');
                    $table->index('department_id');
                }

                if (! Schema::connection('salescrm')->hasColumn('employee_managers', 'priority')) {
                    $table->integer('priority')->nullable()->default(1)->after('department_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('salescrm')->hasTable('employee_managers')) {
            Schema::connection('salescrm')->table('employee_managers', function (Blueprint $table) {
                if (Schema::connection('salescrm')->hasColumn('employee_managers', 'priority')) {
                    $table->dropColumn('priority');
                }

                if (Schema::connection('salescrm')->hasColumn('employee_managers', 'department_id')) {
                    $table->dropIndex(['department_id']);
                    $table->dropColumn('department_id');
                }
            });
        }
    }
};
