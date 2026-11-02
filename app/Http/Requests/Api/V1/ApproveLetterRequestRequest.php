<?php

namespace App\Http\Requests\Api\V1;

use App\Models\EmployeeDocument;
use App\Support\LetterRequestApprovalFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveLetterRequestRequest extends FormRequest
{
    private const APPROVAL_FIELD_RULES = [
        'salary_amount' => ['numeric', 'min:0.01', 'max:999999999.99'],
        'currency' => ['string', 'regex:/\A[A-Z]{3}\z/'],
        'bank_iban' => ['string', 'max:100'],
        'to_address' => ['string', 'max:500'],
        'visa_designation' => ['string', 'max:255'],
        'purpose' => ['string', 'max:1000'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $letterRequest = EmployeeDocument::query()
            ->with('documentType')
            ->whereHas('documentType.category', fn ($query) => $query->where('short_code', 'letter_requests'))
            ->find($this->route('id'));
        $approvalFields = in_array($letterRequest?->status, ['submitted', 'under_review'], true)
            ? LetterRequestApprovalFields::forDocumentCode($letterRequest->documentType?->document_code)
            : [];

        $rules = [
            'destination_country' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];

        foreach (self::APPROVAL_FIELD_RULES as $field => $fieldRules) {
            $rules[$field] = [
                ...(in_array($field, $approvalFields, true) ? ['required'] : ['nullable']),
                ...$fieldRules,
            ];
        }

        return $rules;
    }
}
