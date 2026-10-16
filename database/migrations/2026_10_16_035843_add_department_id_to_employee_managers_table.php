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
        $schema = Schema::connection('salescrm');

        if ($schema->hasTable('employee_managers') && ! $schema->hasColumn('employee_managers', 'department_id')) {
            $schema->table('employee_managers', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable()->after('manager_id');
                $table->index('department_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('salescrm');

        if ($schema->hasTable('employee_managers') && $schema->hasColumn('employee_managers', 'department_id')) {
            $schema->table('employee_managers', function (Blueprint $table) {
                $table->dropIndex(['department_id']);
                $table->dropColumn('department_id');
            });
        }
    }
};
