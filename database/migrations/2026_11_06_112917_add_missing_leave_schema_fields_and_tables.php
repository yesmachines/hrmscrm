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
        Schema::table('leave_policies', function (Blueprint $table) {
            $table->integer('annual_limit')->nullable()->after('organisation_id');
            $table->boolean('allow_once')->default(false)->after('annual_limit');
            $table->boolean('allow_balance')->default(true)->after('allow_once');
            $table->boolean('requires_handover')->default(false)->after('requires_weekend_document');
            $table->integer('carry_forward_weeks')->nullable()->after('requires_handover');
            $table->json('exclude_rules')->nullable()->after('minimum_service_months');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->enum('request_type', ['full_day', 'firsthalf', 'secondhalf'])
                ->default('full_day')
                ->after('created_by');
        });

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dateTime('leave_validity')->nullable()->after('pending');
        });

        Schema::create('employee_wfh_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->json('weekdays');
            $table->boolean('is_default')->default(false);
            $table->tinyInteger('status')->default(1);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('leave_policy_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_policy_id')->constrained()->cascadeOnDelete();
            $table->integer('from_day');
            $table->integer('to_day');
            $table->decimal('pay_percentage', 5, 2);
        });

        Schema::create('leave_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_balance_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->index();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_type');
            $table->decimal('quantity', 10, 2);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balance_transactions');
        Schema::dropIfExists('leave_policy_tiers');
        Schema::dropIfExists('employee_wfh_schedules');

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropColumn('leave_validity');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('request_type');
        });

        Schema::table('leave_policies', function (Blueprint $table) {
            $table->dropColumn([
                'annual_limit',
                'allow_once',
                'allow_balance',
                'requires_handover',
                'carry_forward_weeks',
                'exclude_rules',
            ]);
        });
    }
};
