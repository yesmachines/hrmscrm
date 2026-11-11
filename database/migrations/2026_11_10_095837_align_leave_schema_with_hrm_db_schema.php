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
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->date('start_date')->change();
            $table->date('end_date')->nullable()->change();
            $table->decimal('total_days', 8, 2)->nullable()->change();
            $table->enum('request_type', ['full_day', 'fullday', 'firsthalf', 'secondhalf'])
                ->default('full_day')
                ->change();
        });

        DB::table('leave_requests')
            ->where('request_type', 'full_day')
            ->update(['request_type' => 'fullday']);

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->enum('request_type', ['fullday', 'firsthalf', 'secondhalf'])
                ->default('fullday')
                ->change();
        });

        Schema::table('leave_balances', function (Blueprint $table): void {
            $table->decimal('allocated', 10, 2)->default(0)->change();
            $table->decimal('carried_forward', 10, 2)->default(0)->change();
            $table->decimal('used', 10, 2)->default(0)->change();
            $table->decimal('balance', 10, 2)->default(0)->change();
            $table->decimal('encashed', 10, 2)->default(0)->change();
            $table->decimal('earned', 10, 2)->default(0)->change();
        });

        Schema::table('leave_policies', function (Blueprint $table): void {
            $table->dropColumn([
                'full_pay_days',
                'half_pay_days',
                'no_pay_days',
                'carry_forward',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->enum('request_type', ['full_day', 'fullday', 'firsthalf', 'secondhalf'])
                ->default('fullday')
                ->change();
        });

        DB::table('leave_requests')
            ->where('request_type', 'fullday')
            ->update(['request_type' => 'full_day']);

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dateTime('start_date')->change();
            $table->dateTime('end_date')->nullable()->change();
            $table->float('total_days')->nullable()->change();
            $table->enum('request_type', ['full_day', 'firsthalf', 'secondhalf'])
                ->default('full_day')
                ->change();
        });

        Schema::table('leave_balances', function (Blueprint $table): void {
            $table->float('allocated')->default(0)->change();
            $table->float('carried_forward')->default(0)->change();
            $table->float('used')->default(0)->change();
            $table->float('balance')->default(0)->change();
            $table->float('encashed')->default(0)->change();
            $table->float('earned')->default(0)->change();
        });

        Schema::table('leave_policies', function (Blueprint $table): void {
            $table->integer('full_pay_days')->nullable();
            $table->integer('half_pay_days')->nullable();
            $table->integer('no_pay_days')->nullable();
            $table->boolean('carry_forward')->default(false);
        });
    }
};
