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

        if ($schema->hasTable('employee_managers') && ! $schema->hasColumn('employee_managers', 'priority')) {
            $schema->table('employee_managers', function (Blueprint $table) {
                $table->integer('priority')->nullable()->default(1);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('salescrm');

        if ($schema->hasTable('employee_managers') && $schema->hasColumn('employee_managers', 'priority')) {
            $schema->table('employee_managers', function (Blueprint $table) {
                $table->dropColumn('priority');
            });
        }
    }
};
