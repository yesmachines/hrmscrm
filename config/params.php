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
