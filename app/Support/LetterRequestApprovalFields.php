<?php

namespace App\Support;

class LetterRequestApprovalFields
{
    /**
     * @var list<string>
     */
    private const DOCUMENT_CODES_REQUIRING_HR_DETAILS = [
        'noc',
        'salary_transfer_letter',
    ];

    public static function requiresHrDetails(?string $documentCode): bool
    {
        return in_array($documentCode, self::DOCUMENT_CODES_REQUIRING_HR_DETAILS, true);
    }
}
