<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('shortcode')->unique();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('asset_categories')->cascadeOnDelete();
            $table->string('referenceno')->unique();
            $table->string('asset_name');
            $table->text('details')->nullable();
            $table->enum('condition', [
                'New',
                'Excellent',
                'Good',
                'Fair',
                'Damaged',
                'Needs Repair',
            ])->default('New');
            $table->enum('status', [
                'Active',
                'Returned',
                'Under Maintenance',
                'Retired',
                'Lost',
            ])->default('Active');
            $table->string('attachment')->nullable();
            $table->timestamps();
        });

        Schema::create('assets_assigned', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedBigInteger('assigned_to')->index();
            $table->date('assigned_date');
            $table->date('returned_date')->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['Assigned', 'Returned'])->default('Assigned');
            $table->timestamps();
        });

        Schema::create('asset_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->enum('request_type', [
                'New',
                'Repair',
                'Replacement',
                'Lost',
                'Damage',
            ]);
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->unsignedBigInteger('requested_by')->index();
            $table->date('requested_date');
            $table->text('description');
            $table->enum('priority', ['Low', 'Normal', 'High', 'Urgent'])->default('Normal');
            $table->enum('status', [
                'Pending',
                'Under Review',
                'Approved',
                'Rejected',
                'In Progress',
                'Completed',
                'Cancelled',
            ])->default('Pending')->index();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_requests');
        Schema::dropIfExists('assets_assigned');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
    }
};
