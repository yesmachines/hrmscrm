<?php

namespace App\Support;

class LetterRequestApprovalFields
{
    /**
     * @var array<string, list<string>>
     */
    private const BY_DOCUMENT_CODE = [
        'noc' => ['to_address', 'visa_designation', 'purpose'],
        'salary_certificate' => ['salary_amount', 'currency'],
        'salary_transfer_letter' => ['salary_amount', 'currency', 'bank_iban'],
        'pay_slip' => [],
    ];

    /**
     * @return list<string>
     */
    public static function forDocumentCode(?string $documentCode): array
    {
        return self::BY_DOCUMENT_CODE[$documentCode] ?? [];
    }
}
