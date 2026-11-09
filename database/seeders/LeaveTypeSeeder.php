<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'leave_name' => 'Annual Leave',
                'code' => 'ANNUAL',
                'is_paid' => true,
                'requires_approval' => true,
                'gender' => null,
                'status' => 1,
            ],
            [
                'leave_name' => 'Sick Leave',
                'code' => 'SICK',
                'is_paid' => true, // partially paid, handled in logic
                'requires_approval' => true,
                'gender' => null,
                'status' => 1,
            ],
            [
                'leave_name' => 'Compassionate Leave',
                'code' => 'COMPASSIONATE',
                'is_paid' => true,
                'requires_approval' => true,
                'gender' => null,
                'status' => 1,
            ],
            [
                'leave_name' => 'Festival Leave',
                'code' => 'FESTIVAL',
                'is_paid' => true,
                'requires_approval' => true,
                'gender' => null,
                'status' => 1,
            ],
            [
                'leave_name' => 'Maternity Leave',
                'code' => 'MATERNITY',
                'is_paid' => true,
                'requires_approval' => true,
                'gender' => 'Female',
                'status' => 1,
            ],
            [
                'leave_name' => 'Compensatory Leave',
                'code' => 'COMPENSATORY',
                'is_paid' => true,
                'requires_approval' => true,
                'gender' => null,
                'status' => 1,
            ],
            [
                'leave_name' => 'Parental Leave',
                'code' => 'PARENTAL',
                'is_paid' => true,
                'requires_approval' => true,
                'gender' => null,
                'status' => 1,
            ],
            [
                'leave_name' => 'Unpaid Leave',
                'code' => 'UNPAID',
                'is_paid' => false,
                'requires_approval' => true,
                'gender' => null,
                'status' => 1,
            ],
            [
                'leave_name' => 'Pilgrimage Leave',
                'code' => 'PILGRIMAGE',
                'is_paid' => false,
                'requires_approval' => true,
                'gender' => null,
                'status' => 1,
                'religion' => 'Muslim',
            ],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
