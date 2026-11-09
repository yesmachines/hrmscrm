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
        foreach ([
            'requires_attachment' => 'requires_attachment',
            'requires_handover' => 'requires_handover',
        ] as $leaveTypeColumn => $policyColumn) {
            if (
                Schema::hasColumn('leave_types', $leaveTypeColumn)
                && Schema::hasTable('leave_policies')
                && Schema::hasColumn('leave_policies', $policyColumn)
            ) {
                $leaveTypeIds = DB::table('leave_types')
                    ->where($leaveTypeColumn, true)
                    ->pluck('id');

                if ($leaveTypeIds->isNotEmpty()) {
                    DB::table('leave_policies')
                        ->whereIn('leave_type_id', $leaveTypeIds)
                        ->where($policyColumn, false)
                        ->update([$policyColumn => true]);
                }
            }
        }

        $columnsToDrop = array_values(array_filter(
            ['max_days', 'requires_attachment', 'requires_handover'],
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
            if (! Schema::hasColumn('leave_types', 'max_days')) {
                $table->integer('max_days')->nullable();
            }

            if (! Schema::hasColumn('leave_types', 'requires_attachment')) {
                $table->boolean('requires_attachment')->default(false);
            }

            if (! Schema::hasColumn('leave_types', 'requires_handover')) {
                $table->boolean('requires_handover')->default(false);
            }
        });

        foreach (['requires_attachment', 'requires_handover'] as $policyColumn) {
            if (
                Schema::hasTable('leave_policies')
                && Schema::hasColumn('leave_policies', $policyColumn)
            ) {
                $leaveTypeIds = DB::table('leave_policies')
                    ->where($policyColumn, true)
                    ->pluck('leave_type_id');

                if ($leaveTypeIds->isNotEmpty()) {
                    DB::table('leave_types')
                        ->whereIn('id', $leaveTypeIds)
                        ->update([$policyColumn => true]);
                }
            }
        }
    }
};
