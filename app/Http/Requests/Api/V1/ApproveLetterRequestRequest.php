<?php

namespace App\Http\Requests\Api\V1;

use App\Models\EmployeeDocument;
use App\Support\LetterRequestApprovalFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApproveLetterRequestRequest extends FormRequest
{
    private ?EmployeeDocument $resolvedLetterRequest = null;

    private bool $letterRequestResolved = false;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $letterRequest = $this->letterRequest();
        $requiresHrDetails = $letterRequest !== null
            && in_array($letterRequest->status, ['submitted', 'under_review'], true)
            && LetterRequestApprovalFields::requiresHrDetails($letterRequest->documentType?->document_code);

        return [
            'details' => [
                ...($requiresHrDetails ? ['required'] : ['nullable']),
                'string',
                'max:2000',
            ],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $letterRequest = $this->letterRequest();

        if ($letterRequest === null || ! in_array($letterRequest->status, ['submitted', 'under_review'], true)) {
            return;
        }

        $fieldRequires = $letterRequest->documentType?->field_requires ?? [];
        $submittedFields = array_diff(array_keys($this->all()), ['details', 'remarks']);

        $validator->after(function (Validator $validator) use ($fieldRequires, $submittedFields): void {
            foreach ($submittedFields as $field) {
                $message = array_key_exists($field, $fieldRequires)
                    ? 'Employee-submitted fields are read-only during HR approval. Only details and remarks may be submitted.'
                    : 'Only HR details and remarks may be submitted during approval.';

                $validator->errors()->add((string) $field, $message);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'details.required' => 'HR details are required for this letter request.',
        ];
    }

    private function letterRequest(): ?EmployeeDocument
    {
        if (! $this->letterRequestResolved) {
            $this->resolvedLetterRequest = EmployeeDocument::query()
                ->with('documentType')
                ->whereHas('documentType.category', fn ($query) => $query->where('short_code', 'letter_requests'))
                ->find($this->route('id'));
            $this->letterRequestResolved = true;
        }

        return $this->resolvedLetterRequest;
    }
}
