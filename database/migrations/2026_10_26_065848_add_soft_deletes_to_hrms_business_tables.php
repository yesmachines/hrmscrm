<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES_WITH_SOFT_DELETES = [
        'asset_categories',
        'assets',
        'designations',
        'document_categories',
        'document_templates',
        'document_types',
        'event_types',
        'leave_balances',
        'leave_policies',
        'leave_types',
        'reward_categories',
    ];

    public function up(): void
    {
        foreach (self::TABLES_WITH_SOFT_DELETES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES_WITH_SOFT_DELETES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
