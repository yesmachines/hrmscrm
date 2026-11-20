<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class WorkFromHomeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LeaveType::query()->updateOrCreate(
            ['code' => 'WFH'],
            [
                'leave_name' => 'Work From Home',
                'is_paid' => true,
                'requires_approval' => true,
                'gender' => null,
                'status' => 1,
            ],
        );
    }
}
