<?php

namespace Database\Seeders;

use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\Organisation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orgId = Organisation::first()?->id ?? 1;

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
                'policy' => [
                    'full_pay_days' => 30,
                    'half_pay_days' => 0,
                    'no_pay_days' => 0,
                    'requires_document_after_days' => null,
                    'requires_weekend_document' => false,
                    'carry_forward' => true,
                    'encashment' => true,
                    'remarks' => 'Standard Annual Leave Policy',
                    'requires_attachment' => false,
                    'probation_applicable' => false,
                    'minimum_service_months' => 6,
                ],
            ],
            [
                'leave_name' => 'Sick Leave',
                'code' => 'SICK',
                'is_paid' => true,
                'requires_attachment' => false,
                'requires_approval' => true,
                'max_days' => 90,
                'annual_limit' => 90,
                'gender' => null,
                'allow_once' => false,
                'allow_balance' => true,
                'status' => 1,
                'requires_handover' => false,
                'policy' => [
                    'full_pay_days' => 15,
                    'half_pay_days' => 30,
                    'no_pay_days' => 45,
                    'requires_document_after_days' => 2,
                    'requires_weekend_document' => true,
                    'carry_forward' => false,
                    'encashment' => false,
                    'remarks' => 'Requires medical certificate if > 2 days or spanning weekends.',
                    'requires_attachment' => false,
                    'probation_applicable' => true,
                    'minimum_service_months' => 0,
                ],
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
                'policy' => [
                    'full_pay_days' => 5,
                    'half_pay_days' => 0,
                    'no_pay_days' => 0,
                    'requires_document_after_days' => null,
                    'requires_weekend_document' => false,
                    'carry_forward' => false,
                    'encashment' => false,
                    'remarks' => 'For bereavement or immediate family emergencies.',
                    'requires_attachment' => false,
                    'probation_applicable' => true,
                    'minimum_service_months' => 0,
                ],
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
                'policy' => [
                    'full_pay_days' => 1,
                    'half_pay_days' => 0,
                    'no_pay_days' => 0,
                    'requires_document_after_days' => null,
                    'requires_weekend_document' => false,
                    'carry_forward' => false,
                    'encashment' => false,
                    'remarks' => 'One festival leave allowed per calendar year.',
                    'requires_attachment' => false,
                    'probation_applicable' => true,
                    'minimum_service_months' => 0,
                ],
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
                'policy' => [
                    'full_pay_days' => 45,
                    'half_pay_days' => 15,
                    'no_pay_days' => 0,
                    'requires_document_after_days' => 1,
                    'requires_weekend_document' => false,
                    'carry_forward' => false,
                    'encashment' => false,
                    'remarks' => 'Female employees only with medical certificate and expected due date.',
                    'requires_attachment' => true,
                    'probation_applicable' => false,
                    'minimum_service_months' => 6,
                ],
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
                'policy' => [
                    'full_pay_days' => 30,
                    'half_pay_days' => 0,
                    'no_pay_days' => 0,
                    'requires_document_after_days' => null,
                    'requires_weekend_document' => false,
                    'carry_forward' => false,
                    'encashment' => false,
                    'remarks' => 'Compensation off for authorized weekend / holiday work.',
                    'requires_attachment' => false,
                    'probation_applicable' => true,
                    'minimum_service_months' => 0,
                ],
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
                'policy' => [
                    'full_pay_days' => 5,
                    'half_pay_days' => 0,
                    'no_pay_days' => 0,
                    'requires_document_after_days' => 1,
                    'requires_weekend_document' => false,
                    'carry_forward' => false,
                    'encashment' => false,
                    'remarks' => 'Parental leave for employees within 6 months of child birth.',
                    'requires_attachment' => true,
                    'probation_applicable' => false,
                    'minimum_service_months' => 3,
                ],
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
                'policy' => [
                    'full_pay_days' => 0,
                    'half_pay_days' => 0,
                    'no_pay_days' => 30,
                    'requires_document_after_days' => null,
                    'requires_weekend_document' => false,
                    'carry_forward' => false,
                    'encashment' => false,
                    'remarks' => 'Leave without pay subject to management approval.',
                    'requires_attachment' => false,
                    'probation_applicable' => true,
                    'minimum_service_months' => 0,
                ],
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
                'policy' => [
                    'full_pay_days' => 0,
                    'half_pay_days' => 0,
                    'no_pay_days' => 30,
                    'requires_document_after_days' => 1,
                    'requires_weekend_document' => false,
                    'carry_forward' => false,
                    'encashment' => false,
                    'remarks' => 'Allowed once during service tenure. Requires pilgrimage visa/proof.',
                    'requires_attachment' => true,
                    'probation_applicable' => false,
                    'minimum_service_months' => 12,
                ],
            ],
        ];

        foreach ($types as $type) {
            $policyData = $type['policy'] ?? null;
            $typeData = Arr::except($type, ['policy']);

            $leaveType = LeaveType::updateOrCreate(
                ['code' => $typeData['code']],
                $typeData
            );

            if ($policyData) {
                LeavePolicy::updateOrCreate(
                    ['leave_type_id' => $leaveType->id],
                    array_merge($policyData, [
                        'organisation_id' => $orgId,
                    ])
                );
            }
        }
    }
}
