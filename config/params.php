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
