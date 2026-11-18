<?php

return [
    'status' => [
        1 => 'Active',
        2 => 'Inactive',
    ],
    'field_requires' => [
        'ref_number',  // document_number
        'expiry_date',
        'attachments',
        'uid',
        'issue_date',
        'total_experience',
    ],
    'leave_type' => [
        // Organisation-specific limits remain in leave_policies; these are shared special rules.
        'parental_birth_window_months' => 6,
        'festival_name_match' => 'Festival',
        'special_rules' => [
            'SICK' => [
                // Includes holidays within the range and on the immediately adjacent calendar days.
                'requires_holiday_document' => true,
            ],
            'MATERNITY' => [
                'validator' => 'maternity',
                'fields' => ['due_date'],
                'marital_status' => 'married',
            ],
            'PARENTAL' => [
                'validator' => 'parental',
                'fields' => ['child_birth_date'],
                'marital_status' => 'married',
            ],
            'COMPASSIONATE' => [
                'validator' => 'compassionate',
                'fields' => ['family_relationship'],
            ],
            'FESTIVAL' => [
                'validator' => 'festival',
                'fields' => ['festival_id'],
                'annual_request_limit' => 1,
            ],
            'UNPAID' => [
                'validator' => 'unpaid',
                'allow_balance' => false,
                // Internal safeguard only; never expose this value in response metadata or errors.
                'internal_annual_limit' => 90,
                'remarks_required' => true,
                'handover_after_days' => 3,
            ],
        ],
        'detail_fields' => [
            'due_date' => [
                'label' => 'Expected Due Date',
                'type' => 'date',
                'required' => true,
                'rules' => ['nullable', 'date'],
                'required_message' => 'An expected due date is required for Maternity Leave.',
            ],
            'child_birth_date' => [
                'label' => "Child's Date of Birth",
                'storage_label' => 'Child Birth Date',
                'type' => 'date',
                'required' => true,
                'rules' => ['nullable', 'date'],
                'required_message' => "The child's date of birth is required for Parental Leave.",
                'help' => "The leave start date must be within :months months of the child's birth.",
            ],
            'family_relationship' => [
                'label' => 'Family Relationship',
                'type' => 'select',
                'required' => true,
                'rules' => ['nullable', 'string'],
                'required_message' => 'Please select a valid family relationship for Compassionate Leave.',
                'options_config' => 'params.leave_type.compassionate_relationships',
                'help' => 'Limits apply per request using the leave days calculated from your dates.',
            ],
            'festival_id' => [
                'label' => 'Festival',
                'storage_label' => 'Festival ID',
                'type' => 'festival',
                'required' => true,
                'rules' => ['nullable', 'integer', 'exists:festivals,id'],
                'required_message' => 'Festival selection is required for Festival Leave.',
            ],
        ],
        'compassionate_relationships' => [
            'spouse' => ['label' => 'Spouse', 'group' => 'A', 'max_days' => 5],
            'child' => ['label' => 'Child', 'group' => 'A', 'max_days' => 5],
            'mother' => ['label' => 'Mother', 'group' => 'A', 'max_days' => 5],
            'father' => ['label' => 'Father', 'group' => 'A', 'max_days' => 5],
            'sibling' => ['label' => 'Sibling', 'group' => 'A', 'max_days' => 5],
            'grandparent' => ['label' => 'Grandparent', 'group' => 'B', 'max_days' => 3],
            'grandchild' => ['label' => 'Grandchild', 'group' => 'B', 'max_days' => 3],
            'in_laws' => ['label' => 'In-laws', 'group' => 'B', 'max_days' => 3],
        ],
        'gender_options' => [
            'male' => 'Male',
            'female' => 'Female',
        ],
        'religion_options' => [
            'Other',
            'Hinduism',
            'Islam',
            'Christianity',
            'Sikhism',
            'Buddhism',
            'Jainism',
            'Judaism',
        ],
    ],
    'leave_request' => [
        'request_types' => ['fullday', 'firsthalf', 'secondhalf'],
        'active_statuses' => ['applied', 'approved'],
        'statuses' => ['applied', 'approved', 'rejected', 'cancelled'],
        'certificate_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
        'certificate_max_kb' => 10240,
        'signature_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'signature_max_kb' => 5120,
    ],
    'leave_policy' => [
        'exclusion_rules' => [
            'department_overlap' => 'When true, blocks overlapping applied or approved requests of the same leave type by another employee in the same organisation and department. Requires the applicant to have a department.',
        ],
    ],
    'letter_request' => [
        'status' => [
            'draft' => 'Draft',
            'submitted' => 'Pending Approval',
            'under_review' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'archived' => 'Archived',
            'expired' => 'Expired',
        ],
    ],
];
