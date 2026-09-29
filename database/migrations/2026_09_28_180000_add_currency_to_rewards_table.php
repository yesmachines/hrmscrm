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
        if (Schema::hasTable('rewards') && ! Schema::hasColumn('rewards', 'currency')) {
            Schema::table('rewards', function (Blueprint $table) {
                $table->string('currency', 10)->default('AED')->after('amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('rewards') && Schema::hasColumn('rewards', 'currency')) {
            Schema::table('rewards', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }
};
