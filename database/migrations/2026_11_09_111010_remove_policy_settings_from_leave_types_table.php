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
        $columnsToDrop = array_values(array_filter(
            ['annual_limit', 'allow_once', 'allow_balance'],
            fn (string $column): bool => Schema::hasColumn('leave_types', $column),
        ));

        if ($columnsToDrop !== []) {
            Schema::table('leave_types', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_types', 'annual_limit')) {
                $table->integer('annual_limit')->nullable();
            }

            if (! Schema::hasColumn('leave_types', 'allow_once')) {
                $table->boolean('allow_once')->default(false);
            }

            if (! Schema::hasColumn('leave_types', 'allow_balance')) {
                $table->boolean('allow_balance')->default(true);
            }
        });
    }
};
