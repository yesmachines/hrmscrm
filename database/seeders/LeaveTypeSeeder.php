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
                'requires_attachment' => false,
                'requires_approval' => true,
                'max_days' => 30,
                'annual_limit' => 30,
                'gender' => null,
                'allow_once' => false,
                'allow_balance' => true,
                'status' => 1,
                'requires_handover' => true,
            ],
            [
                'leave_name' => 'Sick Leave',
                'code' => 'SICK',
                'is_paid' => true, // partially paid, handled in logic
                'requires_attachment' => true, // if > 2 days
                'requires_approval' => true,
                'max_days' => 90,
                'annual_limit' => 90,
                'gender' => null,
                'allow_once' => false,
                'allow_balance' => true,
                'status' => 1,
                'requires_handover' => false,
            ],
            [
                'leave_name' => 'Compassionate Leave',
                'code' => 'COMPASSIONATE',
                'is_paid' => true,
                'requires_attachment' => false,
                'requires_approval' => true,
                'max_days' => 5,
                'annual_limit' => null,
                'gender' => null,
                'allow_once' => false,
                'allow_balance' => false,
                'status' => 1,
                'requires_handover' => false,
            ],
            [
                'leave_name' => 'Festival Leave',
                'code' => 'FESTIVAL',
                'is_paid' => true,
                'requires_attachment' => false,
                'requires_approval' => true,
                'max_days' => 1,
                'annual_limit' => 1,
                'gender' => null,
                'allow_once' => false,
                'allow_balance' => true,
                'status' => 1,
                'requires_handover' => false,
            ],
            [
                'leave_name' => 'Maternity Leave',
                'code' => 'MATERNITY',
                'is_paid' => true,
                'requires_attachment' => true,
                'requires_approval' => true,
                'max_days' => 60,
                'annual_limit' => null,
                'gender' => 'Female',
                'allow_once' => false,
                'allow_balance' => false,
                'status' => 1,
                'requires_handover' => true,
            ],
            [
                'leave_name' => 'Compensatory Leave',
                'code' => 'COMPENSATORY',
                'is_paid' => true,
                'requires_attachment' => false,
                'requires_approval' => true,
                'max_days' => null,
                'annual_limit' => null,
                'gender' => null,
                'allow_once' => false,
                'allow_balance' => true,
                'status' => 1,
                'requires_handover' => false,
            ],
            [
                'leave_name' => 'Parental Leave',
                'code' => 'PARENTAL',
                'is_paid' => true,
                'requires_attachment' => true,
                'requires_approval' => true,
                'max_days' => 5,
                'annual_limit' => null,
                'gender' => null,
                'allow_once' => false,
                'allow_balance' => false,
                'status' => 1,
                'requires_handover' => false,
            ],
            [
                'leave_name' => 'Unpaid Leave',
                'code' => 'UNPAID',
                'is_paid' => false,
                'requires_attachment' => false,
                'requires_approval' => true,
                'max_days' => null,
                'annual_limit' => null,
                'gender' => null,
                'allow_once' => false,
                'allow_balance' => false,
                'status' => 1,
                'requires_handover' => true,
            ],
            [
                'leave_name' => 'Pilgrimage Leave',
                'code' => 'PILGRIMAGE',
                'is_paid' => false,
                'requires_attachment' => true,
                'requires_approval' => true,
                'max_days' => 30,
                'annual_limit' => null,
                'gender' => null,
                'allow_once' => true,
                'allow_balance' => false,
                'status' => 1,
                'requires_handover' => true,
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
